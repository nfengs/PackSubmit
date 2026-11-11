package service

import (
	"strings"

	"gorm.io/gorm"

	"packsubmit/internal/dto"
	"packsubmit/internal/model"
	"packsubmit/internal/pkg/auth"
	"packsubmit/internal/pkg/resp"
	"packsubmit/internal/pkg/ulidx"
)

type MasterDataService struct {
	DB *gorm.DB
}

func NewMasterDataService(db *gorm.DB) *MasterDataService {
	return &MasterDataService{DB: db}
}

func (s *MasterDataService) Customers(keyword string) ([]model.Customer, error) {
	items := make([]model.Customer, 0)

	q := s.DB.Where("status = ?", 1)

	if keyword = strings.TrimSpace(keyword); keyword != "" {
		like := "%" + escapeLike(keyword) + "%"
		q = q.Where("(name LIKE ? OR contact LIKE ?)", like, like)
	}

	err := q.Order("name ASC").Limit(500).Find(&items).Error

	return items, err
}

func (s *MasterDataService) Suppliers(keyword string) ([]model.Supplier, error) {
	items := make([]model.Supplier, 0)

	q := s.DB.Where("status = ?", 1)

	if keyword = strings.TrimSpace(keyword); keyword != "" {
		like := "%" + escapeLike(keyword) + "%"
		q = q.Where("(name LIKE ? OR code LIKE ?)", like, like)
	}

	err := q.Order("name ASC").Limit(500).Find(&items).Error

	return items, err
}

// Parts 供应商角色只能看到本供应商的零件号清单
func (s *MasterDataService) Parts(user *model.User, supplierID, keyword string) ([]model.SupplierPart, error) {
	items := make([]model.SupplierPart, 0)

	q := s.DB.Model(&model.SupplierPart{})

	if user.Role == auth.RoleSupplier {
		if user.SupplierID == nil {
			return items, nil
		}
		q = q.Where("supplier_id = ?", *user.SupplierID)
	} else if supplierID != "" {
		q = q.Where("supplier_id = ?", supplierID)
	}

	if keyword = strings.TrimSpace(keyword); keyword != "" {
		like := "%" + escapeLike(keyword) + "%"
		q = q.Where("(part_no LIKE ? OR part_name LIKE ?)", like, like)
	}

	err := q.Order("part_no ASC").Limit(1000).Find(&items).Error

	return items, err
}

func (s *MasterDataService) SupplierByID(id string) (*model.Supplier, error) {
	var supplier model.Supplier

	if err := s.DB.First(&supplier, "id = ?", id).Error; err != nil {
		return nil, resp.Biz("供应商不存在或已被删除")
	}

	return &supplier, nil
}

type ContactService struct {
	DB *gorm.DB
}

func NewContactService(db *gorm.DB) *ContactService {
	return &ContactService{DB: db}
}

// List 供应商固定返回本供应商；管理员可按供应商过滤或查看全部
func (s *ContactService) List(user *model.User, supplierID string) ([]dto.ContactView, error) {
	if user.Role != auth.RoleSupplier && user.Role != auth.RoleAdmin {
		return nil, resp.Forbidden("无权访问供应商联系人")
	}

	q := s.DB.Model(&model.SupplierContact{})

	if user.Role == auth.RoleSupplier {
		if user.SupplierID == nil {
			return []dto.ContactView{}, nil
		}
		q = q.Where("supplier_id = ?", *user.SupplierID)
	} else if supplierID != "" {
		q = q.Where("supplier_id = ?", supplierID)
	}

	var contacts []model.SupplierContact
	if err := q.Order("is_default DESC, created_at ASC").Limit(500).Find(&contacts).Error; err != nil {
		return nil, err
	}

	ids := make([]string, 0, len(contacts))

	for _, item := range contacts {
		ids = append(ids, item.SupplierID)
	}

	names := make(map[string]string, len(ids))

	if len(ids) > 0 {
		var suppliers []model.Supplier
		if err := s.DB.Where("id IN ?", ids).Find(&suppliers).Error; err == nil {
			for _, item := range suppliers {
				names[item.ID] = item.Name
			}
		}
	}

	views := make([]dto.ContactView, 0, len(contacts))

	for _, item := range contacts {
		views = append(views, dto.ContactView{SupplierContact: item, SupplierName: names[item.SupplierID]})
	}

	return views, nil
}

func (s *ContactService) resolveSupplierID(user *model.User, inputSupplierID string) (string, error) {
	if user.Role == auth.RoleSupplier {
		if user.SupplierID == nil {
			return "", resp.Biz("当前账号未关联供应商")
		}

		return *user.SupplierID, nil
	}

	if user.Role != auth.RoleAdmin {
		return "", resp.Forbidden("无权维护供应商联系人")
	}

	if strings.TrimSpace(inputSupplierID) == "" {
		return "", resp.Biz("请选择供应商")
	}

	var supplier model.Supplier
	if err := s.DB.First(&supplier, "id = ?", inputSupplierID).Error; err != nil {
		return "", resp.Biz("供应商不存在或已被删除")
	}

	return supplier.ID, nil
}

func (s *ContactService) Create(user *model.User, input dto.ContactInput) (*model.SupplierContact, error) {
	supplierID, err := s.resolveSupplierID(user, input.SupplierID)
	if err != nil {
		return nil, err
	}

	name := strings.TrimSpace(input.Name)
	if name == "" {
		return nil, resp.Biz("联系人姓名不能为空")
	}

	if err := validateEmail(input.Email); err != nil {
		return nil, err
	}

	contact := model.SupplierContact{
		ID:         ulidx.New(),
		SupplierID: supplierID,
		Name:       name,
		Phone:      strings.TrimSpace(input.Phone),
		Email:      strings.TrimSpace(input.Email),
		Fax:        strings.TrimSpace(input.Fax),
		IsDefault:  0,
	}

	err = s.DB.Transaction(func(tx *gorm.DB) error {
		var total int64
		if err := tx.Model(&model.SupplierContact{}).Where("supplier_id = ?", supplierID).Count(&total).Error; err != nil {
			return err
		}

		isDefault := total == 0 || (input.IsDefault != nil && *input.IsDefault == 1)

		if isDefault {
			if err := tx.Model(&model.SupplierContact{}).Where("supplier_id = ?", supplierID).
				Update("is_default", 0).Error; err != nil {
				return err
			}
			contact.IsDefault = 1
		}

		return tx.Create(&contact).Error
	})

	if err != nil {
		return nil, err
	}

	return &contact, nil
}

func (s *ContactService) Update(user *model.User, id string, input dto.ContactInput) (*model.SupplierContact, error) {
	var contact model.SupplierContact
	if err := s.DB.First(&contact, "id = ?", id).Error; err != nil {
		return nil, resp.ErrNotFound
	}

	if err := s.assertOwnership(user, &contact); err != nil {
		return nil, err
	}

	name := strings.TrimSpace(input.Name)
	if name == "" {
		return nil, resp.Biz("联系人姓名不能为空")
	}

	if err := validateEmail(input.Email); err != nil {
		return nil, err
	}

	err := s.DB.Transaction(func(tx *gorm.DB) error {
		updates := map[string]any{
			"name":  name,
			"phone": strings.TrimSpace(input.Phone),
			"email": strings.TrimSpace(input.Email),
			"fax":   strings.TrimSpace(input.Fax),
		}

		if input.IsDefault != nil && *input.IsDefault == 1 {
			if err := tx.Model(&model.SupplierContact{}).Where("supplier_id = ?", contact.SupplierID).
				Update("is_default", 0).Error; err != nil {
				return err
			}
			updates["is_default"] = 1
		}

		return tx.Model(&contact).Updates(updates).Error
	})

	if err != nil {
		return nil, err
	}

	if err := s.DB.First(&contact, "id = ?", id).Error; err != nil {
		return nil, err
	}

	return &contact, nil
}

func (s *ContactService) Delete(user *model.User, id string) error {
	var contact model.SupplierContact
	if err := s.DB.First(&contact, "id = ?", id).Error; err != nil {
		return resp.ErrNotFound
	}

	if err := s.assertOwnership(user, &contact); err != nil {
		return err
	}

	return s.DB.Transaction(func(tx *gorm.DB) error {
		if err := tx.Delete(&contact).Error; err != nil {
			return err
		}

		// 每个供应商始终保留一条默认联系人
		var remaining int64
		if err := tx.Model(&model.SupplierContact{}).
			Where("supplier_id = ? AND is_default = 1", contact.SupplierID).Count(&remaining).Error; err != nil {
			return err
		}

		if remaining == 0 {
			var earliest model.SupplierContact
			if err := tx.Where("supplier_id = ?", contact.SupplierID).Order("created_at ASC").First(&earliest).Error; err == nil {
				if err := tx.Model(&earliest).Update("is_default", 1).Error; err != nil {
					return err
				}
			}
		}

		return nil
	})
}

func (s *ContactService) assertOwnership(user *model.User, contact *model.SupplierContact) error {
	if user.Role == auth.RoleAdmin {
		return nil
	}

	if user.Role != auth.RoleSupplier || user.SupplierID == nil || *user.SupplierID != contact.SupplierID {
		return resp.Forbidden("无权操作其他供应商的联系人")
	}

	return nil
}

func validateEmail(email string) error {
	email = strings.TrimSpace(email)

	if email == "" {
		return nil
	}

	if !strings.Contains(email, "@") || strings.ContainsAny(email, " \t\r\n") || len(email) > 100 {
		return resp.Biz("邮箱格式不正确")
	}

	return nil
}
