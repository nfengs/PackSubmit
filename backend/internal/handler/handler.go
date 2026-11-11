package handler

import (
	"errors"
	"fmt"
	"reflect"
	"strconv"
	"strings"

	"github.com/gin-gonic/gin"
	"github.com/gin-gonic/gin/binding"
	"github.com/go-playground/validator/v10"
	"gorm.io/gorm"

	"packsubmit/internal/middleware"
	"packsubmit/internal/model"
	"packsubmit/internal/pkg/resp"
	"packsubmit/internal/service"
)

// 校验错误使用 JSON 字段名，前端与文档看到的是同一套命名
func init() {
	if engine, ok := binding.Validator.Engine().(*validator.Validate); ok {
		engine.RegisterTagNameFunc(func(field reflect.StructField) string {
			name := strings.SplitN(field.Tag.Get("json"), ",", 2)[0]

			if name == "" || name == "-" {
				return field.Name
			}

			return name
		})
	}
}

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

// bindJSON 统一绑定 + 参数校验；失败时返回 1001 与可读文案
func bindJSON(c *gin.Context, target any) bool {
	if err := c.ShouldBindJSON(target); err != nil {
		resp.Fail(c, resp.CodeParam, validationMessage(err))
		return false
	}

	return true
}

// 常见字段的中文名；未登记时回退为 JSON 字段名
var fieldLabels = map[string]string{
	"username":         "账号",
	"password":         "密码",
	"old_password":     "原密码",
	"new_password":     "新密码",
	"confirm_password": "确认密码",
	"status":           "评审结果",
	"remark":           "评审意见",
	"ids":              "方案列表",
	"supplier_id":      "供应商",
	"part_no":          "零件号",
	"name":             "联系人姓名",
	"email":            "邮箱",
	"attachment_id":    "附件",
	"proposal_id":      "方案",

	"customer_name":         "客户名称",
	"customer_address":      "客户地址",
	"customer_contact":      "客户联系人",
	"customer_phone":        "客户电话",
	"customer_email":        "客户邮箱",
	"customer_fax":          "客户传真",
	"contact":               "供应商联系人",
	"phone":                 "供应商电话",
	"supplier_email":        "供应商邮箱",
	"supplier_fax":          "供应商传真",
	"project_model":         "项目车型",
	"part_name":             "零件名称",
	"part_material":         "零件材料",
	"pack_box_no":           "包装箱编号",
	"pack_material":         "包装箱材料",
	"packaging_instruction": "包装操作说明",
	"description":           "方案说明",
}

func validationMessage(err error) string {
	var invalid validator.ValidationErrors

	if !errors.As(err, &invalid) || len(invalid) == 0 {
		return "请求参数格式不正确"
	}

	field := invalid[0]
	label := fieldLabels[field.Field()]

	if label == "" {
		label = field.Field()
	}

	switch field.Tag() {
	case "required":
		return "缺少必填项：" + label
	case "max":
		return fmt.Sprintf("%s长度不能超过 %s", label, field.Param())
	case "min":
		return fmt.Sprintf("%s不能少于 %s 项", label, field.Param())
	case "oneof":
		return label + "取值不合法"
	case "email":
		return label + "格式不正确"
	case "gte":
		return fmt.Sprintf("%s不能小于 %s", label, field.Param())
	case "gt":
		return fmt.Sprintf("%s必须大于 %s", label, field.Param())
	default:
		return label + "参数不合法"
	}
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
