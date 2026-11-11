package service

import (
	"fmt"
	"strings"
	"time"

	"gorm.io/gorm"

	"packsubmit/internal/dto"
	"packsubmit/internal/model"
	"packsubmit/internal/pkg/resp"
	"packsubmit/internal/pkg/ulidx"
)

func (s *AdminService) ListParts(supplierID, keyword string, page Page) ([]model.SupplierPart, int64, error) {
	q := s.DB.Model(&model.SupplierPart{})

	if supplierID = strings.TrimSpace(supplierID); supplierID != "" {
		q = q.Where("supplier_id = ?", supplierID)
	}

	if keyword = strings.TrimSpace(keyword); keyword != "" {
		like := likePattern(keyword)
		q = q.Where("(part_no LIKE ? OR part_name LIKE ? OR project_name LIKE ?)", like, like, like)
	}

	var total int64
	if err := q.Count(&total).Error; err != nil {
		return nil, 0, err
	}

	_, size, offset := normalizePage(page)

	var items []model.SupplierPart
	err := q.Order("supplier_name ASC, part_no ASC").Offset(offset).Limit(size).Find(&items).Error

	return items, total, err
}

// ImportParts Excel 列：供应商名称、项目名称、零件号、零件名称
// 按「供应商 + 零件号」upsert，并在该零件无有效方案时自动生成草稿（受控建单）
func (s *AdminService) ImportParts(operator *model.User, rows [][]string) (*dto.ImportResult, error) {
	result := &dto.ImportResult{Errors: []string{}}
	seen := make(map[string]struct{}, len(rows))

	for index := 1; index < len(rows); index++ {
		line := index + 1
		supplierName := rowValue(rows[index], 0)
		projectName := rowValue(rows[index], 1)
		partNo := rowValue(rows[index], 2)
		partName := rowValue(rows[index], 3)

		if supplierName == "" || partNo == "" {
			result.Errors = append(result.Errors, importError(line, "供应商名称与零件号不能为空"))
			continue
		}

		var supplier model.Supplier
		if err := s.DB.First(&supplier, "name = ?", supplierName).Error; err != nil {
			result.Errors = append(result.Errors, importError(line, "供应商「"+supplierName+"」不存在"))
			continue
		}

		key := supplier.ID + "|" + partNo
		if _, ok := seen[key]; ok {
			result.Errors = append(result.Errors, importError(line, "文件内存在重复的供应商 + 零件号"))
			continue
		}
		seen[key] = struct{}{}

		var existing model.SupplierPart
		err := s.DB.Where("supplier_id = ? AND part_no = ?", supplier.ID, partNo).First(&existing).Error
		exists := err == nil

		err = s.DB.Transaction(func(tx *gorm.DB) error {
			var part model.SupplierPart

			if exists {
				part = existing

				if part.ProjectName == projectName && part.PartName == partName {
					result.Skipped++
				} else {
					changes := diffContent([][3]string{
						{"项目名称", part.ProjectName, projectName},
						{"零件名称", part.PartName, partName},
					})

					if err := tx.Model(&part).Updates(map[string]any{
						"project_name": projectName,
						"part_name":    partName,
					}).Error; err != nil {
						return err
					}

					part.ProjectName, part.PartName = projectName, partName

					if err := s.recordPart(tx, &part, operator.ID, "part_update", "导入修改零件号信息", changes); err != nil {
						return err
					}

					if err := s.syncPartToProposals(tx, part); err != nil {
						return err
					}

					result.Updated++
				}
			} else {
				part = model.SupplierPart{
					ID:           ulidx.New(),
					SupplierID:   supplier.ID,
					SupplierName: supplier.Name,
					ProjectName:  projectName,
					PartNo:       partNo,
					PartName:     partName,
				}

				if err := tx.Create(&part).Error; err != nil {
					return err
				}

				if err := s.recordPart(tx, &part, operator.ID, "part_create", "导入新增零件号",
					"新增零件号："+part.PartNo+"（"+part.PartName+"）"); err != nil {
					return err
				}

				result.Created++
			}

			drafted, err := s.ensureDraft(tx, part, operator)
			if err != nil {
				return err
			}

			if drafted {
				result.Drafts++
			}

			return nil
		})

		if err != nil {
			result.Errors = append(result.Errors, importError(line, err.Error()))
		}
	}

	return result, nil
}

// ensureDraft 该「供应商 + 零件号」无有效方案（草稿~已归档）时自动生成草稿
func (s *AdminService) ensureDraft(tx *gorm.DB, part model.SupplierPart, operator *model.User) (bool, error) {
	var valid int64

	if err := tx.Model(&model.Proposal{}).
		Where("supplier_id = ? AND part_no = ? AND status IN ?", part.SupplierID, part.PartNo,
			[]int8{model.StatusDraft, model.StatusPending, model.StatusApproved, model.StatusRejected, model.StatusArchived}).
		Count(&valid).Error; err != nil {
		return false, err
	}

	if valid > 0 {
		return false, nil
	}

	var supplier model.Supplier
	if err := tx.First(&supplier, "id = ?", part.SupplierID).Error; err != nil {
		return false, resp.Biz("供应商不存在或已被删除")
	}

	var contact model.SupplierContact
	_ = tx.Where("supplier_id = ?", supplier.ID).
		Order("is_default DESC, created_at ASC").First(&contact).Error

	proposalNo, err := s.Proposals.generateNo(tx)
	if err != nil {
		return false, err
	}

	proposal := model.Proposal{
		ID:              ulidx.New(),
		ProposalNo:      proposalNo,
		Version:         1,
		Status:          model.StatusDraft,
		SupplierID:      supplier.ID,
		SupplierName:    supplier.Name,
		CreatedBy:       operator.ID,
		WriteDate:       time.Now().Format("2006-01-02"),
		ProjectModel:    part.ProjectName,
		PartNo:          part.PartNo,
		PartName:        part.PartName,
		Spec:            "0*0*0mm",
		SupplierCode:    supplier.Code,
		SupplierAddress: supplier.Address,
		Contact:         contact.Name,
		Phone:           contact.Phone,
		SupplierEmail:   contact.Email,
		SupplierFax:     contact.Fax,
	}

	if err := tx.Create(&proposal).Error; err != nil {
		return false, err
	}

	if err := Record(tx, AuditEntry{
		ProposalID: &proposal.ID, UserID: operator.ID, Action: "create",
		ToStatus: Int8(model.StatusDraft), Version: 1,
		Content: "导入零件号自动生成草稿：" + part.PartNo,
	}); err != nil {
		return false, err
	}

	return true, nil
}

// PreviewBatchParts 批量修改预览：只读，不写库
func (s *AdminService) PreviewBatchParts(rows [][]string) ([]dto.BatchPreviewItem, error) {
	items := make([]dto.BatchPreviewItem, 0, len(rows))

	for index := 1; index < len(rows); index++ {
		supplierName := rowValue(rows[index], 0)
		projectName := rowValue(rows[index], 1)
		partNo := rowValue(rows[index], 2)
		partName := rowValue(rows[index], 3)

		item := dto.BatchPreviewItem{
			SupplierName:   supplierName,
			PartNo:         partNo,
			ProjectName:    projectName,
			PartName:       partName,
			NewProjectName: projectName,
			NewPartName:    partName,
		}

		if supplierName == "" || partNo == "" {
			item.Status = "error"
			item.Message = "供应商名称与零件号不能为空"
			items = append(items, item)
			continue
		}

		var supplier model.Supplier
		if err := s.DB.First(&supplier, "name = ?", supplierName).Error; err != nil {
			item.Status = "error"
			item.Message = "供应商不存在"
			items = append(items, item)
			continue
		}

		var part model.SupplierPart
		if err := s.DB.Where("supplier_id = ? AND part_no = ?", supplier.ID, partNo).First(&part).Error; err != nil {
			item.Status = "missing"
			item.Message = "零件号不存在"
			items = append(items, item)
			continue
		}

		item.ID = part.ID
		item.ProjectName = part.ProjectName
		item.PartName = part.PartName

		if part.ProjectName == projectName && part.PartName == partName {
			item.Status = "same"
			item.Message = "无变化"
		} else {
			item.Status = "update"
			item.Message = "可修改"
		}

		items = append(items, item)
	}

	return items, nil
}

// BatchUpdateParts 批量修改提交：只更新已存在的「供应商 + 零件号」
func (s *AdminService) BatchUpdateParts(operator *model.User, items []dto.BatchPartItem) (int, error) {
	updated := 0

	err := s.DB.Transaction(func(tx *gorm.DB) error {
		for _, item := range items {
			var supplier model.Supplier
			if err := tx.First(&supplier, "name = ?", item.SupplierName).Error; err != nil {
				continue
			}

			var part model.SupplierPart
			if err := tx.Where("supplier_id = ? AND part_no = ?", supplier.ID, item.PartNo).
				First(&part).Error; err != nil {
				continue
			}

			projectName := strings.TrimSpace(item.ProjectName)
			partName := strings.TrimSpace(item.PartName)

			if projectName == "" {
				projectName = part.ProjectName
			}
			if partName == "" {
				partName = part.PartName
			}

			if part.ProjectName == projectName && part.PartName == partName {
				continue
			}

			changes := diffContent([][3]string{
				{"项目名称", part.ProjectName, projectName},
				{"零件名称", part.PartName, partName},
			})

			if err := tx.Model(&part).Updates(map[string]any{
				"project_name": projectName,
				"part_name":    partName,
			}).Error; err != nil {
				return err
			}

			part.ProjectName, part.PartName = projectName, partName

			if err := s.recordPart(tx, &part, operator.ID, "part_update", "批量修改零件号", changes); err != nil {
				return err
			}

			if err := s.syncPartToProposals(tx, part); err != nil {
				return err
			}

			updated++
		}

		return nil
	})

	return updated, err
}

func (s *AdminService) UpdatePart(operator *model.User, id string, input dto.PartInput) (*model.SupplierPart, error) {
	var part model.SupplierPart

	if err := s.DB.First(&part, "id = ?", id).Error; err != nil {
		return nil, resp.ErrNotFound
	}

	projectName := strings.TrimSpace(input.ProjectName)
	partName := strings.TrimSpace(input.PartName)

	if projectName == part.ProjectName && partName == part.PartName {
		return nil, resp.Biz("没有需要修改的内容")
	}

	err := s.DB.Transaction(func(tx *gorm.DB) error {
		changes := diffContent([][3]string{
			{"项目名称", part.ProjectName, projectName},
			{"零件名称", part.PartName, partName},
		})

		if err := tx.Model(&part).Updates(map[string]any{
			"project_name": projectName,
			"part_name":    partName,
		}).Error; err != nil {
			return err
		}

		part.ProjectName, part.PartName = projectName, partName

		if err := s.recordPart(tx, &part, operator.ID, "part_update", "手工修改零件号", changes); err != nil {
			return err
		}

		return s.syncPartToProposals(tx, part)
	})

	if err != nil {
		return nil, err
	}

	return &part, nil
}

func (s *AdminService) DeletePart(operator *model.User, id string) error {
	var part model.SupplierPart

	if err := s.DB.First(&part, "id = ?", id).Error; err != nil {
		return resp.ErrNotFound
	}

	return s.DB.Transaction(func(tx *gorm.DB) error {
		alias := fmt.Sprintf("%s#%s", part.PartNo, shortID(part.ID))

		if err := tx.Model(&part).Update("part_no", alias).Error; err != nil {
			return err
		}

		if err := tx.Delete(&part).Error; err != nil {
			return err
		}

		// 删除零件号不级联方案，只记录审计
		return Record(tx, AuditEntry{
			UserID: operator.ID, Action: "part_delete", Version: 1,
			Content: "删除零件号：" + part.SupplierName + " / " + part.PartNo,
		})
	})
}

func (s *AdminService) PartLogs(id string) ([]model.PartLog, error) {
	var part model.SupplierPart

	if err := s.DB.Unscoped().First(&part, "id = ?", id).Error; err != nil {
		return nil, resp.ErrNotFound
	}

	logs := make([]model.PartLog, 0)

	err := s.DB.Where("supplier_id = ? AND part_no = ?", part.SupplierID, part.PartNo).
		Order("created_at DESC").Limit(100).Find(&logs).Error

	return logs, err
}

// syncPartToProposals 零件名称 / 项目名称变更同步到该零件下所有状态的方案
func (s *AdminService) syncPartToProposals(tx *gorm.DB, part model.SupplierPart) error {
	return tx.Model(&model.Proposal{}).
		Where("supplier_id = ? AND part_no = ?", part.SupplierID, part.PartNo).
		Updates(map[string]any{
			"part_name":     part.PartName,
			"project_model": part.ProjectName,
		}).Error
}
