package handler

import (
	"strings"

	"github.com/gin-gonic/gin"

	"packsubmit/internal/model"
	"packsubmit/internal/pkg/resp"
)

type auditLogView struct {
	model.AuditLog
	UserName string `json:"user_name"`
}

func (h *Handler) AuditLogs(c *gin.Context) {
	page, size := pageParams(c)

	q := h.DB.Model(&model.AuditLog{})

	if action := strings.TrimSpace(c.Query("action")); action != "" {
		q = q.Where("action = ?", action)
	}

	if proposalID := strings.TrimSpace(c.Query("proposal_id")); proposalID != "" {
		q = q.Where("proposal_id = ?", proposalID)
	}

	if userID := strings.TrimSpace(c.Query("user_id")); userID != "" {
		q = q.Where("user_id = ?", userID)
	}

	if keyword := strings.TrimSpace(c.Query("keyword")); keyword != "" {
		escaped := strings.NewReplacer("\\", "\\\\", "%", "\\%", "_", "\\_").Replace(keyword)
		like := "%" + escaped + "%"
		q = q.Where("(action_name LIKE ? OR content LIKE ?)", like, like)
	}

	if start := strings.TrimSpace(c.Query("start_date")); start != "" {
		q = q.Where("created_at >= ?", start+" 00:00:00")
	}

	if end := strings.TrimSpace(c.Query("end_date")); end != "" {
		q = q.Where("created_at <= ?", end+" 23:59:59")
	}

	var total int64
	if err := q.Count(&total).Error; err != nil {
		resp.Error(c, err)
		return
	}

	var logs []model.AuditLog
	if err := q.Order("created_at DESC").Offset((page - 1) * size).Limit(size).Find(&logs).Error; err != nil {
		resp.Error(c, err)
		return
	}

	ids := make([]string, 0, len(logs))
	seen := make(map[string]struct{}, len(logs))

	for _, log := range logs {
		if _, ok := seen[log.UserID]; ok {
			continue
		}
		seen[log.UserID] = struct{}{}
		ids = append(ids, log.UserID)
	}

	names := make(map[string]string, len(ids))

	if len(ids) > 0 {
		var users []model.User
		if err := h.DB.Where("id IN ?", ids).Find(&users).Error; err == nil {
			for _, user := range users {
				label := user.RealName
				if label == "" {
					label = user.Username
				}
				names[user.ID] = label
			}
		}
	}

	views := make([]auditLogView, 0, len(logs))

	for _, log := range logs {
		views = append(views, auditLogView{AuditLog: log, UserName: names[log.UserID]})
	}

	resp.OKPage(c, views, total, page, size)
}
