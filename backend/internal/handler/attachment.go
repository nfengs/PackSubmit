package handler

import (
	"net/http"
	"net/url"

	"github.com/gin-gonic/gin"

	"packsubmit/internal/dto"
	"packsubmit/internal/pkg/resp"
)

func (h *Handler) AttachmentUpload(c *gin.Context) {
	header, err := c.FormFile("file")
	if err != nil {
		resp.Fail(c, resp.CodeParam, "请选择要上传的文件")
		return
	}

	proposalID := c.PostForm("proposal_id")
	attachType := c.PostForm("attach_type")

	attachment, reused, err := h.Attachments.Upload(currentUser(c), header, proposalID, attachType)
	if err != nil {
		resp.Error(c, err)
		return
	}

	message := "上传成功"
	if reused {
		message = "上传成功（已复用服务器上的相同文件）"
	}

	c.JSON(http.StatusOK, resp.Body{
		Code: resp.CodeOK, Message: message,
		Data: gin.H{"attachment": attachment, "reused": reused},
	})
}

func (h *Handler) AttachmentBind(c *gin.Context) {
	var input dto.BindAttachmentInput
	if !bindJSON(c, &input) {
		return
	}

	if err := h.Attachments.Bind(currentUser(c), input.AttachmentID, input.ProposalID); err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKMsg(c, "绑定成功")
}

func (h *Handler) AttachmentDownload(c *gin.Context) {
	attachment, abs, err := h.Attachments.Resolve(currentUser(c), c.Param("id"))
	if err != nil {
		resp.Error(c, err)
		return
	}

	c.Header("Content-Disposition", "attachment; filename*=UTF-8''"+url.PathEscape(attachment.FileName))
	c.Header("Content-Type", attachment.MimeType)
	c.File(abs)
}

func (h *Handler) AttachmentPreview(c *gin.Context) {
	attachment, abs, err := h.Attachments.Resolve(currentUser(c), c.Param("id"))
	if err != nil {
		resp.Error(c, err)
		return
	}

	switch attachment.FileExt {
	case "pdf", "png", "jpg", "jpeg":
	default:
		resp.Fail(c, resp.CodeBiz, "该文件类型不支持在线预览，请下载后查看")
		return
	}

	c.Header("Content-Type", attachment.MimeType)
	c.File(abs)
}

func (h *Handler) AttachmentDelete(c *gin.Context) {
	if err := h.Attachments.Delete(currentUser(c), c.Param("id"), c.ClientIP()); err != nil {
		resp.Error(c, err)
		return
	}

	resp.OKMsg(c, "删除成功")
}
