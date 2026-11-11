package router

import (
	"testing"
	"time"

	"github.com/gin-gonic/gin"

	"packsubmit/internal/config"
	"packsubmit/internal/handler"
	"packsubmit/internal/pkg/auth"
)

// TestRouteRegistration 校验路由注册无冲突（gin 在冲突时会 panic）
func TestRouteRegistration(t *testing.T) {
	gin.SetMode(gin.TestMode)

	cfg := &config.Config{}
	manager := auth.NewManager("test-secret", time.Hour, time.Hour)

	engine := New(&handler.Handler{}, manager, nil, cfg)
	if engine == nil {
		t.Fatal("router 初始化失败")
	}

	routes := engine.Routes()

	if len(routes) < 20 {
		t.Fatalf("路由数量异常：%d", len(routes))
	}

	found := false

	for _, route := range routes {
		if route.Method == "POST" && route.Path == "/api/v1/auth/login" {
			found = true
			break
		}
	}

	if !found {
		t.Fatal("缺少登录路由")
	}
}
