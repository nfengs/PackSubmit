package handler

import (
	"net/http"
	"net/url"
	"strconv"

	"github.com/gin-gonic/gin"

	"packsubmit/internal/dto"
	"packsubmit/internal/pkg/resp"
	"packsubmit/internal/service"
)

func (h *Handler) ProposalList(c *gin.Context) {
	page, size := pageParams(c)

	filter := service.ListFilter{
		Page:       page,
		PageSize:   size,
		Status:     parseStatuses(c.Query("status")),
		Keyword:    c.Query("keyword"),
		Customer:   c.Query("customer"),
		Supplier:   c.Query("supplier"),
		Project:    c.Query("project"),
		PartNo:     c.Query("part_no"),
		WithPallet: c.Query("with_pallet"),
		DateField:  c.Query("date_field"),
		StartDate:  c.Query("start_date"),
		EndDate:    c.Query("end_date"),
	}

	list, total, err := h.Proposals.List(currentUser(c), filter)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKPage(c, list, total, page, size)
}

func (h *Handler) ProposalStatistics(c *gin.Context) {
	stats, err := h.Proposals.Statistics(currentUser(c))
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, stats)
}

func (h *Handler) ProposalProjects(c *gin.Context) {
	projects, err := h.Proposals.Projects(currentUser(c))
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, projects)
}

func (h *Handler) ProposalDetail(c *gin.Context) {
	detail, err := h.Proposals.Get(currentUser(c), c.Param("id"))
	if err != nil {
		resp.Error(c, err)
		return
	}

	_ = service.Record(h.DB, service.AuditEntry{
		ProposalID: &detail.ID, UserID: currentUser(c).ID, Action: "view",
		Version: detail.Version, Content: "查看方案：" + detail.PartNo, IP: c.ClientIP(),
	})

	resp.OK(c, detail)
}

func (h *Handler) ProposalCreate(c *gin.Context) {
	var input dto.ProposalCreateInput
	if !bindJSON(c, &input) {
		return
	}

	detail, err := h.Proposals.Create(currentUser(c), &input, c.ClientIP())
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, detail)
}

func (h *Handler) ProposalUpdate(c *gin.Context) {
	var input dto.ProposalUpdateInput
	if !bindJSON(c, &input) {
		return
	}

	detail, err := h.Proposals.Update(currentUser(c), c.Param("id"), &input, c.ClientIP())
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, detail)
}

func (h *Handler) ProposalDelete(c *gin.Context) {
	if err := h.Proposals.Delete(currentUser(c), c.Param("id"), c.ClientIP()); err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKMsg(c, "删除成功")
}

func (h *Handler) ProposalSubmit(c *gin.Context) {
	if err := h.Proposals.Submit(currentUser(c), c.Param("id"), c.ClientIP()); err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKMsg(c, "已提交评审")
}

func (h *Handler) ProposalWithdraw(c *gin.Context) {
	if err := h.Proposals.Withdraw(currentUser(c), c.Param("id"), c.ClientIP()); err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKMsg(c, "已撤回，退回草稿")
}

func (h *Handler) ProposalReview(c *gin.Context) {
	var input dto.ReviewInput
	if !bindJSON(c, &input) {
		return
	}

	if err := h.Proposals.Review(currentUser(c), c.Param("id"), input, c.ClientIP()); err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKMsg(c, "评审完成")
}

func (h *Handler) ProposalChange(c *gin.Context) {
	detail, err := h.Proposals.Change(currentUser(c), c.Param("id"), c.ClientIP())
	if err != nil {
		resp.Error(c, err)
		return
	}

	c.JSON(http.StatusOK, resp.Body{Code: resp.CodeOK, Message: "已发起变更", Data: detail})
}

func (h *Handler) ProposalArchive(c *gin.Context) {
	var input dto.ArchiveInput
	if !bindJSON(c, &input) {
		return
	}

	count, err := h.Proposals.Archive(currentUser(c), input.IDs, c.ClientIP())
	if err != nil {
		resp.Error(c, err)
		return
	}

	c.JSON(http.StatusOK, resp.Body{
		Code: resp.CodeOK, Message: "已归档 " + strconv.Itoa(count) + " 条方案",
		Data: gin.H{"count": count},
	})
}

// AdminProposalReport 全量详细方案（52 字段明细，供报表页与导出使用）
func (h *Handler) AdminProposalReport(c *gin.Context) {
	page, size := pageParams(c)

	// 报表页单页最多 500 条
	if size > 500 {
		size = 500
	}

	filter := service.ListFilter{
		Page:       page,
		PageSize:   size,
		Status:     parseStatuses(c.Query("status")),
		Keyword:    c.Query("keyword"),
		Customer:   c.Query("customer"),
		Supplier:   c.Query("supplier"),
		Project:    c.Query("project"),
		PartNo:     c.Query("part_no"),
		WithPallet: c.Query("with_pallet"),
		DateField:  c.Query("date_field"),
		StartDate:  c.Query("start_date"),
		EndDate:    c.Query("end_date"),
	}

	list, total, err := h.Proposals.Report(currentUser(c), filter)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKPage(c, list, total, page, size)
}

func (h *Handler) ProposalExportExcel(c *gin.Context) {
	user := currentUser(c)

	detail, err := h.Proposals.Get(user, c.Param("id"))
	if err != nil {
		resp.Error(c, err)
		return
	}

	proposal := detail.Proposal
	data, filename, err := h.Export.Excel(&proposal)
	if err != nil {
		resp.Error(c, err)
		return
	}

	_ = service.Record(h.DB, service.AuditEntry{
		ProposalID: &proposal.ID, UserID: user.ID, Action: "export",
		Version: proposal.Version, Content: "导出包装确认单：" + filename, IP: c.ClientIP(),
	})

	c.Header("Content-Disposition", "attachment; filename*=UTF-8''"+url.PathEscape(filename))
	c.Data(http.StatusOK, "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", data)
}

func itoa(value int) string {
	if value == 0 {
		return "0"
	}

	digits := make([]byte, 0, 8)
	for value > 0 {
		digits = append([]byte{byte('0' + value%10)}, digits...)
		value /= 10
	}

	return string(digits)
}
