package dto

// ProposalFields 方案可编辑字段集合
// 注意：spec / quantity / pallet_parts_qty / write_date 由后端派生，刻意不在此处定义，
// 前端即使提交也会被 JSON 绑定丢弃。
type ProposalFields struct {
	CustomerID      *string `json:"customer_id"`
	CustomerName    string  `json:"customer_name" binding:"max=100"`
	CustomerAddress string  `json:"customer_address" binding:"max=255"`
	CustomerContact string  `json:"customer_contact" binding:"max=50"`
	CustomerPhone   string  `json:"customer_phone" binding:"max=30"`
	CustomerEmail   string  `json:"customer_email" binding:"max=100"`
	CustomerFax     string  `json:"customer_fax" binding:"max=30"`

	// 供应商联系人快照（从供应商联系人下拉选择后带出）
	Contact       string `json:"contact" binding:"max=50"`
	Phone         string `json:"phone" binding:"max=30"`
	SupplierEmail string `json:"supplier_email" binding:"max=100"`
	SupplierFax   string `json:"supplier_fax" binding:"max=30"`

	ProjectModel string `json:"project_model" binding:"max=100"`
	PartNo       string `json:"part_no" binding:"max=100"`
	PartName     string `json:"part_name" binding:"max=200"`
	PartMaterial string `json:"part_material" binding:"max=100"`

	PackBoxNo    string  `json:"pack_box_no" binding:"max=50"`
	PackMaterial string  `json:"pack_material" binding:"omitempty,oneof=plastic metal carton other"`
	PartWeight   float64 `json:"part_weight" binding:"gte=0"`
	PartSizeL    float64 `json:"part_size_l" binding:"gte=0"`
	PartSizeW    float64 `json:"part_size_w" binding:"gte=0"`
	PartSizeH    float64 `json:"part_size_h" binding:"gte=0"`
	BoxSizeL     float64 `json:"box_size_l" binding:"gte=0"`
	BoxSizeW     float64 `json:"box_size_w" binding:"gte=0"`
	BoxSizeH     float64 `json:"box_size_h" binding:"gte=0"`
	GrossWeight  float64 `json:"gross_weight" binding:"gte=0"`

	Returnable        *int8 `json:"returnable" binding:"omitempty,oneof=0 1"`
	DunnageReturnable *int8 `json:"dunnage_returnable" binding:"omitempty,oneof=0 1"`
	WithPallet        *int8 `json:"with_pallet" binding:"omitempty,oneof=0 1"`
	PackingQty        int   `json:"packing_qty" binding:"gte=0"`

	PackagingInstruction string `json:"packaging_instruction" binding:"max=2000"`
	Description          string `json:"description" binding:"max=2000"`

	PalletSizeL      float64 `json:"pallet_size_l" binding:"gte=0"`
	PalletSizeW      float64 `json:"pallet_size_w" binding:"gte=0"`
	PalletSizeH      float64 `json:"pallet_size_h" binding:"gte=0"`
	PalletLayerQty   int     `json:"pallet_layer_qty" binding:"gte=0"`
	PalletLayerCount int     `json:"pallet_layer_count" binding:"gte=0"`
}

// ProposalCreateInput 新增方案：供应商必填，零件号在业务层校验（受控建单）
type ProposalCreateInput struct {
	ProposalFields

	SupplierID string `json:"supplier_id" binding:"required,max=26"`
}

// ProposalUpdateInput 编辑方案：整表单覆盖提交，供应商仅管理员可调整
type ProposalUpdateInput struct {
	ProposalFields

	SupplierID string `json:"supplier_id" binding:"max=26"`
}

// ReviewInput 评审入参
type ReviewInput struct {
	Status int8   `json:"status" binding:"required,oneof=2 3"`
	Remark string `json:"remark" binding:"max=500"`
}

// ArchiveInput 批量归档入参
type ArchiveInput struct {
	IDs []string `json:"ids" binding:"required,min=1"`
}
