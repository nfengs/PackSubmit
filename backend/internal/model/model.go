package model

import (
	"time"

	"gorm.io/gorm"
)

// 方案状态：0 草稿 1 待评审 2 评审通过 3 驳回 4 已归档 5 已归档-已变更-作废
const (
	StatusDraft    int8 = 0
	StatusPending  int8 = 1
	StatusApproved int8 = 2
	StatusRejected int8 = 3
	StatusArchived int8 = 4
	StatusObsolete int8 = 5
)

var StatusText = map[int8]string{
	StatusDraft:    "草稿",
	StatusPending:  "待评审",
	StatusApproved: "评审通过",
	StatusRejected: "驳回",
	StatusArchived: "已归档",
	StatusObsolete: "已归档-已变更-作废",
}

// ActiveStatuses 进行中的状态（用于「同一供应商 + 零件号只能有一条有效方案」判断）
var ActiveStatuses = []int8{StatusDraft, StatusPending, StatusApproved, StatusRejected}

// 附件类型
const (
	AttachPart      = "part"
	AttachInner     = "inner"
	AttachFinal     = "final"
	AttachPackaging = "packaging"
	AttachOther     = "attachment"
)

var ImageAttachTypes = []string{AttachPart, AttachInner, AttachFinal, AttachPackaging}

var AttachTypeText = map[string]string{
	AttachPart:      "零件图片",
	AttachInner:     "内部排列",
	AttachFinal:     "整体包装",
	AttachPackaging: "包装图片",
	AttachOther:     "其他附件",
}

type User struct {
	ID           string    `gorm:"primaryKey;size:26" json:"id"`
	Username     string    `gorm:"size:50;uniqueIndex;not null" json:"username"`
	PasswordHash string    `gorm:"size:255;not null" json:"-"`
	RealName     string    `gorm:"size:50" json:"real_name"`
	Role         int8      `gorm:"index;not null" json:"role"`
	Dept         string    `gorm:"size:100" json:"dept"`
	SupplierID   *string   `gorm:"size:26;index" json:"supplier_id"`
	Phone        string    `gorm:"size:30" json:"phone"`
	Status       int8      `gorm:"default:1;index" json:"status"`
	TokenVersion int       `gorm:"default:1" json:"-"`
	CreatedAt    time.Time `json:"created_at"`
	UpdatedAt    time.Time `json:"updated_at"`

	Supplier *Supplier `gorm:"foreignKey:SupplierID" json:"supplier,omitempty"`
}

func (User) TableName() string { return "users" }

const (
	UserEnabled  int8 = 1
	UserDisabled int8 = 0
)

type Supplier struct {
	ID        string         `gorm:"primaryKey;size:26" json:"id"`
	Name      string         `gorm:"size:100;uniqueIndex;not null" json:"name"`
	Code      string         `gorm:"size:50" json:"code"`
	Address   string         `gorm:"size:255" json:"address"`
	Status    int8           `gorm:"default:1" json:"status"`
	CreatedAt time.Time      `json:"created_at"`
	UpdatedAt time.Time      `json:"updated_at"`
	DeletedAt gorm.DeletedAt `gorm:"index" json:"-"`
}

func (Supplier) TableName() string { return "suppliers" }

type SupplierContact struct {
	ID         string         `gorm:"primaryKey;size:26" json:"id"`
	SupplierID string         `gorm:"size:26;index;not null" json:"supplier_id"`
	Name       string         `gorm:"size:50;not null" json:"name"`
	Phone      string         `gorm:"size:30" json:"phone"`
	Email      string         `gorm:"size:100" json:"email"`
	Fax        string         `gorm:"size:30" json:"fax"`
	IsDefault  int8           `gorm:"default:0" json:"is_default"`
	CreatedAt  time.Time      `json:"created_at"`
	UpdatedAt  time.Time      `json:"updated_at"`
	DeletedAt  gorm.DeletedAt `gorm:"index" json:"-"`
}

func (SupplierContact) TableName() string { return "supplier_contacts" }

type Customer struct {
	ID        string         `gorm:"primaryKey;size:26" json:"id"`
	Name      string         `gorm:"size:100;uniqueIndex;not null" json:"name"`
	Address   string         `gorm:"size:255" json:"address"`
	Contact   string         `gorm:"size:50" json:"contact"`
	Phone     string         `gorm:"size:30" json:"phone"`
	Email     string         `gorm:"size:100" json:"email"`
	Fax       string         `gorm:"size:30" json:"fax"`
	Status    int8           `gorm:"default:1" json:"status"`
	CreatedAt time.Time      `json:"created_at"`
	UpdatedAt time.Time      `json:"updated_at"`
	DeletedAt gorm.DeletedAt `gorm:"index" json:"-"`
}

func (Customer) TableName() string { return "customers" }

type SupplierPart struct {
	ID           string         `gorm:"primaryKey;size:26" json:"id"`
	SupplierID   string         `gorm:"size:26;uniqueIndex:uk_supplier_part;not null" json:"supplier_id"`
	SupplierName string         `gorm:"size:100" json:"supplier_name"`
	ProjectName  string         `gorm:"size:150" json:"project_name"`
	PartNo       string         `gorm:"size:100;uniqueIndex:uk_supplier_part;not null" json:"part_no"`
	PartName     string         `gorm:"size:200" json:"part_name"`
	CreatedAt    time.Time      `json:"created_at"`
	UpdatedAt    time.Time      `json:"updated_at"`
	DeletedAt    gorm.DeletedAt `gorm:"index" json:"-"`
}

func (SupplierPart) TableName() string { return "supplier_parts" }

type Dict struct {
	ID        string         `gorm:"primaryKey;size:26" json:"id"`
	DictType  string         `gorm:"size:50;uniqueIndex:uk_dict_type_value;not null" json:"dict_type"`
	DictLabel string         `gorm:"size:100;not null" json:"dict_label"`
	DictValue string         `gorm:"size:100;uniqueIndex:uk_dict_type_value;not null" json:"dict_value"`
	Sort      int            `gorm:"default:0" json:"sort"`
	Status    int8           `gorm:"default:1" json:"status"`
	Remark    string         `gorm:"size:255" json:"remark"`
	CreatedAt time.Time      `json:"created_at"`
	UpdatedAt time.Time      `json:"updated_at"`
	DeletedAt gorm.DeletedAt `gorm:"index" json:"-"`
}

func (Dict) TableName() string { return "dicts" }

// Proposal 包装方案（一条记录 = 一个供应商 + 零件号的某一版本）
type Proposal struct {
	ID               string  `gorm:"primaryKey;size:26" json:"id"`
	ProposalNo       string  `gorm:"size:32;uniqueIndex;not null" json:"proposal_no"`
	Version          int     `gorm:"default:1" json:"version"`
	SourceProposalID *string `gorm:"size:26;index" json:"source_proposal_id"`
	Status           int8    `gorm:"default:0;index" json:"status"`

	SupplierID   string  `gorm:"size:26;index;not null" json:"supplier_id"`
	SupplierName string  `gorm:"size:100;index;not null" json:"supplier_name"`
	CreatedBy    string  `gorm:"size:26;index;not null" json:"created_by"`
	ReviewerID   *string `gorm:"size:26" json:"reviewer_id"`
	ReviewRemark string  `gorm:"size:500" json:"review_remark"`

	WriteDate    string  `gorm:"size:10" json:"write_date"`
	ProjectModel string  `gorm:"size:100" json:"project_model"`
	PartNo       string  `gorm:"size:100;index" json:"part_no"`
	PartName     string  `gorm:"size:200" json:"part_name"`
	PartMaterial string  `gorm:"size:100" json:"part_material"`
	Spec         string  `gorm:"size:255" json:"spec"`
	Quantity     int     `json:"quantity"`
	CustomerID   *string `gorm:"size:26;index" json:"customer_id"`
	CustomerName string  `gorm:"size:100" json:"customer_name"`

	CustomerAddress string `gorm:"size:255" json:"customer_address"`
	CustomerContact string `gorm:"size:50" json:"customer_contact"`
	CustomerPhone   string `gorm:"size:30" json:"customer_phone"`
	CustomerEmail   string `gorm:"size:100" json:"customer_email"`
	CustomerFax     string `gorm:"size:30" json:"customer_fax"`

	SupplierCode    string `gorm:"size:50" json:"supplier_code"`
	SupplierAddress string `gorm:"size:255" json:"supplier_address"`
	Contact         string `gorm:"size:50" json:"contact"`
	Phone           string `gorm:"size:30" json:"phone"`
	SupplierEmail   string `gorm:"size:100" json:"supplier_email"`
	SupplierFax     string `gorm:"size:30" json:"supplier_fax"`

	PackBoxNo    string  `gorm:"size:50" json:"pack_box_no"`
	PackMaterial string  `gorm:"size:20" json:"pack_material"`
	PartWeight   float64 `gorm:"type:decimal(10,3)" json:"part_weight"`
	PartSizeL    float64 `gorm:"type:decimal(10,2)" json:"part_size_l"`
	PartSizeW    float64 `gorm:"type:decimal(10,2)" json:"part_size_w"`
	PartSizeH    float64 `gorm:"type:decimal(10,2)" json:"part_size_h"`
	BoxSizeL     float64 `gorm:"type:decimal(10,2)" json:"box_size_l"`
	BoxSizeW     float64 `gorm:"type:decimal(10,2)" json:"box_size_w"`
	BoxSizeH     float64 `gorm:"type:decimal(10,2)" json:"box_size_h"`
	GrossWeight  float64 `gorm:"type:decimal(10,3)" json:"gross_weight"`

	Returnable        *int8 `json:"returnable"`
	DunnageReturnable *int8 `json:"dunnage_returnable"`
	WithPallet        *int8 `json:"with_pallet"`
	PackingQty        int   `json:"packing_qty"`

	PackagingInstruction string `gorm:"type:text" json:"packaging_instruction"`
	Description          string `gorm:"type:text" json:"description"`

	PalletSizeL      float64 `gorm:"type:decimal(10,2)" json:"pallet_size_l"`
	PalletSizeW      float64 `gorm:"type:decimal(10,2)" json:"pallet_size_w"`
	PalletSizeH      float64 `gorm:"type:decimal(10,2)" json:"pallet_size_h"`
	PalletLayerQty   int     `json:"pallet_layer_qty"`
	PalletLayerCount int     `json:"pallet_layer_count"`
	PalletPartsQty   int     `json:"pallet_parts_qty"`

	SubmitTime  *time.Time `json:"submit_time"`
	ReviewTime  *time.Time `json:"review_time"`
	ArchiveTime *time.Time `json:"archive_time"`

	CreatedAt time.Time      `json:"created_at"`
	UpdatedAt time.Time      `json:"updated_at"`
	DeletedAt gorm.DeletedAt `gorm:"index" json:"-"`
}

func (Proposal) TableName() string { return "proposals" }

func (p *Proposal) StatusLabel() string {
	if text, ok := StatusText[p.Status]; ok {
		return text
	}

	return "未知"
}

type Attachment struct {
	ID         string    `gorm:"primaryKey;size:26" json:"id"`
	ProposalID *string   `gorm:"size:26;index" json:"proposal_id"`
	AttachType string    `gorm:"size:20;not null" json:"attach_type"`
	UploadedBy string    `gorm:"size:26;index;not null" json:"uploaded_by"`
	FileName   string    `gorm:"size:255;not null" json:"file_name"`
	FilePath   string    `gorm:"size:500;not null;index" json:"-"`
	FileExt    string    `gorm:"size:20" json:"file_ext"`
	FileSize   int64     `json:"file_size"`
	MimeType   string    `gorm:"size:100" json:"mime_type"`
	MD5        string    `gorm:"size:32;index" json:"md5"`
	CreatedAt  time.Time `json:"created_at"`
}

func (Attachment) TableName() string { return "attachments" }

// AuditLog 全局审计 + 方案评审日志（action 区分）
type AuditLog struct {
	ID         string    `gorm:"primaryKey;size:26" json:"id"`
	ProposalID *string   `gorm:"size:26;index" json:"proposal_id"`
	UserID     string    `gorm:"size:26;index" json:"user_id"`
	Action     string    `gorm:"size:30;index" json:"action"`
	ActionName string    `gorm:"size:50" json:"action_name"`
	FromStatus *int8     `json:"from_status"`
	ToStatus   *int8     `json:"to_status"`
	Version    int       `gorm:"default:1" json:"version"`
	Content    string    `gorm:"size:1000" json:"content"`
	IP         string    `gorm:"size:64" json:"ip"`
	CreatedAt  time.Time `gorm:"index" json:"created_at"`
}

func (AuditLog) TableName() string { return "audit_logs" }

type MasterLog struct {
	ID         string    `gorm:"primaryKey;size:26" json:"id"`
	BizType    string    `gorm:"size:30;index:idx_target;not null" json:"biz_type"`
	TargetID   string    `gorm:"size:26;index:idx_target;not null" json:"target_id"`
	TargetName string    `gorm:"size:150" json:"target_name"`
	UserID     string    `gorm:"size:26" json:"user_id"`
	Action     string    `gorm:"size:30" json:"action"`
	ActionName string    `gorm:"size:50" json:"action_name"`
	Content    string    `gorm:"size:1000" json:"content"`
	CreatedAt  time.Time `json:"created_at"`
}

func (MasterLog) TableName() string { return "master_logs" }

type PartLog struct {
	ID           string    `gorm:"primaryKey;size:26" json:"id"`
	SupplierID   string    `gorm:"size:26;index" json:"supplier_id"`
	SupplierName string    `gorm:"size:100;index:idx_part_logs" json:"supplier_name"`
	PartNo       string    `gorm:"size:100;index:idx_part_logs" json:"part_no"`
	UserID       string    `gorm:"size:26" json:"user_id"`
	Action       string    `gorm:"size:30" json:"action"`
	ActionName   string    `gorm:"size:50" json:"action_name"`
	Content      string    `gorm:"size:1000" json:"content"`
	CreatedAt    time.Time `json:"created_at"`
}

func (PartLog) TableName() string { return "part_logs" }
