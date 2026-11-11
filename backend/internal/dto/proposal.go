package dto

// ProposalInput 方案编辑入参
// 注意：spec / quantity / pallet_parts_qty / write_date 由后端派生，前端传值会被忽略
type ProposalInput struct {
	CustomerID      *string `json:"customer_id"`
	CustomerName    string  `json:"customer_name"`
	CustomerAddress string  `json:"customer_address"`
	CustomerContact string  `json:"customer_contact"`
	CustomerPhone   string  `json:"customer_phone"`
	CustomerEmail   string  `json:"customer_email"`
	CustomerFax     string  `json:"customer_fax"`

	// 仅管理员可指定供应商；供应商角色由后端强制为账号所属供应商
	SupplierID string `json:"supplier_id"`

	// 供应商联系人快照（从供应商联系人下拉选择后带出）
	Contact       string `json:"contact"`
	Phone         string `json:"phone"`
	SupplierEmail string `json:"supplier_email"`
	SupplierFax   string `json:"supplier_fax"`

	ProjectModel string `json:"project_model"`
	PartNo       string `json:"part_no"`
	PartName     string `json:"part_name"`
	PartMaterial string `json:"part_material"`

	PackBoxNo    string  `json:"pack_box_no"`
	PackMaterial string  `json:"pack_material"`
	PartWeight   float64 `json:"part_weight"`
	PartSizeL    float64 `json:"part_size_l"`
	PartSizeW    float64 `json:"part_size_w"`
	PartSizeH    float64 `json:"part_size_h"`
	BoxSizeL     float64 `json:"box_size_l"`
	BoxSizeW     float64 `json:"box_size_w"`
	BoxSizeH     float64 `json:"box_size_h"`
	GrossWeight  float64 `json:"gross_weight"`

	Returnable        *int8 `json:"returnable"`
	DunnageReturnable *int8 `json:"dunnage_returnable"`
	WithPallet        *int8 `json:"with_pallet"`
	PackingQty        int   `json:"packing_qty"`

	PackagingInstruction string `json:"packaging_instruction"`
	Description          string `json:"description"`

	PalletSizeL      float64 `json:"pallet_size_l"`
	PalletSizeW      float64 `json:"pallet_size_w"`
	PalletSizeH      float64 `json:"pallet_size_h"`
	PalletLayerQty   int     `json:"pallet_layer_qty"`
	PalletLayerCount int     `json:"pallet_layer_count"`
}

// ReviewInput 评审入参
type ReviewInput struct {
	Status int8   `json:"status"` // 2 通过 / 3 驳回
	Remark string `json:"remark"`
}

// ArchiveInput 批量归档入参
type ArchiveInput struct {
	IDs []string `json:"ids"`
}

// ContactInput 供应商联系人入参
type ContactInput struct {
	SupplierID string `json:"supplier_id"`
	Name       string `json:"name"`
	Phone      string `json:"phone"`
	Email      string `json:"email"`
	Fax        string `json:"fax"`
	IsDefault  *int8  `json:"is_default"`
}
