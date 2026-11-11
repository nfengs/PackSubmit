// migrate-legacy 把旧 PHP 版本（pf_* 前缀）的数据迁移到新 schema。
//
// 用法（在 backend/ 目录执行）：
//
//	go run ./cmd/migrate-legacy -config configs/config.yaml -dry-run   # 只统计
//	go run ./cmd/migrate-legacy -config configs/config.yaml            # 实际迁移
//
// 迁移前提：新表为空（已执行 -migrate，未执行 -seed）。旧表与新表必须在同一个库。
package main

import (
	"flag"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"strings"
	"time"

	"gorm.io/gorm"

	"packsubmit/internal/config"
	"packsubmit/internal/database"
	"packsubmit/internal/model"
	"packsubmit/internal/pkg/ulidx"
)

var (
	legacyPrefix  = flag.String("legacy-prefix", "pf_", "旧表前缀")
	legacyStorage = flag.String("legacy-storage", "../storage/attachments", "旧附件目录（Web 根目录之外的 storage/attachments）")
	storageDir    = flag.String("storage", "storage/attachments", "新附件目录")
	dryRun        = flag.Bool("dry-run", false, "只统计不写库")
)

type legacyUser struct {
	ID           int64      `gorm:"column:id"`
	Username     string     `gorm:"column:username"`
	Password     string     `gorm:"column:password"`
	RealName     string     `gorm:"column:real_name"`
	Role         int8       `gorm:"column:role"`
	Dept         string     `gorm:"column:dept"`
	Phone        string     `gorm:"column:phone"`
	Status       int8       `gorm:"column:status"`
	TokenVersion int        `gorm:"column:token_version"`
	CreateTime   *time.Time `gorm:"column:create_time"`
	UpdateTime   *time.Time `gorm:"column:update_time"`
}

type legacyCustomer struct {
	ID         int64      `gorm:"column:id"`
	Name       string     `gorm:"column:name"`
	Address    string     `gorm:"column:address"`
	Contact    string     `gorm:"column:contact"`
	Phone      string     `gorm:"column:phone"`
	Email      string     `gorm:"column:email"`
	Fax        string     `gorm:"column:fax"`
	Status     int8       `gorm:"column:status"`
	CreateTime *time.Time `gorm:"column:create_time"`
	UpdateTime *time.Time `gorm:"column:update_time"`
	DeleteTime *time.Time `gorm:"column:delete_time"`
}

type legacySupplier struct {
	ID         int64      `gorm:"column:id"`
	Name       string     `gorm:"column:name"`
	Code       string     `gorm:"column:code"`
	Address    string     `gorm:"column:address"`
	Status     int8       `gorm:"column:status"`
	CreateTime *time.Time `gorm:"column:create_time"`
	UpdateTime *time.Time `gorm:"column:update_time"`
	DeleteTime *time.Time `gorm:"column:delete_time"`
}

type legacyContact struct {
	ID         int64      `gorm:"column:id"`
	SupplierID int64      `gorm:"column:supplier_id"`
	Name       string     `gorm:"column:name"`
	Phone      string     `gorm:"column:phone"`
	Email      string     `gorm:"column:email"`
	Fax        string     `gorm:"column:fax"`
	IsDefault  int8       `gorm:"column:is_default"`
	CreateTime *time.Time `gorm:"column:create_time"`
	UpdateTime *time.Time `gorm:"column:update_time"`
}

type legacyPart struct {
	ID           int64      `gorm:"column:id"`
	SupplierName string     `gorm:"column:supplier_name"`
	ProjectName  string     `gorm:"column:project_name"`
	PartNo       string     `gorm:"column:part_no"`
	PartName     string     `gorm:"column:part_name"`
	CreateTime   *time.Time `gorm:"column:create_time"`
	UpdateTime   *time.Time `gorm:"column:update_time"`
}

type legacyProposal struct {
	ID               int64      `gorm:"column:id"`
	ProposalNo       string     `gorm:"column:proposal_no"`
	Customer         string     `gorm:"column:customer"`
	Spec             string     `gorm:"column:spec"`
	Quantity         int        `gorm:"column:quantity"`
	Supplier         string     `gorm:"column:supplier"`
	Contact          string     `gorm:"column:contact"`
	Phone            string     `gorm:"column:phone"`
	Description      string     `gorm:"column:description"`
	Version          int        `gorm:"column:version"`
	Status           int8       `gorm:"column:status"`
	UserID           int64      `gorm:"column:user_id"`
	ReviewerID       *int64     `gorm:"column:reviewer_id"`
	ReviewRemark     string     `gorm:"column:review_remark"`
	SubmitTime       *time.Time `gorm:"column:submit_time"`
	ReviewTime       *time.Time `gorm:"column:review_time"`
	ArchiveTime      *time.Time `gorm:"column:archive_time"`
	CreateTime       *time.Time `gorm:"column:create_time"`
	UpdateTime       *time.Time `gorm:"column:update_time"`
	DeleteTime       *time.Time `gorm:"column:delete_time"`
	SourceProposalID int64      `gorm:"column:source_proposal_id"`
	WriteDate        string     `gorm:"column:write_date"`
	ProjectModel     string     `gorm:"column:project_model"`
	PartNo           string     `gorm:"column:part_no"`
	PartName         string     `gorm:"column:part_name"`
	PartMaterial     string     `gorm:"column:part_material"`

	CustomerAddress string `gorm:"column:customer_address"`
	CustomerContact string `gorm:"column:customer_contact"`
	CustomerPhone   string `gorm:"column:customer_phone"`
	CustomerEmail   string `gorm:"column:customer_email"`
	CustomerFax     string `gorm:"column:customer_fax"`

	SupplierCode    string `gorm:"column:supplier_code"`
	SupplierAddress string `gorm:"column:supplier_address"`
	SupplierEmail   string `gorm:"column:supplier_email"`
	SupplierFax     string `gorm:"column:supplier_fax"`

	PackBoxNo    string  `gorm:"column:pack_box_no"`
	PackMaterial string  `gorm:"column:pack_material"`
	PartWeight   float64 `gorm:"column:part_weight"`
	PartSizeL    float64 `gorm:"column:part_size_l"`
	PartSizeW    float64 `gorm:"column:part_size_w"`
	PartSizeH    float64 `gorm:"column:part_size_h"`
	BoxSizeL     float64 `gorm:"column:box_size_l"`
	BoxSizeW     float64 `gorm:"column:box_size_w"`
	BoxSizeH     float64 `gorm:"column:box_size_h"`
	GrossWeight  float64 `gorm:"column:gross_weight"`

	Returnable        *int8 `gorm:"column:returnable"`
	DunnageReturnable *int8 `gorm:"column:dunnage_returnable"`
	WithPallet        *int8 `gorm:"column:with_pallet"`
	PackingQty        int   `gorm:"column:packing_qty"`

	PackagingInstruction string `gorm:"column:packaging_instruction"`

	PalletSizeL      float64 `gorm:"column:pallet_size_l"`
	PalletSizeW      float64 `gorm:"column:pallet_size_w"`
	PalletSizeH      float64 `gorm:"column:pallet_size_h"`
	PalletLayerQty   int     `gorm:"column:pallet_layer_qty"`
	PalletLayerCount int     `gorm:"column:pallet_layer_count"`
	PalletPartsQty   int     `gorm:"column:pallet_parts_qty"`
}

type legacyAttachment struct {
	ID         int64      `gorm:"column:id"`
	ProposalID int64      `gorm:"column:proposal_id"`
	AttachType string     `gorm:"column:attach_type"`
	UserID     int64      `gorm:"column:user_id"`
	FileName   string     `gorm:"column:file_name"`
	FilePath   string     `gorm:"column:file_path"`
	FileExt    string     `gorm:"column:file_ext"`
	FileSize   int64      `gorm:"column:file_size"`
	MimeType   string     `gorm:"column:mime_type"`
	MD5        string     `gorm:"column:md5"`
	CreateTime *time.Time `gorm:"column:create_time"`
}

type legacyAudit struct {
	ID         int64      `gorm:"column:id"`
	ProposalID int64      `gorm:"column:proposal_id"`
	UserID     int64      `gorm:"column:user_id"`
	Action     string     `gorm:"column:action"`
	ActionName string     `gorm:"column:action_name"`
	FromStatus *int8      `gorm:"column:from_status"`
	ToStatus   *int8      `gorm:"column:to_status"`
	Version    int        `gorm:"column:version"`
	Content    string     `gorm:"column:content"`
	IP         string     `gorm:"column:ip"`
	CreateTime *time.Time `gorm:"column:create_time"`
}

type legacyMasterLog struct {
	ID         int64      `gorm:"column:id"`
	BizType    string     `gorm:"column:biz_type"`
	TargetID   int64      `gorm:"column:target_id"`
	TargetName string     `gorm:"column:target_name"`
	UserID     int64      `gorm:"column:user_id"`
	Action     string     `gorm:"column:action"`
	ActionName string     `gorm:"column:action_name"`
	Content    string     `gorm:"column:content"`
	CreateTime *time.Time `gorm:"column:create_time"`
}

type legacyPartLog struct {
	ID           int64      `gorm:"column:id"`
	SupplierName string     `gorm:"column:supplier_name"`
	PartNo       string     `gorm:"column:part_no"`
	UserID       int64      `gorm:"column:user_id"`
	Action       string     `gorm:"column:action"`
	ActionName   string     `gorm:"column:action_name"`
	Content      string     `gorm:"column:content"`
	CreateTime   *time.Time `gorm:"column:create_time"`
}

type migrator struct {
	db    *gorm.DB
	stats map[string]int

	userMap     map[int64]string
	customerMap map[int64]string
	supplierMap map[int64]string
	partMap     map[int64]string
	proposalMap map[int64]string

	supplierByName map[string]string
	customerByName map[string]string
}

func main() {
	configPath := flag.String("config", "configs/config.yaml", "配置文件路径")
	flag.Parse()

	cfg, err := config.Load(*configPath)
	if err != nil {
		log.Fatalf("加载配置失败: %v", err)
	}

	db, err := database.Open(cfg)
	if err != nil {
		log.Fatalf("连接数据库失败: %v", err)
	}

	var existing int64
	if err := db.Model(&model.User{}).Count(&existing).Error; err != nil {
		log.Fatalf("检查新表失败: %v", err)
	}

	if existing > 0 && !*dryRun {
		log.Fatalf("新表已有 %d 个用户，迁移会重复写入。请先清空新表（或使用 -dry-run 仅统计）", existing)
	}

	m := &migrator{
		db:             db,
		stats:          map[string]int{},
		userMap:        map[int64]string{},
		customerMap:    map[int64]string{},
		supplierMap:    map[int64]string{},
		partMap:        map[int64]string{},
		proposalMap:    map[int64]string{},
		supplierByName: map[string]string{},
		customerByName: map[string]string{},
	}

	steps := []struct {
		name string
		run  func() error
	}{
		{"用户", m.migrateUsers},
		{"客户", m.migrateCustomers},
		{"供应商", m.migrateSuppliers},
		{"供应商联系人", m.migrateContacts},
		{"零件号", m.migrateParts},
		{"方案", m.migrateProposals},
		{"方案变更来源", m.fixProposalSources},
		{"附件", m.migrateAttachments},
		{"审计日志", m.migrateAuditLogs},
		{"主数据日志", m.migrateMasterLogs},
		{"零件号日志", m.migratePartLogs},
	}

	for _, step := range steps {
		if err := step.run(); err != nil {
			log.Fatalf("迁移%s失败: %v", step.name, err)
		}
	}

	log.Println("---------------- 迁移汇总 ----------------")

	for _, key := range []string{"用户", "客户", "供应商", "联系人", "零件号", "方案", "附件", "审计日志", "主数据日志", "零件号日志"} {
		log.Printf("%-10s %d 条", key, m.stats[key])
	}

	if *dryRun {
		log.Println("dry-run 模式：未写入任何数据")
	}
}

func (m *migrator) table(name string) string {
	return *legacyPrefix + name
}

func (m *migrator) load(table string, target any) (bool, error) {
	err := m.db.Raw("SELECT * FROM `" + m.table(table) + "`").Scan(target).Error
	if err != nil {
		log.Printf("[skip] 读取 %s 失败（旧表可能不存在）: %v", m.table(table), err)
		return false, nil
	}

	return true, nil
}

func (m *migrator) create(record any, key string, count int) error {
	if *dryRun {
		m.stats[key] += count
		return nil
	}

	if err := m.db.Create(record).Error; err != nil {
		return err
	}

	m.stats[key] += count

	return nil
}

func convertPassword(hash string) string {
	// Go 的 bcrypt 对 $2y$ 兼容性不稳定，统一改写为 $2b$（算法与哈希值不变）
	if strings.HasPrefix(hash, "$2y$") {
		return "$2b$" + hash[4:]
	}

	return hash
}

func timeOrNow(value *time.Time) time.Time {
	if value != nil && !value.IsZero() {
		return *value
	}

	return time.Now()
}

func deletedAt(value *time.Time) gorm.DeletedAt {
	if value == nil || value.IsZero() {
		return gorm.DeletedAt{}
	}

	return gorm.DeletedAt{Time: *value, Valid: true}
}

func (m *migrator) migrateUsers() error {
	var rows []legacyUser
	if ok, err := m.load("user", &rows); !ok || err != nil {
		return err
	}

	for _, row := range rows {
		id := ulidx.New()
		m.userMap[row.ID] = id

		user := model.User{
			ID:           id,
			Username:     row.Username,
			PasswordHash: convertPassword(row.Password),
			RealName:     row.RealName,
			Role:         row.Role,
			Dept:         row.Dept,
			Phone:        row.Phone,
			Status:       row.Status,
			TokenVersion: 1,
			CreatedAt:    timeOrNow(row.CreateTime),
			UpdatedAt:    timeOrNow(row.UpdateTime),
		}

		if *dryRun {
			m.stats["用户"]++
			continue
		}

		if err := m.db.Create(&user).Error; err != nil {
			return fmt.Errorf("用户 %s: %w", row.Username, err)
		}

		m.stats["用户"]++
	}

	return nil
}

func (m *migrator) migrateCustomers() error {
	var rows []legacyCustomer
	if ok, err := m.load("customer", &rows); !ok || err != nil {
		return err
	}

	for _, row := range rows {
		id := ulidx.New()
		m.customerMap[row.ID] = id
		m.customerByName[row.Name] = id

		customer := model.Customer{
			ID:        id,
			Name:      row.Name,
			Address:   row.Address,
			Contact:   row.Contact,
			Phone:     row.Phone,
			Email:     row.Email,
			Fax:       row.Fax,
			Status:    row.Status,
			CreatedAt: timeOrNow(row.CreateTime),
			UpdatedAt: timeOrNow(row.UpdateTime),
			DeletedAt: deletedAt(row.DeleteTime),
		}

		if *dryRun {
			m.stats["客户"]++
			continue
		}

		if err := m.db.Create(&customer).Error; err != nil {
			return fmt.Errorf("客户 %s: %w", row.Name, err)
		}

		m.stats["客户"]++
	}

	return nil
}

func (m *migrator) migrateSuppliers() error {
	var rows []legacySupplier
	if ok, err := m.load("supplier", &rows); !ok || err != nil {
		return err
	}

	for _, row := range rows {
		id := ulidx.New()
		m.supplierMap[row.ID] = id
		m.supplierByName[row.Name] = id

		supplier := model.Supplier{
			ID:        id,
			Name:      row.Name,
			Code:      row.Code,
			Address:   row.Address,
			Status:    row.Status,
			CreatedAt: timeOrNow(row.CreateTime),
			UpdatedAt: timeOrNow(row.UpdateTime),
			DeletedAt: deletedAt(row.DeleteTime),
		}

		if *dryRun {
			m.stats["供应商"]++
			continue
		}

		if err := m.db.Create(&supplier).Error; err != nil {
			return fmt.Errorf("供应商 %s: %w", row.Name, err)
		}

		m.stats["供应商"]++
	}

	return nil
}

func (m *migrator) migrateContacts() error {
	var rows []legacyContact
	if ok, err := m.load("supplier_contact", &rows); !ok || err != nil {
		return err
	}

	for _, row := range rows {
		supplierID, ok := m.supplierMap[row.SupplierID]
		if !ok {
			log.Printf("[skip] 联系人 %s 的供应商不存在", row.Name)
			continue
		}

		contact := model.SupplierContact{
			ID:         ulidx.New(),
			SupplierID: supplierID,
			Name:       row.Name,
			Phone:      row.Phone,
			Email:      row.Email,
			Fax:        row.Fax,
			IsDefault:  row.IsDefault,
			CreatedAt:  timeOrNow(row.CreateTime),
			UpdatedAt:  timeOrNow(row.UpdateTime),
		}

		if *dryRun {
			m.stats["联系人"]++
			continue
		}

		if err := m.db.Create(&contact).Error; err != nil {
			return err
		}

		m.stats["联系人"]++
	}

	return nil
}

func (m *migrator) migrateParts() error {
	var rows []legacyPart
	if ok, err := m.load("supplier_part", &rows); !ok || err != nil {
		return err
	}

	for _, row := range rows {
		supplierID, ok := m.supplierByName[row.SupplierName]
		if !ok {
			log.Printf("[skip] 零件号 %s 的供应商「%s」不存在", row.PartNo, row.SupplierName)
			continue
		}

		part := model.SupplierPart{
			ID:           ulidx.New(),
			SupplierID:   supplierID,
			SupplierName: row.SupplierName,
			ProjectName:  row.ProjectName,
			PartNo:       row.PartNo,
			PartName:     row.PartName,
			CreatedAt:    timeOrNow(row.CreateTime),
			UpdatedAt:    timeOrNow(row.UpdateTime),
		}

		if *dryRun {
			m.stats["零件号"]++
			continue
		}

		if err := m.db.Create(&part).Error; err != nil {
			return err
		}

		m.partMap[row.ID] = part.ID
		m.stats["零件号"]++
	}

	return nil
}

func (m *migrator) migrateProposals() error {
	var rows []legacyProposal
	if ok, err := m.load("pack_proposal", &rows); !ok || err != nil {
		return err
	}

	for _, row := range rows {
		id := ulidx.New()
		m.proposalMap[row.ID] = id

		supplierID := m.supplierByName[row.Supplier]
		if supplierID == "" {
			log.Printf("[warn] 方案 %s 的供应商「%s」不存在，使用占位值", row.ProposalNo, row.Supplier)
		}

		var customerID *string
		if cid, ok := m.customerByName[row.Customer]; ok {
			customerID = &cid
		}

		createdBy := m.userMap[row.UserID]

		proposal := model.Proposal{
			ID:                   id,
			ProposalNo:           row.ProposalNo,
			Version:              row.Version,
			Status:               row.Status,
			SupplierID:           supplierID,
			SupplierName:         row.Supplier,
			CreatedBy:            createdBy,
			ReviewRemark:         row.ReviewRemark,
			WriteDate:            row.WriteDate,
			ProjectModel:         row.ProjectModel,
			PartNo:               row.PartNo,
			PartName:             row.PartName,
			PartMaterial:         row.PartMaterial,
			Spec:                 row.Spec,
			Quantity:             row.Quantity,
			CustomerID:           customerID,
			CustomerName:         row.Customer,
			CustomerAddress:      row.CustomerAddress,
			CustomerContact:      row.CustomerContact,
			CustomerPhone:        row.CustomerPhone,
			CustomerEmail:        row.CustomerEmail,
			CustomerFax:          row.CustomerFax,
			SupplierCode:         row.SupplierCode,
			SupplierAddress:      row.SupplierAddress,
			Contact:              row.Contact,
			Phone:                row.Phone,
			SupplierEmail:        row.SupplierEmail,
			SupplierFax:          row.SupplierFax,
			PackBoxNo:            row.PackBoxNo,
			PackMaterial:         row.PackMaterial,
			PartWeight:           row.PartWeight,
			PartSizeL:            row.PartSizeL,
			PartSizeW:            row.PartSizeW,
			PartSizeH:            row.PartSizeH,
			BoxSizeL:             row.BoxSizeL,
			BoxSizeW:             row.BoxSizeW,
			BoxSizeH:             row.BoxSizeH,
			GrossWeight:          row.GrossWeight,
			Returnable:           row.Returnable,
			DunnageReturnable:    row.DunnageReturnable,
			WithPallet:           row.WithPallet,
			PackingQty:           row.PackingQty,
			PackagingInstruction: row.PackagingInstruction,
			Description:          row.Description,
			PalletSizeL:          row.PalletSizeL,
			PalletSizeW:          row.PalletSizeW,
			PalletSizeH:          row.PalletSizeH,
			PalletLayerQty:       row.PalletLayerQty,
			PalletLayerCount:     row.PalletLayerCount,
			PalletPartsQty:       row.PalletPartsQty,
			SubmitTime:           row.SubmitTime,
			ReviewTime:           row.ReviewTime,
			ArchiveTime:          row.ArchiveTime,
			CreatedAt:            timeOrNow(row.CreateTime),
			UpdatedAt:            timeOrNow(row.UpdateTime),
			DeletedAt:            deletedAt(row.DeleteTime),
		}

		if row.ReviewerID != nil {
			if rid, ok := m.userMap[*row.ReviewerID]; ok {
				proposal.ReviewerID = &rid
			}
		}

		if *dryRun {
			m.stats["方案"]++
			continue
		}

		if err := m.db.Create(&proposal).Error; err != nil {
			return fmt.Errorf("方案 %s: %w", row.ProposalNo, err)
		}

		m.stats["方案"]++
	}

	return nil
}

// fixProposalSources 两遍写入：方案落库后再回填 source_proposal_id
func (m *migrator) fixProposalSources() error {
	if *dryRun {
		return nil
	}

	var rows []legacyProposal
	if ok, err := m.load("pack_proposal", &rows); !ok || err != nil {
		return err
	}

	for _, row := range rows {
		if row.SourceProposalID <= 0 {
			continue
		}

		newID, ok := m.proposalMap[row.ID]
		if !ok {
			continue
		}

		sourceID, ok := m.proposalMap[row.SourceProposalID]
		if !ok {
			continue
		}

		if err := m.db.Model(&model.Proposal{}).Where("id = ?", newID).
			Update("source_proposal_id", sourceID).Error; err != nil {
			return err
		}
	}

	return nil
}

func (m *migrator) migrateAttachments() error {
	var rows []legacyAttachment
	if ok, err := m.load("attachment", &rows); !ok || err != nil {
		return err
	}

	for _, row := range rows {
		attachType := row.AttachType
		if attachType == "" {
			attachType = model.AttachOther
		}

		createdAt := timeOrNow(row.CreateTime)
		relative := m.copyLegacyFile(row.FilePath, createdAt, row.FileExt)

		attachment := model.Attachment{
			ID:         ulidx.New(),
			AttachType: attachType,
			UploadedBy: m.userMap[row.UserID],
			FileName:   row.FileName,
			FilePath:   relative,
			FileExt:    row.FileExt,
			FileSize:   row.FileSize,
			MimeType:   row.MimeType,
			MD5:        row.MD5,
			CreatedAt:  createdAt,
		}

		if row.ProposalID > 0 {
			if pid, ok := m.proposalMap[row.ProposalID]; ok {
				attachment.ProposalID = &pid
			}
		}

		if *dryRun {
			m.stats["附件"]++
			continue
		}

		if relative == "" {
			log.Printf("[skip] 附件 %s 的物理文件不存在", row.FileName)
			continue
		}

		if err := m.db.Create(&attachment).Error; err != nil {
			return err
		}

		m.stats["附件"]++
	}

	return nil
}

// copyLegacyFile 把旧附件复制到新附件目录，返回新的相对路径
func (m *migrator) copyLegacyFile(oldPath string, createdAt time.Time, ext string) string {
	subPath := strings.TrimPrefix(oldPath, "storage/attachments/")
	subPath = strings.TrimPrefix(subPath, "/")

	if subPath == "" || strings.Contains(subPath, "..") {
		return ""
	}

	source := filepath.Join(*legacyStorage, filepath.FromSlash(subPath))

	data, err := os.ReadFile(source)
	if err != nil {
		return ""
	}

	dir := filepath.Dir(subPath)
	if dir == "." {
		dir = createdAt.Format("200601")
	}

	if ext == "" {
		ext = strings.TrimPrefix(filepath.Ext(subPath), ".")
	}

	name := ulidx.New()
	if ext != "" {
		name += "." + ext
	}

	relative := filepath.ToSlash(filepath.Join(dir, name))
	destination := filepath.Join(*storageDir, filepath.FromSlash(relative))

	if *dryRun {
		return relative
	}

	if err := os.MkdirAll(filepath.Dir(destination), 0o755); err != nil {
		return ""
	}

	if err := os.WriteFile(destination, data, 0o644); err != nil {
		return ""
	}

	return relative
}

func (m *migrator) migrateAuditLogs() error {
	var rows []legacyAudit
	if ok, err := m.load("review_log", &rows); !ok || err != nil {
		return err
	}

	for _, row := range rows {
		log := model.AuditLog{
			ID:         ulidx.New(),
			UserID:     m.userMap[row.UserID],
			Action:     row.Action,
			ActionName: row.ActionName,
			FromStatus: row.FromStatus,
			ToStatus:   row.ToStatus,
			Version:    row.Version,
			Content:    row.Content,
			IP:         row.IP,
			CreatedAt:  timeOrNow(row.CreateTime),
		}

		if row.ProposalID > 0 {
			if pid, ok := m.proposalMap[row.ProposalID]; ok {
				log.ProposalID = &pid
			}
		}

		if *dryRun {
			m.stats["审计日志"]++
			continue
		}

		if err := m.db.Create(&log).Error; err != nil {
			return err
		}

		m.stats["审计日志"]++
	}

	return nil
}

func (m *migrator) migrateMasterLogs() error {
	var rows []legacyMasterLog
	if ok, err := m.load("master_log", &rows); !ok || err != nil {
		return err
	}

	for _, row := range rows {
		targetID := ""

		switch row.BizType {
		case "customer":
			targetID = m.customerMap[row.TargetID]
		case "supplier":
			targetID = m.supplierMap[row.TargetID]
		case "user":
			targetID = m.userMap[row.TargetID]
		}

		if targetID == "" {
			continue
		}

		log := model.MasterLog{
			ID:         ulidx.New(),
			BizType:    row.BizType,
			TargetID:   targetID,
			TargetName: row.TargetName,
			UserID:     m.userMap[row.UserID],
			Action:     row.Action,
			ActionName: row.ActionName,
			Content:    row.Content,
			CreatedAt:  timeOrNow(row.CreateTime),
		}

		if *dryRun {
			m.stats["主数据日志"]++
			continue
		}

		if err := m.db.Create(&log).Error; err != nil {
			return err
		}

		m.stats["主数据日志"]++
	}

	return nil
}

func (m *migrator) migratePartLogs() error {
	var rows []legacyPartLog
	if ok, err := m.load("part_log", &rows); !ok || err != nil {
		return err
	}

	for _, row := range rows {
		supplierID := m.supplierByName[row.SupplierName]
		if supplierID == "" {
			continue
		}

		log := model.PartLog{
			ID:           ulidx.New(),
			SupplierID:   supplierID,
			SupplierName: row.SupplierName,
			PartNo:       row.PartNo,
			UserID:       m.userMap[row.UserID],
			Action:       row.Action,
			ActionName:   row.ActionName,
			Content:      row.Content,
			CreatedAt:    timeOrNow(row.CreateTime),
		}

		if *dryRun {
			m.stats["零件号日志"]++
			continue
		}

		if err := m.db.Create(&log).Error; err != nil {
			return err
		}

		m.stats["零件号日志"]++
	}

	return nil
}
