package service

import (
	"errors"
	"fmt"

	"gorm.io/gorm"

	"packsubmit/internal/model"
	"packsubmit/internal/pkg/ulidx"
)

// AdminService 主数据维护（用户 / 客户 / 供应商 / 零件号 / 字典）
type AdminService struct {
	DB        *gorm.DB
	Proposals *ProposalService
}

func NewAdminService(db *gorm.DB, proposals *ProposalService) *AdminService {
	return &AdminService{DB: db, Proposals: proposals}
}

type Page struct {
	Page     int
	PageSize int
}

func normalizePage(page Page) (int, int, int) {
	number, size := page.Page, page.PageSize

	if number < 1 {
		number = 1
	}
	if size < 1 {
		size = 20
	}
	if size > 100 {
		size = 100
	}

	return number, size, (number - 1) * size
}

func likePattern(keyword string) string {
	return "%" + escapeLike(keyword) + "%"
}

// recordMaster 写入主数据维护日志
func (s *AdminService) recordMaster(tx *gorm.DB, bizType, targetID, targetName, userID, action, actionName, content string) error {
	return tx.Create(&model.MasterLog{
		ID:         ulidx.New(),
		BizType:    bizType,
		TargetID:   targetID,
		TargetName: targetName,
		UserID:     userID,
		Action:     action,
		ActionName: actionName,
		Content:    content,
	}).Error
}

// recordPart 写入零件号维护日志
func (s *AdminService) recordPart(tx *gorm.DB, part *model.SupplierPart, userID, action, actionName, content string) error {
	return tx.Create(&model.PartLog{
		ID:           ulidx.New(),
		SupplierID:   part.SupplierID,
		SupplierName: part.SupplierName,
		PartNo:       part.PartNo,
		UserID:       userID,
		Action:       action,
		ActionName:   actionName,
		Content:      content,
	}).Error
}

// diffContent 生成「字段 旧值 → 新值」描述，无变化返回空串
func diffContent(pairs [][3]string) string {
	content := ""

	for _, pair := range pairs {
		if pair[1] == pair[2] {
			continue
		}

		oldValue := pair[1]
		newValue := pair[2]

		if oldValue == "" {
			oldValue = "（空）"
		}
		if newValue == "" {
			newValue = "（空）"
		}

		if content != "" {
			content += "；"
		}
		content += fmt.Sprintf("%s：%s → %s", pair[0], oldValue, newValue)
	}

	return content
}

func statusOf(value *int8, fallback int8) int8 {
	if value == nil {
		return fallback
	}

	return *value
}

var errNotFound = errors.New("记录不存在或已被删除")

// shortID 取 ULID 后 6 位，用于软删时改名释放唯一索引
func shortID(id string) string {
	if len(id) <= 6 {
		return id
	}

	return id[len(id)-6:]
}
