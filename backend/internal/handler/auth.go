package handler

import (
	"strings"

	"github.com/gin-gonic/gin"

	"packsubmit/internal/dto"
	"packsubmit/internal/middleware"
	"packsubmit/internal/pkg/resp"
)

func (h *Handler) Login(c *gin.Context) {
	var input dto.LoginInput
	if !bindJSON(c, &input) {
		return
	}

	session, err := h.Auth.Login(input.Username, input.Password, c.ClientIP())
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
	var input dto.PasswordInput
	if !bindJSON(c, &input) {
		return
	}

	if input.NewPassword != input.ConfirmPassword {
		resp.Fail(c, resp.CodeParam, "两次输入的新密码不一致")
		return
	}

	token, err := h.Auth.ChangePassword(currentUser(c), input.OldPassword, input.NewPassword, c.ClientIP())
	if err != nil {
		resp.Error(c, err)
		return
	}

	resp.OK(c, gin.H{"token": token})
}
