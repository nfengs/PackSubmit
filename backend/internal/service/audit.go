package service

import (
	"gorm.io/gorm"

	"packsubmit/internal/model"
	"packsubmit/internal/pkg/ulidx"
)

// 动作名称快照，保证历史日志文案稳定
var actionNames = map[string]string{
	"create":            "新增方案",
	"update":            "编辑方案",
	"view":              "查看方案",
	"export":            "导出方案",
	"submit":            "提交评审",
	"withdraw":          "撤回提交",
	"review_pass":       "评审通过",
	"review_reject":     "评审驳回",
	"archive":           "方案归档",
	"change":            "发起变更",
	"obsolete":          "变更作废原方案",
	"delete":            "删除方案",
	"upload":            "上传附件",
	"delete_attachment": "删除附件",
	"login":             "登录系统",
	"logout":            "退出登录",
	"password_change":   "修改密码",
	"part_create":       "导入新增零件号",
	"part_update":       "修改零件号信息",
	"part_delete":       "删除零件号",
}

// ActionName 取动作中文名（未登记时回退为动作标识）
func ActionName(action string) string {
	if name, ok := actionNames[action]; ok {
		return name
	}

	return action
}

type AuditEntry struct {
	ProposalID *string
	UserID     string
	Action     string
	FromStatus *int8
	ToStatus   *int8
	Version    int
	Content    string
	IP         string
}

// Record 写入全局审计日志（可传事务）
func Record(db *gorm.DB, entry AuditEntry) error {
	if db == nil || entry.UserID == "" {
		return nil
	}

	version := entry.Version
	if version <= 0 {
		version = 1
	}

	log := model.AuditLog{
		ID:         ulidx.New(),
		ProposalID: entry.ProposalID,
		UserID:     entry.UserID,
		Action:     entry.Action,
		ActionName: ActionName(entry.Action),
		FromStatus: entry.FromStatus,
		ToStatus:   entry.ToStatus,
		Version:    version,
		Content:    entry.Content,
		IP:         entry.IP,
	}

	return db.Create(&log).Error
}

// Int8 生成状态指针，便于日志记录可选状态
func Int8(value int8) *int8 { return &value }

// StrPtr 生成字符串指针
func StrPtr(value string) *string { return &value }
