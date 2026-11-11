package handler

import (
	"mime/multipart"
	"net/http"
	"net/url"
	"strconv"
	"strings"

	"github.com/gin-gonic/gin"

	"packsubmit/internal/dto"
	"packsubmit/internal/pkg/resp"
	"packsubmit/internal/service"
)

const maxImportSize = 10 << 20

const xlsxMime = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"

func bindFile(c *gin.Context) (multipart.File, *multipart.FileHeader, bool) {
	file, header, err := c.Request.FormFile("file")
	if err != nil {
		resp.Fail(c, resp.CodeParam, "请选择要导入的文件")
		return nil, nil, false
	}

	if header.Size > maxImportSize {
		_ = file.Close()
		resp.Fail(c, resp.CodeParam, "文件不能超过 10MB")
		return nil, nil, false
	}

	return file, header, true
}

func sendTemplate(c *gin.Context, header []string, fileName string) {
	data, err := service.BuildTemplate(header)
	if err != nil {
		resp.Fail(c, resp.CodeServer, "生成模板失败")
		return
	}

	c.Header("Content-Disposition", "attachment; filename*=UTF-8''"+url.PathEscape(fileName))
	c.Data(http.StatusOK, xlsxMime, data)
}

func queryInt(c *gin.Context, key string) int {
	value, _ := strconv.Atoi(strings.TrimSpace(c.Query(key)))

	return value
}

// ------------------------------------------------------------------
// 用户管理
// ------------------------------------------------------------------

func (h *Handler) AdminUsers(c *gin.Context) {
	page, size := pageParams(c)

	statusRaw, statusSet := c.GetQuery("status")
	status := 0

	if statusSet {
		status, _ = strconv.Atoi(strings.TrimSpace(statusRaw))
	}

	items, total, err := h.Admin.ListUsers(service.UserFilter{
		Page:      service.Page{Page: page, PageSize: size},
		Keyword:   c.Query("keyword"),
		Role:      int8(queryInt(c, "role")),
		Status:    int8(status),
		StatusSet: statusSet,
	})
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKPage(c, items, total, page, size)
}

func (h *Handler) AdminUserCreate(c *gin.Context) {
	var input dto.UserInput
	if !bindJSON(c, &input) {
		return
	}

	user, err := h.Admin.CreateUser(currentUser(c), input)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, user)
}

func (h *Handler) AdminUserUpdate(c *gin.Context) {
	var input dto.UserInput
	if !bindJSON(c, &input) {
		return
	}

	user, err := h.Admin.UpdateUser(currentUser(c), c.Param("id"), input)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, user)
}

func (h *Handler) AdminUserStatus(c *gin.Context) {
	var input dto.UserStatusInput
	if !bindJSON(c, &input) {
		return
	}

	if err := h.Admin.UpdateUserStatus(currentUser(c), c.Param("id"), input.Status); err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKMsg(c, "状态已更新")
}

func (h *Handler) AdminUserResetPassword(c *gin.Context) {
	var input dto.ResetPasswordInput
	if !bindJSON(c, &input) {
		return
	}

	if err := h.Admin.ResetUserPassword(currentUser(c), c.Param("id"), input.Password); err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKMsg(c, "密码已重置，该账号旧登录状态已失效")
}

func (h *Handler) AdminUserTemplate(c *gin.Context) {
	sendTemplate(c, []string{"登录账号", "姓名", "角色", "部门/供应商", "联系电话", "初始密码"}, "用户导入模板.xlsx")
}

func (h *Handler) AdminUserImport(c *gin.Context) {
	file, _, ok := bindFile(c)
	if !ok {
		return
	}
	defer file.Close()

	rows, err := service.ParseXlsx(file)
	if err != nil {
		resp.Error(c, err)
		return
	}

	result, err := h.Admin.ImportUsers(currentUser(c), rows)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, result)
}

func (h *Handler) AdminUserLogs(c *gin.Context) {
	logs, err := h.Admin.UserLogs(c.Param("id"))
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, logs)
}

// ------------------------------------------------------------------
// 客户
// ------------------------------------------------------------------

func (h *Handler) AdminCustomers(c *gin.Context) {
	page, size := pageParams(c)

	items, total, err := h.Admin.ListCustomers(c.Query("keyword"), service.Page{Page: page, PageSize: size})
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKPage(c, items, total, page, size)
}

func (h *Handler) AdminCustomerCreate(c *gin.Context) {
	var input dto.CustomerInput
	if !bindJSON(c, &input) {
		return
	}

	customer, err := h.Admin.CreateCustomer(currentUser(c), input)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, customer)
}

func (h *Handler) AdminCustomerUpdate(c *gin.Context) {
	var input dto.CustomerInput
	if !bindJSON(c, &input) {
		return
	}

	customer, err := h.Admin.UpdateCustomer(currentUser(c), c.Param("id"), input)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, customer)
}

func (h *Handler) AdminCustomerDelete(c *gin.Context) {
	if err := h.Admin.DeleteCustomer(currentUser(c), c.Param("id")); err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKMsg(c, "删除成功")
}

func (h *Handler) AdminCustomerCheckName(c *gin.Context) {
	exists, err := h.Admin.CheckCustomerName(c.Query("name"), c.Query("exclude_id"))
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, gin.H{"exists": exists})
}

func (h *Handler) AdminCustomerLogs(c *gin.Context) {
	logs, err := h.Admin.CustomerLogs(c.Param("id"))
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, logs)
}

// ------------------------------------------------------------------
// 供应商
// ------------------------------------------------------------------

func (h *Handler) AdminSuppliers(c *gin.Context) {
	page, size := pageParams(c)

	items, total, err := h.Admin.ListSuppliers(c.Query("keyword"), service.Page{Page: page, PageSize: size})
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKPage(c, items, total, page, size)
}

func (h *Handler) AdminSupplierCreate(c *gin.Context) {
	var input dto.SupplierInput
	if !bindJSON(c, &input) {
		return
	}

	supplier, err := h.Admin.CreateSupplier(currentUser(c), input)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, supplier)
}

func (h *Handler) AdminSupplierUpdate(c *gin.Context) {
	var input dto.SupplierInput
	if !bindJSON(c, &input) {
		return
	}

	supplier, err := h.Admin.UpdateSupplier(currentUser(c), c.Param("id"), input)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, supplier)
}

func (h *Handler) AdminSupplierDelete(c *gin.Context) {
	if err := h.Admin.DeleteSupplier(currentUser(c), c.Param("id")); err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKMsg(c, "删除成功")
}

func (h *Handler) AdminSupplierCheckName(c *gin.Context) {
	exists, err := h.Admin.CheckSupplierName(c.Query("name"), c.Query("exclude_id"))
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, gin.H{"exists": exists})
}

func (h *Handler) AdminSupplierLogs(c *gin.Context) {
	logs, err := h.Admin.SupplierLogs(c.Param("id"))
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, logs)
}

func (h *Handler) AdminSupplierTemplate(c *gin.Context) {
	sendTemplate(c, []string{"供应商名称", "供应商代码", "供应商地址"}, "供应商导入模板.xlsx")
}

func (h *Handler) AdminSupplierImport(c *gin.Context) {
	file, _, ok := bindFile(c)
	if !ok {
		return
	}
	defer file.Close()

	rows, err := service.ParseXlsx(file)
	if err != nil {
		resp.Error(c, err)
		return
	}

	result, err := h.Admin.ImportSuppliers(currentUser(c), rows)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, result)
}

// ------------------------------------------------------------------
// 零件号
// ------------------------------------------------------------------

func (h *Handler) AdminParts(c *gin.Context) {
	page, size := pageParams(c)

	items, total, err := h.Admin.ListParts(c.Query("supplier_id"), c.Query("keyword"),
		service.Page{Page: page, PageSize: size})
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKPage(c, items, total, page, size)
}

func (h *Handler) AdminPartTemplate(c *gin.Context) {
	sendTemplate(c, []string{"供应商名称", "项目名称", "零件号", "零件名称"}, "指定零件号导入模板.xlsx")
}

func (h *Handler) AdminPartImport(c *gin.Context) {
	file, _, ok := bindFile(c)
	if !ok {
		return
	}
	defer file.Close()

	rows, err := service.ParseXlsx(file)
	if err != nil {
		resp.Error(c, err)
		return
	}

	result, err := h.Admin.ImportParts(currentUser(c), rows)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, result)
}

func (h *Handler) AdminPartBatchPreview(c *gin.Context) {
	file, _, ok := bindFile(c)
	if !ok {
		return
	}
	defer file.Close()

	rows, err := service.ParseXlsx(file)
	if err != nil {
		resp.Error(c, err)
		return
	}

	items, err := h.Admin.PreviewBatchParts(rows)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, items)
}

func (h *Handler) AdminPartBatchUpdate(c *gin.Context) {
	var input struct {
		Rows []dto.BatchPartItem `json:"rows" binding:"required,min=1"`
	}

	if !bindJSON(c, &input) {
		return
	}

	count, err := h.Admin.BatchUpdateParts(currentUser(c), input.Rows)
	if err != nil {
		resp.Error(c, err)
		return
	}

	if count == 0 {
		resp.Fail(c, resp.CodeBiz, "没有需要修改的内容")
		return
	}

	resp.OK(c, gin.H{"count": count})
}

func (h *Handler) AdminPartUpdate(c *gin.Context) {
	var input dto.PartInput
	if !bindJSON(c, &input) {
		return
	}

	part, err := h.Admin.UpdatePart(currentUser(c), c.Param("id"), input)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, part)
}

func (h *Handler) AdminPartDelete(c *gin.Context) {
	if err := h.Admin.DeletePart(currentUser(c), c.Param("id")); err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKMsg(c, "删除成功")
}

func (h *Handler) AdminPartLogs(c *gin.Context) {
	logs, err := h.Admin.PartLogs(c.Param("id"))
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, logs)
}

// ------------------------------------------------------------------
// 字典
// ------------------------------------------------------------------

func (h *Handler) AdminDicts(c *gin.Context) {
	page, size := pageParams(c)

	items, total, err := h.Admin.ListDicts(c.Query("dict_type"), c.Query("keyword"),
		service.Page{Page: page, PageSize: size})
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKPage(c, items, total, page, size)
}

func (h *Handler) AdminDictCreate(c *gin.Context) {
	var input dto.DictInput
	if !bindJSON(c, &input) {
		return
	}

	dict, err := h.Admin.CreateDict(currentUser(c), input)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, dict)
}

func (h *Handler) AdminDictUpdate(c *gin.Context) {
	var input dto.DictInput
	if !bindJSON(c, &input) {
		return
	}

	dict, err := h.Admin.UpdateDict(currentUser(c), c.Param("id"), input)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, dict)
}

func (h *Handler) AdminDictDelete(c *gin.Context) {
	if err := h.Admin.DeleteDict(currentUser(c), c.Param("id")); err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKMsg(c, "删除成功")
}
