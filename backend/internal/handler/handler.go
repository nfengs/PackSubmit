package handler

import (
	"strconv"
	"strings"

	"github.com/gin-gonic/gin"
	"gorm.io/gorm"

	"packsubmit/internal/middleware"
	"packsubmit/internal/model"
	"packsubmit/internal/pkg/resp"
	"packsubmit/internal/service"
)

type Handler struct {
	DB          *gorm.DB
	Auth        *service.AuthService
	Proposals   *service.ProposalService
	Attachments *service.AttachmentService
	MasterData  *service.MasterDataService
	Contacts    *service.ContactService
	Export      *service.ExportService
}

func New(db *gorm.DB, authService *service.AuthService, proposals *service.ProposalService,
	attachments *service.AttachmentService, masterData *service.MasterDataService,
	contacts *service.ContactService, export *service.ExportService) *Handler {
	return &Handler{
		DB: db, Auth: authService, Proposals: proposals, Attachments: attachments,
		MasterData: masterData, Contacts: contacts, Export: export,
	}
}

func currentUser(c *gin.Context) *model.User {
	return middleware.CurrentUser(c)
}

func bindJSON(c *gin.Context, target any) bool {
	if err := c.ShouldBindJSON(target); err != nil {
		resp.Fail(c, resp.CodeParam, "请求参数格式不正确")
		return false
	}

	return true
}

func pageParams(c *gin.Context) (int, int) {
	page, _ := strconv.Atoi(c.DefaultQuery("page", "1"))
	size, _ := strconv.Atoi(c.DefaultQuery("page_size", "20"))

	if page < 1 {
		page = 1
	}
	if size < 1 {
		size = 20
	}
	if size > 100 {
		size = 100
	}

	return page, size
}

func parseStatuses(raw string) []int8 {
	raw = strings.TrimSpace(raw)
	if raw == "" {
		return nil
	}

	statuses := make([]int8, 0, 4)

	for _, part := range strings.Split(raw, ",") {
		value, err := strconv.Atoi(strings.TrimSpace(part))
		if err != nil || value < 0 || value > 5 {
			continue
		}
		statuses = append(statuses, int8(value))
	}

	return statuses
}
