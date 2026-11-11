package dto

// ContactInput 供应商联系人入参
// 供应商角色忽略 supplier_id（后端强制为本供应商），管理员必须传
type ContactInput struct {
	SupplierID string `json:"supplier_id" binding:"max=26"`
	Name       string `json:"name" binding:"required,max=50"`
	Phone      string `json:"phone" binding:"max=30"`
	Email      string `json:"email" binding:"omitempty,email,max=100"`
	Fax        string `json:"fax" binding:"max=30"`
	IsDefault  *int8  `json:"is_default" binding:"omitempty,oneof=0 1"`
}
