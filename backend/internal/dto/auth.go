package dto

import (
	"packsubmit/internal/model"
	"packsubmit/internal/pkg/auth"
)

// LoginInput 登录入参
type LoginInput struct {
	Username string `json:"username" binding:"required,max=50"`
	Password string `json:"password" binding:"required,max=72"`
}

// PasswordInput 自助修改密码入参（复杂度由 PasswordPolicy 统一校验）
type PasswordInput struct {
	OldPassword     string `json:"old_password" binding:"required"`
	NewPassword     string `json:"new_password" binding:"required"`
	ConfirmPassword string `json:"confirm_password" binding:"required"`
}

// Session 登录态：token + 用户 + 动态菜单/路由/权限
type Session struct {
	Token       string      `json:"token,omitempty"`
	User        *model.User `json:"user"`
	Menus       []auth.Menu `json:"menus"`
	Routes      []auth.Menu `json:"routes"`
	Permissions []string    `json:"permissions"`
}
