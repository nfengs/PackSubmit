package dto

// BindAttachmentInput 暂存附件绑定到方案
type BindAttachmentInput struct {
	AttachmentID string `json:"attachment_id" binding:"required,max=26"`
	ProposalID   string `json:"proposal_id" binding:"required,max=26"`
}
