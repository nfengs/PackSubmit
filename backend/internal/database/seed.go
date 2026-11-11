package database

import (
	"fmt"
	"time"

	"gorm.io/gorm"

	"packsubmit/internal/model"
	"packsubmit/internal/pkg/auth"
	"packsubmit/internal/pkg/ulidx"
)

// Seed 初始化演示数据（幂等：已有用户时直接跳过）
func Seed(db *gorm.DB) error {
	var count int64
	if err := db.Model(&model.User{}).Count(&count).Error; err != nil {
		return err
	}

	if count > 0 {
		return nil
	}

	return db.Transaction(func(tx *gorm.DB) error {
		suppliers := []model.Supplier{
			{ID: ulidx.New(), Name: "供应商A", Code: "SUP-A001", Address: "江苏省苏州市工业园区新平街 8 号", Status: 1},
			{ID: ulidx.New(), Name: "供应商B", Code: "SUP-B002", Address: "浙江省宁波市北仑区大港工业城 18 号", Status: 1},
			{ID: ulidx.New(), Name: "供应商C", Code: "SUP-C003", Address: "安徽省合肥市经开区繁华大道 28 号", Status: 1},
		}

		if err := tx.Create(&suppliers).Error; err != nil {
			return err
		}

		customers := []model.Customer{
			{ID: ulidx.New(), Name: "客户A", Address: "上海市浦东新区金桥路 100 号", Contact: "李经理", Phone: "021-60000001", Email: "cust_a@example.com", Fax: "021-60000009", Status: 1},
			{ID: ulidx.New(), Name: "客户B", Address: "上海市嘉定区安亭镇园大路 200 号", Contact: "王经理", Phone: "021-60000002", Email: "cust_b@example.com", Fax: "021-60000008", Status: 1},
			{ID: ulidx.New(), Name: "客户C", Address: "江苏省南京市江宁区秣周东路 300 号", Contact: "赵经理", Phone: "025-60000003", Email: "cust_c@example.com", Fax: "025-60000007", Status: 1},
		}

		if err := tx.Create(&customers).Error; err != nil {
			return err
		}

		contacts := []model.SupplierContact{
			{ID: ulidx.New(), SupplierID: suppliers[0].ID, Name: "张三", Phone: "0512-60000001", Email: "sup_a@example.com", Fax: "0512-60000009", IsDefault: 1},
			{ID: ulidx.New(), SupplierID: suppliers[0].ID, Name: "李助理", Phone: "0512-60000011", Email: "sup_a_assist@example.com", Fax: "0512-60000019"},
			{ID: ulidx.New(), SupplierID: suppliers[1].ID, Name: "李四", Phone: "0574-60000002", Email: "sup_b@example.com", IsDefault: 1},
			{ID: ulidx.New(), SupplierID: suppliers[2].ID, Name: "王五", Phone: "0551-60000003", Email: "sup_c@example.com", IsDefault: 1},
		}

		if err := tx.Create(&contacts).Error; err != nil {
			return err
		}

		parts := []model.SupplierPart{
			{ID: ulidx.New(), SupplierID: suppliers[0].ID, SupplierName: suppliers[0].Name, ProjectName: "新能源重卡 X1", PartNo: "PN-2026-0001", PartName: "精密减速机壳体"},
			{ID: ulidx.New(), SupplierID: suppliers[0].ID, SupplierName: suppliers[0].Name, ProjectName: "电驱系统 E2", PartNo: "PN-2026-0002", PartName: "电机定子总成"},
			{ID: ulidx.New(), SupplierID: suppliers[0].ID, SupplierName: suppliers[0].Name, ProjectName: "智能座舱 C3", PartNo: "PN-2026-0003", PartName: "电控箱体"},
			{ID: ulidx.New(), SupplierID: suppliers[1].ID, SupplierName: suppliers[1].Name, ProjectName: "新能源重卡 X1", PartNo: "PN-B-1001", PartName: "电机端盖"},
		}

		if err := tx.Create(&parts).Error; err != nil {
			return err
		}

		dicts := []model.Dict{
			{ID: ulidx.New(), DictType: "customer", DictLabel: "客户A", DictValue: "cust_a", Sort: 10, Status: 1},
			{ID: ulidx.New(), DictType: "customer", DictLabel: "客户B", DictValue: "cust_b", Sort: 20, Status: 1},
			{ID: ulidx.New(), DictType: "customer", DictLabel: "客户C", DictValue: "cust_c", Sort: 30, Status: 1},
		}

		if err := tx.Create(&dicts).Error; err != nil {
			return err
		}

		accounts := []struct {
			username string
			password string
			realName string
			role     int8
			dept     string
			supplier *string
		}{
			{"admin", "admin123", "系统管理员", auth.RoleAdmin, "信息部", nil},
			{"reviewer", "reviewer123", "包装评审员", auth.RoleReviewer, "包装工程部", nil},
			{"submitter", "submitter123", "供应商工程师", auth.RoleSupplier, suppliers[0].Name, &suppliers[0].ID},
			{"viewer", "viewer1234", "审计查看员", auth.RoleViewer, "审计部", nil},
		}

		for _, item := range accounts {
			hash, err := auth.HashPassword(item.password)
			if err != nil {
				return err
			}

			user := model.User{
				ID:           ulidx.New(),
				Username:     item.username,
				PasswordHash: hash,
				RealName:     item.realName,
				Role:         item.role,
				Dept:         item.dept,
				SupplierID:   item.supplier,
				Status:       model.UserEnabled,
				TokenVersion: 1,
			}

			if err := tx.Create(&user).Error; err != nil {
				return err
			}
		}

		return seedProposals(tx, suppliers, customers, contacts, parts)
	})
}

func seedProposals(tx *gorm.DB, suppliers []model.Supplier, customers []model.Customer, contacts []model.SupplierContact, parts []model.SupplierPart) error {
	var submitter model.User
	if err := tx.Where("username = ?", "submitter").First(&submitter).Error; err != nil {
		return err
	}

	now := time.Now()
	today := now.Format("2006-01-02")
	prefix := "PS" + now.Format("20060102")

	base := func(seq int, status int8, part model.SupplierPart, customer model.Customer, contact model.SupplierContact) model.Proposal {
		return model.Proposal{
			ID:               ulidx.New(),
			ProposalNo:       fmt.Sprintf("%s%04d", prefix, seq),
			Version:          1,
			Status:           status,
			SupplierID:       suppliers[0].ID,
			SupplierName:     suppliers[0].Name,
			CreatedBy:        submitter.ID,
			WriteDate:        today,
			ProjectModel:     part.ProjectName,
			PartNo:           part.PartNo,
			PartName:         part.PartName,
			PartMaterial:     "铝合金",
			Spec:             "1200*800*900mm",
			Quantity:         10,
			CustomerID:       &customer.ID,
			CustomerName:     customer.Name,
			CustomerAddress:  customer.Address,
			CustomerContact:  customer.Contact,
			CustomerPhone:    customer.Phone,
			CustomerEmail:    customer.Email,
			CustomerFax:      customer.Fax,
			SupplierCode:     suppliers[0].Code,
			SupplierAddress:  suppliers[0].Address,
			Contact:          contact.Name,
			Phone:            contact.Phone,
			SupplierEmail:    contact.Email,
			SupplierFax:      contact.Fax,
			PackBoxNo:        "BOX-2026-001",
			PackMaterial:     "carton",
			PartWeight:       12.5,
			PartSizeL:        600,
			PartSizeW:        400,
			PartSizeH:        300,
			BoxSizeL:         1200,
			BoxSizeW:         800,
			BoxSizeH:         900,
			GrossWeight:      16.8,
			Returnable:       int8Ptr(1),
			DunnageReturnable: int8Ptr(0),
			WithPallet:       int8Ptr(1),
			PackingQty:       10,
			Description:      "演示数据",
			PalletSizeL:      1200,
			PalletSizeW:      1000,
			PalletSizeH:      1100,
			PalletLayerQty:   4,
			PalletLayerCount: 3,
			PalletPartsQty:   120,
			PackagingInstruction: "内部 EPE 缓冲，托盘缠绕膜固定",
		}
	}

	pending := base(1, model.StatusPending, parts[0], customers[0], contacts[0])
	pending.SubmitTime = &now

	archived := base(2, model.StatusArchived, parts[1], customers[1], contacts[1])
	archived.SubmitTime = &now
	archived.ReviewTime = &now
	archived.ArchiveTime = &now
	archived.PalletPartsQty = 0
	archived.WithPallet = int8Ptr(0)
	archived.PalletSizeL, archived.PalletSizeW, archived.PalletSizeH = 0, 0, 0
	archived.PalletLayerQty, archived.PalletLayerCount = 0, 0

	draft := base(3, model.StatusDraft, parts[2], customers[2], contacts[0])
	draft.PackBoxNo = ""
	draft.PartWeight = 0
	draft.GrossWeight = 0
	draft.BoxSizeL, draft.BoxSizeW, draft.BoxSizeH = 0, 0, 0
	draft.Spec = "0*0*0mm"

	if err := tx.Create(&pending).Error; err != nil {
		return err
	}

	if err := tx.Create(&archived).Error; err != nil {
		return err
	}

	if err := tx.Create(&draft).Error; err != nil {
		return err
	}

	logs := []model.AuditLog{
		{ID: ulidx.New(), ProposalID: &pending.ID, UserID: submitter.ID, Action: "create", ActionName: "新增方案", ToStatus: int8Ptr(model.StatusDraft), Version: 1, Content: "导入零件号自动生成草稿", IP: "127.0.0.1"},
		{ID: ulidx.New(), ProposalID: &pending.ID, UserID: submitter.ID, Action: "submit", ActionName: "提交评审", FromStatus: int8Ptr(model.StatusDraft), ToStatus: int8Ptr(model.StatusPending), Version: 1, Content: "第 1 版提交评审", IP: "127.0.0.1"},
	}

	return tx.Create(&logs).Error
}

func int8Ptr(value int8) *int8 { return &value }
