package main

import (
	"context"
	"errors"
	"flag"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/gin-gonic/gin"

	"packsubmit/internal/config"
	"packsubmit/internal/database"
	"packsubmit/internal/handler"
	"packsubmit/internal/pkg/auth"
	"packsubmit/internal/router"
	"packsubmit/internal/service"
	"packsubmit/internal/version"
)

func main() {
	configPath := flag.String("config", "configs/config.yaml", "配置文件路径")
	migrate := flag.Bool("migrate", false, "执行数据库迁移")
	seed := flag.Bool("seed", false, "初始化演示数据（仅空库生效）")
	flag.Parse()

	cfg, err := config.Load(*configPath)
	if err != nil {
		log.Fatalf("加载配置失败: %v", err)
	}

	if cfg.Server.Mode == "release" {
		gin.SetMode(gin.ReleaseMode)
	}

	if err := database.EnsureDatabase(cfg); err != nil {
		log.Fatalf("创建数据库失败: %v", err)
	}

	db, err := database.Open(cfg)
	if err != nil {
		log.Fatalf("连接数据库失败: %v", err)
	}

	if *migrate {
		if err := database.Migrate(db, cfg.Migrations.Dir); err != nil {
			log.Fatalf("执行迁移失败: %v", err)
		}
		log.Println("数据库迁移完成")
	}

	if *seed {
		if err := database.Seed(db); err != nil {
			log.Fatalf("初始化演示数据失败: %v", err)
		}
		log.Println("演示数据初始化完成")
	}

	// 仅执行迁移/初始化时直接退出，不启动 HTTP 服务
	if *migrate || *seed {
		log.Println("初始化完成，进程退出")
		return
	}

	if err := os.MkdirAll(cfg.Storage.Attachments, 0o755); err != nil {
		log.Fatalf("创建附件目录失败: %v", err)
	}

	manager := auth.NewManager(
		cfg.JWT.Secret,
		time.Duration(cfg.JWT.TTLMinutes)*time.Minute,
		time.Duration(cfg.JWT.RefreshMinutes)*time.Minute,
	)
	limiter := service.NewLoginLimiter(cfg.Login.MaxAttempts, cfg.Login.MaxIPAttempts, cfg.Login.LockSeconds)

	attachmentService := service.NewAttachmentService(db, cfg.Storage.Attachments)
	proposalService := service.NewProposalService(db, attachmentService)
	authService := service.NewAuthService(db, manager, limiter)
	masterDataService := service.NewMasterDataService(db)
	contactService := service.NewContactService(db)
	exportService := service.NewExportService(db, attachmentService, cfg.Template.Pack)

	h := handler.New(db, authService, proposalService, attachmentService, masterDataService, contactService, exportService)
	engine := router.New(h, manager, db, cfg)

	server := &http.Server{
		Addr:              cfg.Server.Addr,
		Handler:           engine,
		ReadHeaderTimeout: 10 * time.Second,
	}

	go func() {
		log.Printf("PackSubmit %s 已启动：http://127.0.0.1%s", version.Version, cfg.Server.Addr)

		if err := server.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			log.Fatalf("服务启动失败: %v", err)
		}
	}()

	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	if err := server.Shutdown(ctx); err != nil {
		log.Printf("服务关闭异常: %v", err)
	}

	log.Println("服务已退出")
}
