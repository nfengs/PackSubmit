package service

import (
	"regexp"
	"strings"

	"gorm.io/gorm"

	"packsubmit/internal/dto"
	"packsubmit/internal/model"
	"packsubmit/internal/pkg/auth"
	"packsubmit/internal/pkg/resp"
	"packsubmit/internal/pkg/ulidx"
)

var usernamePattern = regexp.MustCompile(`^[A-Za-z0-9_]{1,50}$`)

type UserFilter struct {
	Page      Page
	Keyword   string
	Role      int8
	Status    int8
	StatusSet bool
}

func (s *AdminService) ListUsers(filter UserFilter) ([]model.User, int64, error) {
	q := s.DB.Model(&model.User{})

	if keyword := strings.TrimSpace(filter.Keyword); keyword != "" {
		like := likePattern(keyword)
		q = q.Where("(username LIKE ? OR real_name LIKE ? OR dept LIKE ?)", like, like, like)
	}

	if filter.Role > 0 {
		q = q.Where("role = ?", filter.Role)
	}

	if filter.StatusSet {
		q = q.Where("status = ?", filter.Status)
	}

	var total int64
	if err := q.Count(&total).Error; err != nil {
		return nil, 0, err
	}

	_, size, offset := normalizePage(filter.Page)

	var users []model.User
	err := q.Preload("Supplier").Order("created_at DESC").Offset(offset).Limit(size).Find(&users).Error

	return users, total, err
}

// resolveUserSupplier 校验角色与供应商归属，返回 supplier_id 与展示用 dept
func (s *AdminService) resolveUserSupplier(tx *gorm.DB, role int8, supplierID, dept string) (*string, string, error) {
	if role != auth.RoleSupplier {
		return nil, strings.TrimSpace(dept), nil
	}

	var supplier model.Supplier

	if strings.TrimSpace(supplierID) == "" {
		return nil, "", resp.Biz("供应商角色必须选择所属供应商")
	}

	if err := tx.First(&supplier, "id = ?", supplierID).Error; err != nil {
		return nil, "", resp.Biz("供应商不存在或已被删除")
	}

	return &supplier.ID, supplier.Name, nil
}

func (s *AdminService) CreateUser(operator *model.User, input dto.UserInput) (*model.User, error) {
	username := strings.TrimSpace(input.Username)

	if !usernamePattern.MatchString(username) {
		return nil, resp.Biz("账号只能包含字母、数字和下划线，且不超过 50 位")
	}

	if input.Role < auth.RoleSupplier || input.Role > auth.RoleViewer {
		return nil, resp.Biz("角色不合法")
	}

	if err := auth.AssertPassword(input.Password); err != nil {
		return nil, resp.Biz(err.Error())
	}

	hash, err := auth.HashPassword(input.Password)
	if err != nil {
		return nil, resp.New(resp.CodeServer, "密码加密失败")
	}

	var created model.User

	err = s.DB.Transaction(func(tx *gorm.DB) error {
		var exists int64
		if err := tx.Model(&model.User{}).Where("username = ?", username).Count(&exists).Error; err != nil {
			return err
		}

		if exists > 0 {
			return resp.Biz("登录账号已存在")
		}

		supplierID, dept, err := s.resolveUserSupplier(tx, input.Role, input.SupplierID, input.Dept)
		if err != nil {
			return err
		}

		user := model.User{
			ID:           ulidx.New(),
			Username:     username,
			PasswordHash: hash,
			RealName:     strings.TrimSpace(input.RealName),
			Role:         input.Role,
			Dept:         dept,
			SupplierID:   supplierID,
			Phone:        strings.TrimSpace(input.Phone),
			Status:       model.UserEnabled,
			TokenVersion: 1,
		}

		if err := tx.Create(&user).Error; err != nil {
			return err
		}

		created = user

		return s.recordMaster(tx, "user", user.ID, user.Username, operator.ID, "user_create", "新增用户",
			"首次录入账号："+user.Username)
	})

	if err != nil {
		return nil, err
	}

	return &created, nil
}

func (s *AdminService) UpdateUser(operator *model.User, id string, input dto.UserInput) (*model.User, error) {
	var user model.User

	if err := s.DB.First(&user, "id = ?", id).Error; err != nil {
		return nil, resp.ErrNotFound
	}

	if operator.ID == user.ID && input.Role != user.Role {
		return nil, resp.Biz("不能修改自己的角色")
	}

	err := s.DB.Transaction(func(tx *gorm.DB) error {
		supplierID, dept, err := s.resolveUserSupplier(tx, input.Role, input.SupplierID, input.Dept)
		if err != nil {
			return err
		}

		changes := diffContent([][3]string{
			{"姓名", user.RealName, strings.TrimSpace(input.RealName)},
			{"角色", roleText(user.Role), roleText(input.Role)},
			{"部门/供应商", user.Dept, dept},
			{"联系电话", user.Phone, strings.TrimSpace(input.Phone)},
		})

		if err := tx.Model(&user).Updates(map[string]any{
			"real_name":   strings.TrimSpace(input.RealName),
			"role":        input.Role,
			"dept":        dept,
			"supplier_id": supplierID,
			"phone":       strings.TrimSpace(input.Phone),
		}).Error; err != nil {
			return err
		}

		if changes == "" {
			changes = "无字段变化"
		}

		return s.recordMaster(tx, "user", user.ID, user.Username, operator.ID, "user_update", "编辑用户", changes)
	})

	if err != nil {
		return nil, err
	}

	if err := s.DB.Preload("Supplier").First(&user, "id = ?", id).Error; err != nil {
		return nil, err
	}

	return &user, nil
}

func (s *AdminService) UpdateUserStatus(operator *model.User, id string, status int8) error {
	var user model.User

	if err := s.DB.First(&user, "id = ?", id).Error; err != nil {
		return resp.ErrNotFound
	}

	if operator.ID == user.ID && status == model.UserDisabled {
		return resp.Biz("不能禁用当前登录账号")
	}

	return s.DB.Transaction(func(tx *gorm.DB) error {
		if err := tx.Model(&user).Update("status", status).Error; err != nil {
			return err
		}

		label := "启用"
		if status == model.UserDisabled {
			label = "禁用"
		}

		return s.recordMaster(tx, "user", user.ID, user.Username, operator.ID, "user_status", label+"用户",
			"账号状态："+label)
	})
}

func (s *AdminService) ResetUserPassword(operator *model.User, id, password string) error {
	var user model.User

	if err := s.DB.First(&user, "id = ?", id).Error; err != nil {
		return resp.ErrNotFound
	}

	if err := auth.AssertPassword(password); err != nil {
		return resp.Biz(err.Error())
	}

	hash, err := auth.HashPassword(password)
	if err != nil {
		return resp.New(resp.CodeServer, "密码加密失败")
	}

	return s.DB.Transaction(func(tx *gorm.DB) error {
		if err := tx.Model(&user).Updates(map[string]any{
			"password_hash": hash,
			"token_version": user.TokenVersion + 1,
		}).Error; err != nil {
			return err
		}

		return s.recordMaster(tx, "user", user.ID, user.Username, operator.ID, "user_reset", "重置密码",
			"管理员重置了登录密码（密码值不记录）")
	})
}

func (s *AdminService) UserLogs(id string) ([]model.MasterLog, error) {
	logs := make([]model.MasterLog, 0)

	err := s.DB.Where("biz_type = ? AND target_id = ?", "user", id).
		Order("created_at DESC").Limit(100).Find(&logs).Error

	return logs, err
}

// ImportUsers Excel 列：登录账号、姓名、角色、部门/供应商、联系电话、初始密码
func (s *AdminService) ImportUsers(operator *model.User, rows [][]string) (*dto.ImportResult, error) {
	result := &dto.ImportResult{Errors: []string{}}

	for index := 1; index < len(rows); index++ {
		row := rows[index]
		line := index + 1

		username := rowValue(row, 0)
		realName := rowValue(row, 1)
		roleValue := rowValue(row, 2)
		dept := rowValue(row, 3)
		phone := rowValue(row, 4)
		password := rowValue(row, 5)

		if username == "" {
			result.Errors = append(result.Errors, importError(line, "登录账号不能为空"))
			continue
		}

		if !usernamePattern.MatchString(username) {
			result.Errors = append(result.Errors, importError(line, "账号只能包含字母、数字和下划线"))
			continue
		}

		role, ok := parseRole(roleValue)
		if !ok {
			result.Errors = append(result.Errors, importError(line, "角色需填写 1/2/3/4 或 供应商/评审员/管理员/查看者"))
			continue
		}

		var supplier *model.Supplier

		if role == auth.RoleSupplier {
			if dept == "" {
				result.Errors = append(result.Errors, importError(line, "供应商角色必须填写供应商名称"))
				continue
			}

			var found model.Supplier
			if err := s.DB.First(&found, "name = ?", dept).Error; err != nil {
				result.Errors = append(result.Errors, importError(line, "供应商「"+dept+"」不存在"))
				continue
			}

			supplier = &found
		}

		if password != "" {
			if err := auth.AssertPassword(password); err != nil {
				result.Errors = append(result.Errors, importError(line, err.Error()))
				continue
			}
		}

		var existing model.User
		exists := s.DB.Where("username = ?", username).First(&existing).Error == nil

		err := s.DB.Transaction(func(tx *gorm.DB) error {
			if exists {
				updates := map[string]any{
					"real_name": realName,
					"role":      role,
					"dept":      dept,
					"phone":     phone,
				}

				if supplier != nil {
					updates["supplier_id"] = supplier.ID
					updates["dept"] = supplier.Name
				} else {
					updates["supplier_id"] = nil
				}

				if password != "" {
					hash, err := auth.HashPassword(password)
					if err != nil {
						return err
					}

					updates["password_hash"] = hash
					updates["token_version"] = existing.TokenVersion + 1
				}

				if err := tx.Model(&existing).Updates(updates).Error; err != nil {
					return err
				}

				return s.recordMaster(tx, "user", existing.ID, existing.Username, operator.ID, "user_update", "导入更新用户",
					"批量导入更新账号："+existing.Username)
			}

			if password == "" {
				return resp.Biz("新增账号必须填写初始密码")
			}

			hash, err := auth.HashPassword(password)
			if err != nil {
				return err
			}

			user := model.User{
				ID:           ulidx.New(),
				Username:     username,
				PasswordHash: hash,
				RealName:     realName,
				Role:         role,
				Dept:         dept,
				Phone:        phone,
				Status:       model.UserEnabled,
				TokenVersion: 1,
			}

			if supplier != nil {
				user.SupplierID = &supplier.ID
				user.Dept = supplier.Name
			}

			if err := tx.Create(&user).Error; err != nil {
				return err
			}

			return s.recordMaster(tx, "user", user.ID, user.Username, operator.ID, "user_create", "导入新增用户",
				"批量导入新增账号："+user.Username)
		})

		if err != nil {
			result.Errors = append(result.Errors, importError(line, err.Error()))
			continue
		}

		if exists {
			result.Updated++
		} else {
			result.Created++
		}
	}

	return result, nil
}

func parseRole(value string) (int8, bool) {
	switch strings.TrimSpace(value) {
	case "1", "供应商":
		return auth.RoleSupplier, true
	case "2", "评审员":
		return auth.RoleReviewer, true
	case "3", "管理员":
		return auth.RoleAdmin, true
	case "4", "查看者":
		return auth.RoleViewer, true
	default:
		return 0, false
	}
}

func roleText(role int8) string {
	switch role {
	case auth.RoleSupplier:
		return "供应商"
	case auth.RoleReviewer:
		return "评审员"
	case auth.RoleAdmin:
		return "管理员"
	case auth.RoleViewer:
		return "查看者"
	default:
		return "未知"
	}
}
