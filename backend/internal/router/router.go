package router

import (
	"net/http"
	"os"
	"path/filepath"
	"strings"

	"github.com/gin-gonic/gin"
	"gorm.io/gorm"

	"packsubmit/internal/config"
	"packsubmit/internal/handler"
	"packsubmit/internal/middleware"
	"packsubmit/internal/pkg/auth"
	"packsubmit/internal/pkg/resp"
	"packsubmit/internal/version"
)

func New(h *handler.Handler, manager *auth.Manager, db *gorm.DB, cfg *config.Config) *gin.Engine {
	engine := gin.New()
	engine.Use(gin.Logger(), middleware.Recovery(), middleware.SecurityHeaders(), middleware.CORS(cfg.CORS.AllowOrigins))

	api := engine.Group("/api/v1")

	api.GET("/health", func(c *gin.Context) {
		resp.OK(c, gin.H{
			"status":      "ok",
			"version":     version.Version,
			"released_at": version.ReleasedAt,
		})
	})

	api.POST("/auth/login", h.Login)
	api.POST("/auth/refresh", h.Refresh)

	authorized := api.Group("")
	authorized.Use(middleware.Auth(manager, db))
	{
		authorized.POST("/auth/logout", h.Logout)
		authorized.GET("/auth/me", h.Me)
		authorized.PUT("/auth/password", h.ChangePassword)

		authorized.GET("/dicts/options", h.DictOptions)
		authorized.GET("/master/customers", h.Customers)
		authorized.GET("/master/suppliers", h.Suppliers)
		authorized.GET("/master/my-supplier", h.MySupplier)
		authorized.GET("/master/parts", h.Parts)

		contacts := authorized.Group("/supplier-contacts")
		contacts.Use(middleware.RequirePermission(auth.PermContactManage))
		{
			contacts.GET("", h.ContactList)
			contacts.POST("", h.ContactSave)
			contacts.PUT("/:id", h.ContactUpdate)
			contacts.DELETE("/:id", h.ContactDelete)
		}

		proposals := authorized.Group("/proposals")
		proposals.Use(middleware.RequirePermission(auth.PermProposalView))
		{
			proposals.GET("", h.ProposalList)
			proposals.GET("/statistics", h.ProposalStatistics)
			proposals.GET("/projects", h.ProposalProjects)
			proposals.GET("/:id", h.ProposalDetail)
			proposals.POST("", middleware.RequirePermission(auth.PermProposalAll), h.ProposalCreate)
			proposals.PUT("/:id", middleware.RequirePermission(auth.PermProposalUpdate), h.ProposalUpdate)
			proposals.DELETE("/:id", middleware.RequirePermission(auth.PermProposalDelete), h.ProposalDelete)
			proposals.POST("/:id/submit", middleware.RequirePermission(auth.PermProposalSubmit), h.ProposalSubmit)
			proposals.POST("/:id/withdraw", middleware.RequirePermission(auth.PermProposalWithdraw), h.ProposalWithdraw)
			proposals.POST("/:id/review", middleware.RequirePermission(auth.PermProposalReview), h.ProposalReview)
			proposals.POST("/:id/change", middleware.RequirePermission(auth.PermProposalChange), h.ProposalChange)
			proposals.GET("/:id/export/excel", middleware.RequirePermission(auth.PermProposalExport), h.ProposalExportExcel)
		}

		attachments := authorized.Group("/attachments")
		{
			attachments.POST("/upload", h.AttachmentUpload)
			attachments.POST("/bind", h.AttachmentBind)
			attachments.GET("/:id/download", h.AttachmentDownload)
			attachments.GET("/:id/preview", h.AttachmentPreview)
			attachments.DELETE("/:id", h.AttachmentDelete)
		}

		admin := authorized.Group("/admin")
		admin.Use(middleware.RequireRole(auth.RoleAdmin))
		{
			admin.POST("/proposals/archive", middleware.RequirePermission(auth.PermProposalArchive), h.ProposalArchive)
			admin.GET("/proposals/detail", middleware.RequirePermission(auth.PermReportView), h.AdminProposalReport)
			admin.GET("/audit-logs", middleware.RequirePermission(auth.PermAuditView), h.AuditLogs)

			users := admin.Group("/users")
			users.Use(middleware.RequirePermission(auth.PermUserManage))
			{
				users.GET("", h.AdminUsers)
				users.GET("/template", h.AdminUserTemplate)
				users.POST("/import", h.AdminUserImport)
				users.POST("", h.AdminUserCreate)
				users.PUT("/:id", h.AdminUserUpdate)
				users.PATCH("/:id/status", h.AdminUserStatus)
				users.POST("/:id/reset-password", h.AdminUserResetPassword)
				users.GET("/:id/logs", h.AdminUserLogs)
			}

			customers := admin.Group("/customers")
			customers.Use(middleware.RequirePermission(auth.PermMasterManage))
			{
				customers.GET("", h.AdminCustomers)
				customers.GET("/check-name", h.AdminCustomerCheckName)
				customers.POST("", h.AdminCustomerCreate)
				customers.PUT("/:id", h.AdminCustomerUpdate)
				customers.DELETE("/:id", h.AdminCustomerDelete)
				customers.GET("/:id/logs", h.AdminCustomerLogs)
			}

			suppliers := admin.Group("/suppliers")
			suppliers.Use(middleware.RequirePermission(auth.PermMasterManage))
			{
				suppliers.GET("", h.AdminSuppliers)
				suppliers.GET("/template", h.AdminSupplierTemplate)
				suppliers.POST("/import", h.AdminSupplierImport)
				suppliers.GET("/check-name", h.AdminSupplierCheckName)
				suppliers.POST("", h.AdminSupplierCreate)
				suppliers.PUT("/:id", h.AdminSupplierUpdate)
				suppliers.DELETE("/:id", h.AdminSupplierDelete)
				suppliers.GET("/:id/logs", h.AdminSupplierLogs)
			}

			parts := admin.Group("/parts")
			parts.Use(middleware.RequirePermission(auth.PermMasterManage))
			{
				parts.GET("", h.AdminParts)
				parts.GET("/template", h.AdminPartTemplate)
				parts.POST("/import", h.AdminPartImport)
				parts.POST("/batch-preview", h.AdminPartBatchPreview)
				parts.POST("/batch-update", h.AdminPartBatchUpdate)
				parts.PUT("/:id", h.AdminPartUpdate)
				parts.DELETE("/:id", h.AdminPartDelete)
				parts.GET("/:id/logs", h.AdminPartLogs)
			}

			dicts := admin.Group("/dicts")
			dicts.Use(middleware.RequirePermission(auth.PermMasterManage))
			{
				dicts.GET("", h.AdminDicts)
				dicts.POST("", h.AdminDictCreate)
				dicts.PUT("/:id", h.AdminDictUpdate)
				dicts.DELETE("/:id", h.AdminDictDelete)
			}
		}
	}

	serveStatic(engine, cfg.Server.StaticDir)

	return engine
}

// serveStatic 单二进制部署时托管前端构建产物
func serveStatic(engine *gin.Engine, dir string) {
	if strings.TrimSpace(dir) == "" {
		return
	}

	index := filepath.Join(dir, "index.html")

	if _, err := os.Stat(index); err != nil {
		return
	}

	engine.Static("/assets", filepath.Join(dir, "assets"))
	engine.StaticFile("/favicon.ico", filepath.Join(dir, "favicon.ico"))

	engine.NoRoute(func(c *gin.Context) {
		if strings.HasPrefix(c.Request.URL.Path, "/api/") || c.Request.Method != http.MethodGet {
			c.JSON(http.StatusNotFound, resp.Body{Code: resp.CodeBiz, Message: "接口不存在"})
			return
		}

		c.File(index)
	})
}
