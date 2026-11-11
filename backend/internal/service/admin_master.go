package service

import (
	"fmt"
	"strings"

	"gorm.io/gorm"

	"packsubmit/internal/dto"
	"packsubmit/internal/model"
	"packsubmit/internal/pkg/resp"
	"packsubmit/internal/pkg/ulidx"
)

// ------------------------------------------------------------------
// 客户
// ------------------------------------------------------------------

func (s *AdminService) ListCustomers(keyword string, page Page) ([]model.Customer, int64, error) {
	q := s.DB.Model(&model.Customer{})

	if keyword = strings.TrimSpace(keyword); keyword != "" {
		like := likePattern(keyword)
		q = q.Where("(name LIKE ? OR contact LIKE ?)", like, like)
	}

	var total int64
	if err := q.Count(&total).Error; err != nil {
		return nil, 0, err
	}

	_, size, offset := normalizePage(page)

	var items []model.Customer
	err := q.Order("created_at DESC").Offset(offset).Limit(size).Find(&items).Error

	return items, total, err
}

func (s *AdminService) CreateCustomer(operator *model.User, input dto.CustomerInput) (*model.Customer, error) {
	name := strings.TrimSpace(input.Name)

	var created model.Customer

	err := s.DB.Transaction(func(tx *gorm.DB) error {
		var exists int64
		if err := tx.Model(&model.Customer{}).Where("name = ?", name).Count(&exists).Error; err != nil {
			return err
		}

		if exists > 0 {
			return resp.Biz("客户名称已存在")
		}

		customer := model.Customer{
			ID:      ulidx.New(),
			Name:    name,
			Address: strings.TrimSpace(input.Address),
			Contact: strings.TrimSpace(input.Contact),
			Phone:   strings.TrimSpace(input.Phone),
			Email:   strings.TrimSpace(input.Email),
			Fax:     strings.TrimSpace(input.Fax),
			Status:  statusOf(input.Status, 1),
		}

		if err := tx.Create(&customer).Error; err != nil {
			return err
		}

		created = customer

		return s.recordMaster(tx, "customer", customer.ID, customer.Name, operator.ID, "customer_create", "新增客户",
			"首次录入客户："+customer.Name)
	})

	if err != nil {
		return nil, err
	}

	return &created, nil
}

func (s *AdminService) UpdateCustomer(operator *model.User, id string, input dto.CustomerInput) (*model.Customer, error) {
	var customer model.Customer

	if err := s.DB.First(&customer, "id = ?", id).Error; err != nil {
		return nil, resp.ErrNotFound
	}

	name := strings.TrimSpace(input.Name)

	err := s.DB.Transaction(func(tx *gorm.DB) error {
		var exists int64
		if err := tx.Model(&model.Customer{}).
			Where("name = ? AND id <> ?", name, id).Count(&exists).Error; err != nil {
			return err
		}

		if exists > 0 {
			return resp.Biz("客户名称已存在")
		}

		changes := diffContent([][3]string{
			{"客户名称", customer.Name, name},
			{"地址", customer.Address, strings.TrimSpace(input.Address)},
			{"联系人", customer.Contact, strings.TrimSpace(input.Contact)},
			{"电话", customer.Phone, strings.TrimSpace(input.Phone)},
			{"邮箱", customer.Email, strings.TrimSpace(input.Email)},
			{"传真", customer.Fax, strings.TrimSpace(input.Fax)},
		})

		updates := map[string]any{
			"name":    name,
			"address": strings.TrimSpace(input.Address),
			"contact": strings.TrimSpace(input.Contact),
			"phone":   strings.TrimSpace(input.Phone),
			"email":   strings.TrimSpace(input.Email),
			"fax":     strings.TrimSpace(input.Fax),
		}

		if input.Status != nil {
			updates["status"] = *input.Status
		}

		if err := tx.Model(&customer).Updates(updates).Error; err != nil {
			return err
		}

		if changes == "" {
			changes = "无字段变化"
		}

		return s.recordMaster(tx, "customer", customer.ID, name, operator.ID, "customer_update", "编辑客户", changes)
	})

	if err != nil {
		return nil, err
	}

	if err := s.DB.First(&customer, "id = ?", id).Error; err != nil {
		return nil, err
	}

	return &customer, nil
}

func (s *AdminService) DeleteCustomer(operator *model.User, id string) error {
	var customer model.Customer

	if err := s.DB.First(&customer, "id = ?", id).Error; err != nil {
		return resp.ErrNotFound
	}

	var used int64
	if err := s.DB.Model(&model.Proposal{}).Where("customer_id = ?", id).Count(&used).Error; err != nil {
		return err
	}

	if used > 0 {
		return resp.Biz("该客户已被方案引用，不能删除")
	}

	return s.DB.Transaction(func(tx *gorm.DB) error {
		// 改名释放唯一索引，便于重新创建同名客户
		alias := fmt.Sprintf("%s#%s", customer.Name, shortID(customer.ID))

		if err := tx.Model(&customer).Update("name", alias).Error; err != nil {
			return err
		}

		if err := tx.Delete(&customer).Error; err != nil {
			return err
		}

		return s.recordMaster(tx, "customer", customer.ID, customer.Name, operator.ID, "customer_delete", "删除客户",
			"删除客户："+customer.Name)
	})
}

func (s *AdminService) CheckCustomerName(name, excludeID string) (bool, error) {
	var count int64

	q := s.DB.Model(&model.Customer{}).Where("name = ?", strings.TrimSpace(name))

	if excludeID != "" {
		q = q.Where("id <> ?", excludeID)
	}

	err := q.Count(&count).Error

	return count > 0, err
}

func (s *AdminService) CustomerLogs(id string) ([]model.MasterLog, error) {
	logs := make([]model.MasterLog, 0)

	err := s.DB.Where("biz_type = ? AND target_id = ?", "customer", id).
		Order("created_at DESC").Limit(100).Find(&logs).Error

	return logs, err
}

// ------------------------------------------------------------------
// 供应商
// ------------------------------------------------------------------

func (s *AdminService) ListSuppliers(keyword string, page Page) ([]model.Supplier, int64, error) {
	q := s.DB.Model(&model.Supplier{})

	if keyword = strings.TrimSpace(keyword); keyword != "" {
		like := likePattern(keyword)
		q = q.Where("(name LIKE ? OR code LIKE ?)", like, like)
	}

	var total int64
	if err := q.Count(&total).Error; err != nil {
		return nil, 0, err
	}

	_, size, offset := normalizePage(page)

	var items []model.Supplier
	err := q.Order("created_at DESC").Offset(offset).Limit(size).Find(&items).Error

	return items, total, err
}

func (s *AdminService) CreateSupplier(operator *model.User, input dto.SupplierInput) (*model.Supplier, error) {
	name := strings.TrimSpace(input.Name)

	var created model.Supplier

	err := s.DB.Transaction(func(tx *gorm.DB) error {
		var exists int64
		if err := tx.Model(&model.Supplier{}).Where("name = ?", name).Count(&exists).Error; err != nil {
			return err
		}

		if exists > 0 {
			return resp.Biz("供应商名称已存在")
		}

		supplier := model.Supplier{
			ID:      ulidx.New(),
			Name:    name,
			Code:    strings.TrimSpace(input.Code),
			Address: strings.TrimSpace(input.Address),
			Status:  statusOf(input.Status, 1),
		}

		if err := tx.Create(&supplier).Error; err != nil {
			return err
		}

		created = supplier

		return s.recordMaster(tx, "supplier", supplier.ID, supplier.Name, operator.ID, "supplier_create", "新增供应商",
			"首次录入供应商："+supplier.Name)
	})

	if err != nil {
		return nil, err
	}

	return &created, nil
}

// UpdateSupplier 改名时同步方案归属名称、零件号清单名称与账号部门
func (s *AdminService) UpdateSupplier(operator *model.User, id string, input dto.SupplierInput) (*model.Supplier, error) {
	var supplier model.Supplier

	if err := s.DB.First(&supplier, "id = ?", id).Error; err != nil {
		return nil, resp.ErrNotFound
	}

	name := strings.TrimSpace(input.Name)

	err := s.DB.Transaction(func(tx *gorm.DB) error {
		var exists int64
		if err := tx.Model(&model.Supplier{}).
			Where("name = ? AND id <> ?", name, id).Count(&exists).Error; err != nil {
			return err
		}

		if exists > 0 {
			return resp.Biz("供应商名称已存在")
		}

		changes := diffContent([][3]string{
			{"供应商名称", supplier.Name, name},
			{"供应商代码", supplier.Code, strings.TrimSpace(input.Code)},
			{"地址", supplier.Address, strings.TrimSpace(input.Address)},
		})

		updates := map[string]any{
			"name":    name,
			"code":    strings.TrimSpace(input.Code),
			"address": strings.TrimSpace(input.Address),
		}

		if input.Status != nil {
			updates["status"] = *input.Status
		}

		if err := tx.Model(&supplier).Updates(updates).Error; err != nil {
			return err
		}

		if supplier.Name != name {
			// 方案与零件号以供应商名称为冗余快照，必须同步，否则归属断裂
			if err := tx.Model(&model.Proposal{}).Where("supplier_id = ?", id).
				Update("supplier_name", name).Error; err != nil {
				return err
			}

			if err := tx.Model(&model.SupplierPart{}).Where("supplier_id = ?", id).
				Update("supplier_name", name).Error; err != nil {
				return err
			}

			if err := tx.Model(&model.User{}).Where("supplier_id = ?", id).
				Update("dept", name).Error; err != nil {
				return err
			}
		}

		if changes == "" {
			changes = "无字段变化"
		}

		return s.recordMaster(tx, "supplier", supplier.ID, name, operator.ID, "supplier_update", "编辑供应商", changes)
	})

	if err != nil {
		return nil, err
	}

	if err := s.DB.First(&supplier, "id = ?", id).Error; err != nil {
		return nil, err
	}

	return &supplier, nil
}

func (s *AdminService) DeleteSupplier(operator *model.User, id string) error {
	var supplier model.Supplier

	if err := s.DB.First(&supplier, "id = ?", id).Error; err != nil {
		return resp.ErrNotFound
	}

	var usedByProposal, usedByUser int64

	if err := s.DB.Model(&model.Proposal{}).Where("supplier_id = ?", id).Count(&usedByProposal).Error; err != nil {
		return err
	}

	if err := s.DB.Model(&model.User{}).Where("supplier_id = ?", id).Count(&usedByUser).Error; err != nil {
		return err
	}

	if usedByProposal > 0 {
		return resp.Biz("该供应商已被方案引用，不能删除")
	}

	if usedByUser > 0 {
		return resp.Biz("该供应商下仍有账号在用，不能删除")
	}

	return s.DB.Transaction(func(tx *gorm.DB) error {
		alias := fmt.Sprintf("%s#%s", supplier.Name, shortID(supplier.ID))

		// 名称改写后再软删，释放唯一索引，允许重新创建同名供应商
		if err := tx.Model(&supplier).Update("name", alias).Error; err != nil {
			return err
		}

		if err := tx.Delete(&supplier).Error; err != nil {
			return err
		}

		// 清理联系人，避免已删除供应商的数据继续被拉取
		if err := tx.Where("supplier_id = ?", id).Delete(&model.SupplierContact{}).Error; err != nil {
			return err
		}

		// 零件号软删并改名释放联合唯一键
		var parts []model.SupplierPart
		if err := tx.Where("supplier_id = ?", id).Find(&parts).Error; err != nil {
			return err
		}

		for _, part := range parts {
			partAlias := fmt.Sprintf("%s#%s", part.PartNo, shortID(part.ID))

			if err := tx.Model(&part).Update("part_no", partAlias).Error; err != nil {
				return err
			}

			if err := tx.Delete(&part).Error; err != nil {
				return err
			}
		}

		return s.recordMaster(tx, "supplier", supplier.ID, supplier.Name, operator.ID, "supplier_delete", "删除供应商",
			"删除供应商："+supplier.Name)
	})
}

func (s *AdminService) CheckSupplierName(name, excludeID string) (bool, error) {
	var count int64

	q := s.DB.Model(&model.Supplier{}).Where("name = ?", strings.TrimSpace(name))

	if excludeID != "" {
		q = q.Where("id <> ?", excludeID)
	}

	err := q.Count(&count).Error

	return count > 0, err
}

func (s *AdminService) SupplierLogs(id string) ([]model.MasterLog, error) {
	logs := make([]model.MasterLog, 0)

	err := s.DB.Where("biz_type = ? AND target_id = ?", "supplier", id).
		Order("created_at DESC").Limit(100).Find(&logs).Error

	return logs, err
}

// ImportSuppliers Excel 列：供应商名称、供应商代码、供应商地址
func (s *AdminService) ImportSuppliers(operator *model.User, rows [][]string) (*dto.ImportResult, error) {
	result := &dto.ImportResult{Errors: []string{}}

	for index := 1; index < len(rows); index++ {
		line := index + 1
		name := rowValue(rows[index], 0)
		code := rowValue(rows[index], 1)
		address := rowValue(rows[index], 2)

		if name == "" {
			result.Errors = append(result.Errors, importError(line, "供应商名称不能为空"))
			continue
		}

		var existing model.Supplier
		exists := s.DB.Where("name = ?", name).First(&existing).Error == nil

		err := s.DB.Transaction(func(tx *gorm.DB) error {
			if exists {
				if err := tx.Model(&existing).Updates(map[string]any{
					"code":    code,
					"address": address,
				}).Error; err != nil {
					return err
				}

				return s.recordMaster(tx, "supplier", existing.ID, existing.Name, operator.ID,
					"supplier_update", "导入更新供应商", "批量导入更新供应商："+existing.Name)
			}

			supplier := model.Supplier{
				ID:      ulidx.New(),
				Name:    name,
				Code:    code,
				Address: address,
				Status:  1,
			}

			if err := tx.Create(&supplier).Error; err != nil {
				return err
			}

			return s.recordMaster(tx, "supplier", supplier.ID, supplier.Name, operator.ID,
				"supplier_create", "导入新增供应商", "批量导入新增供应商："+supplier.Name)
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
