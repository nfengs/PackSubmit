package handler

import (
	"strings"

	"github.com/gin-gonic/gin"

	"packsubmit/internal/middleware"
	"packsubmit/internal/pkg/resp"
)

type loginRequest struct {
	Username string `json:"username"`
	Password string `json:"password"`
}

type passwordRequest struct {
	OldPassword     string `json:"old_password"`
	NewPassword     string `json:"new_password"`
	ConfirmPassword string `json:"confirm_password"`
}

func (h *Handler) Login(c *gin.Context) {
	var request loginRequest
	if !bindJSON(c, &request) {
		return
	}

	session, err := h.Auth.Login(request.Username, request.Password, c.ClientIP())
	if err != nil {
		resp.Error(c, err)
		return
	}

	c.JSON(200, resp.Body{Code: resp.CodeOK, Message: "登录成功", Data: session})
}

func (h *Handler) Refresh(c *gin.Context) {
	raw := strings.TrimSpace(strings.TrimPrefix(c.GetHeader("Authorization"), "Bearer "))

	session, err := h.Auth.Refresh(raw)
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, session)
}

func (h *Handler) Logout(c *gin.Context) {
	user := currentUser(c)
	h.Auth.Logout(middleware.Token(c), user.ID, c.ClientIP())

	resp.OKMsg(c, "已退出登录")
}

func (h *Handler) Me(c *gin.Context) {
	resp.OK(c, h.Auth.Profile(currentUser(c)))
}

func (h *Handler) ChangePassword(c *gin.Context) {
	var request passwordRequest
	if !bindJSON(c, &request) {
		return
	}

	if request.NewPassword != request.ConfirmPassword {
		resp.Fail(c, resp.CodeParam, "两次输入的新密码不一致")
		return
	}

	token, err := h.Auth.ChangePassword(currentUser(c), request.OldPassword, request.NewPassword, c.ClientIP())
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, gin.H{"token": token})
}
