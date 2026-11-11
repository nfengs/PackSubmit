package dto

// UserInput 用户新增/编辑入参
type UserInput struct {
	Username   string `json:"username" binding:"required,max=50"`
	Password   string `json:"password" binding:"omitempty,max=72"`
	RealName   string `json:"real_name" binding:"max=50"`
	Role       int8   `json:"role" binding:"required,oneof=1 2 3 4"`
	Dept       string `json:"dept" binding:"max=100"`
	SupplierID string `json:"supplier_id" binding:"max=26"`
	Phone      string `json:"phone" binding:"max=30"`
}

type UserStatusInput struct {
	Status int8 `json:"status" binding:"oneof=0 1"`
}

type ResetPasswordInput struct {
	Password string `json:"password" binding:"required,min=8,max=32"`
}

// CustomerInput 客户档案入参
type CustomerInput struct {
	Name    string `json:"name" binding:"required,max=100"`
	Address string `json:"address" binding:"max=255"`
	Contact string `json:"contact" binding:"max=50"`
	Phone   string `json:"phone" binding:"max=30"`
	Email   string `json:"email" binding:"omitempty,email,max=100"`
	Fax     string `json:"fax" binding:"max=30"`
	Status  *int8  `json:"status" binding:"omitempty,oneof=0 1"`
}

// SupplierInput 供应商档案入参（仅名称 / 代码 / 地址）
type SupplierInput struct {
	Name    string `json:"name" binding:"required,max=100"`
	Code    string `json:"code" binding:"max=50"`
	Address string `json:"address" binding:"max=255"`
	Status  *int8  `json:"status" binding:"omitempty,oneof=0 1"`
}

// PartInput 零件号行内编辑（供应商与零件号只读）
type PartInput struct {
	ProjectName string `json:"project_name" binding:"max=150"`
	PartName    string `json:"part_name" binding:"max=200"`
}

// BatchPartItem 批量修改单行（预览与提交共用）
type BatchPartItem struct {
	SupplierName string `json:"supplier_name"`
	PartNo       string `json:"part_no"`
	ProjectName  string `json:"project_name"`
	PartName     string `json:"part_name"`
}

// BatchPreviewItem 批量修改预览结果
type BatchPreviewItem struct {
	ID             string `json:"id"`
	SupplierName   string `json:"supplier_name"`
	PartNo         string `json:"part_no"`
	ProjectName    string `json:"project_name"`
	PartName       string `json:"part_name"`
	NewProjectName string `json:"new_project_name"`
	NewPartName    string `json:"new_part_name"`
	Status         string `json:"status"` // update / same / missing / error
	Message        string `json:"message"`
}

// ImportResult 通用导入结果
type ImportResult struct {
	Created int      `json:"created"`
	Updated int      `json:"updated"`
	Skipped int      `json:"skipped"`
	Drafts  int      `json:"drafts"`
	Errors  []string `json:"errors"`
}
