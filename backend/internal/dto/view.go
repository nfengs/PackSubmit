package dto

import (
	"time"

	"packsubmit/internal/model"
)

// 说明：仅当响应结构与数据表不一致时才定义 View，
// 客户 / 供应商 / 字典等直接输出 model，不额外套壳。

// ProposalView 方案列表行：附状态文案、人员姓名与当前用户可执行操作
type ProposalView struct {
	model.Proposal
	StatusLabel  string          `json:"status_label"`
	CreatorName  string          `json:"creator_name"`
	ReviewerName string          `json:"reviewer_name"`
	Permissions  map[string]bool `json:"permissions"`
}

// HistoryItem 方案流程记录（由审计日志派生）
type HistoryItem struct {
	Action     string    `json:"action"`
	ActionName string    `json:"action_name"`
	Version    int       `json:"version"`
	Content    string    `json:"content"`
	UserName   string    `json:"user_name"`
	CreatedAt  time.Time `json:"created_at"`
}

// ProposalDetail 方案详情：字段 + 附件 + 流程 + 变更关系
type ProposalDetail struct {
	ProposalView
	Attachments  []model.Attachment `json:"attachments"`
	Logs         []HistoryItem      `json:"logs"`
	History      []HistoryItem      `json:"history"`
	Source       *ProposalView      `json:"source,omitempty"`
	ActiveChange *ProposalView      `json:"active_change,omitempty"`
}

// Statistics 工作台统计
type Statistics struct {
	Draft    int64 `json:"draft"`
	Pending  int64 `json:"pending"`
	Approved int64 `json:"approved"`
	Archived int64 `json:"archived"`
	Obsolete int64 `json:"obsolete"`
	Total    int64 `json:"total"`
}

// ContactView 供应商联系人（带供应商名称，管理员跨供应商查看时需要）
type ContactView struct {
	model.SupplierContact
	SupplierName string `json:"supplier_name"`
}

// AuditLogView 审计日志（带操作人姓名）
type AuditLogView struct {
	model.AuditLog
	UserName string `json:"user_name"`
}
