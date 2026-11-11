package middleware

import (
	"errors"
	"net/http"
	"strings"

	"github.com/gin-gonic/gin"
	"gorm.io/gorm"

	"packsubmit/internal/model"
	"packsubmit/internal/pkg/auth"
	"packsubmit/internal/pkg/resp"
)

const contextUserKey = "current_user"

// Recovery 捕获 panic，返回统一错误结构
func Recovery() gin.HandlerFunc {
	return gin.CustomRecovery(func(c *gin.Context, err any) {
		resp.Fail(c, resp.CodeServer, "服务器内部错误")
		c.Abort()
	})
}

// SecurityHeaders 移除版本信息并补充基础安全头
func SecurityHeaders() gin.HandlerFunc {
	return func(c *gin.Context) {
		header := c.Writer.Header()
		header.Set("X-Content-Type-Options", "nosniff")
		header.Set("X-Frame-Options", "SAMEORIGIN")
		header.Set("Referrer-Policy", "no-referrer")
		header.Set("Permissions-Policy", "geolocation=(), microphone=(), camera=()")
		header.Del("X-Powered-By")

		c.Next()
	}
}

// CORS 白名单跨域；未配置时不开放跨域
func CORS(allowOrigins []string) gin.HandlerFunc {
	allowed := make(map[string]struct{}, len(allowOrigins))

	for _, origin := range allowOrigins {
		allowed[strings.TrimSpace(origin)] = struct{}{}
	}

	return func(c *gin.Context) {
		origin := c.GetHeader("Origin")

		if origin != "" {
			if _, ok := allowed[origin]; ok {
				header := c.Writer.Header()
				header.Set("Access-Control-Allow-Origin", origin)
				header.Set("Access-Control-Allow-Credentials", "true")
				header.Set("Access-Control-Allow-Headers", "Authorization, Content-Type, X-Requested-With")
				header.Set("Access-Control-Allow-Methods", "GET, POST, PUT, PATCH, DELETE, OPTIONS")
				header.Set("Access-Control-Max-Age", "3600")
			}

			c.Writer.Header().Add("Vary", "Origin")
		}

		if c.Request.Method == http.MethodOptions {
			c.AbortWithStatus(http.StatusNoContent)
			return
		}

		c.Next()
	}
}

// Auth 校验 token 并回查用户状态与 token 版本
func Auth(manager *auth.Manager, db *gorm.DB) gin.HandlerFunc {
	return func(c *gin.Context) {
		raw := strings.TrimSpace(strings.TrimPrefix(c.GetHeader("Authorization"), "Bearer "))
		if raw == "" {
			resp.Fail(c, resp.CodeUnauthorized, "请先登录")
			c.Abort()
			return
		}

		claims, err := manager.Parse(raw, false)
		if err != nil {
			resp.Fail(c, resp.CodeUnauthorized, "登录状态已失效，请重新登录")
			c.Abort()
			return
		}

		var user model.User
		if err := db.First(&user, "id = ?", claims.UID).Error; err != nil {
			if errors.Is(err, gorm.ErrRecordNotFound) {
				resp.Fail(c, resp.CodeUnauthorized, "账号不存在或已被删除")
			} else {
				resp.Fail(c, resp.CodeServer, "鉴权服务异常")
			}
			c.Abort()
			return
		}

		if user.Status != model.UserEnabled {
			resp.Fail(c, resp.CodeForbidden, "账号已被禁用，请联系管理员")
			c.Abort()
			return
		}

		// 改密 / 重置密码后旧 token 立即失效
		if claims.Ver != user.TokenVersion {
			resp.Fail(c, resp.CodeUnauthorized, "登录状态已失效，请重新登录")
			c.Abort()
			return
		}

		c.Set(contextUserKey, &user)
		c.Set("token_raw", raw)
		c.Next()
	}
}

// RequirePermission 接口级权限校验
func RequirePermission(perm string) gin.HandlerFunc {
	return func(c *gin.Context) {
		user := CurrentUser(c)
		if user == nil {
			resp.Fail(c, resp.CodeUnauthorized, "请先登录")
			c.Abort()
			return
		}

		if !auth.HasPermission(user.Role, perm) {
			resp.Fail(c, resp.CodeForbidden, "无操作权限")
			c.Abort()
			return
		}

		c.Next()
	}
}

// RequireRole 固定角色校验（如管理员接口组）
func RequireRole(roles ...int8) gin.HandlerFunc {
	allowed := make(map[int8]struct{}, len(roles))
	for _, role := range roles {
		allowed[role] = struct{}{}
	}

	return func(c *gin.Context) {
		user := CurrentUser(c)
		if user == nil {
			resp.Fail(c, resp.CodeUnauthorized, "请先登录")
			c.Abort()
			return
		}

		if _, ok := allowed[user.Role]; !ok {
			resp.Fail(c, resp.CodeForbidden, "无操作权限")
			c.Abort()
			return
		}

		c.Next()
	}
}

// CurrentUser 取当前登录用户
func CurrentUser(c *gin.Context) *model.User {
	value, exists := c.Get(contextUserKey)
	if !exists {
		return nil
	}

	user, ok := value.(*model.User)
	if !ok {
		return nil
	}

	return user
}

// Token 取当前请求 token（退出时拉黑用）
func Token(c *gin.Context) string {
	value, _ := c.Get("token_raw")
	token, _ := value.(string)

	return token
}
