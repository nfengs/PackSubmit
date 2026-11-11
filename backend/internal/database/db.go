package database

import (
	"database/sql"
	"fmt"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"time"

	"gorm.io/driver/mysql"
	"gorm.io/gorm"
	"gorm.io/gorm/logger"

	"packsubmit/internal/config"
)

// Open 建立数据库连接（要求库已存在）
func Open(cfg *config.Config) (*gorm.DB, error) {
	db, err := gorm.Open(mysql.Open(cfg.DSN()), &gorm.Config{
		Logger:                                   logger.Default.LogMode(logger.Warn),
		DisableForeignKeyConstraintWhenMigrating: true,
	})
	if err != nil {
		return nil, fmt.Errorf("连接数据库失败: %w", err)
	}

	sqlDB, err := db.DB()
	if err != nil {
		return nil, err
	}

	sqlDB.SetMaxOpenConns(30)
	sqlDB.SetMaxIdleConns(10)
	sqlDB.SetConnMaxLifetime(time.Hour)

	return db, nil
}

// EnsureDatabase 目标库不存在时自动创建（开发环境便利，生产通常已建库）
func EnsureDatabase(cfg *config.Config) error {
	conn, err := sql.Open("mysql", cfg.DSNWithoutDatabase())
	if err != nil {
		return err
	}
	defer conn.Close()

	stmt := fmt.Sprintf(
		"CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci",
		cfg.DB.Name,
	)

	_, err = conn.Exec(stmt)

	return err
}

// Migrate 按文件名顺序执行 migrations 目录下未应用的 .sql
func Migrate(db *gorm.DB, dir string) error {
	if err := db.Exec(`CREATE TABLE IF NOT EXISTS schema_migrations (
		version varchar(64) NOT NULL,
		applied_at datetime(3) NOT NULL,
		PRIMARY KEY (version)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`).Error; err != nil {
		return fmt.Errorf("创建迁移记录表失败: %w", err)
	}

	entries, err := os.ReadDir(dir)
	if err != nil {
		return fmt.Errorf("读取迁移目录失败: %w", err)
	}

	names := make([]string, 0, len(entries))

	for _, entry := range entries {
		if !entry.IsDir() && strings.HasSuffix(entry.Name(), ".sql") {
			names = append(names, entry.Name())
		}
	}

	sort.Strings(names)

	for _, name := range names {
		var count int64
		db.Table("schema_migrations").Where("version = ?", name).Count(&count)

		if count > 0 {
			continue
		}

		content, err := os.ReadFile(filepath.Join(dir, name))
		if err != nil {
			return fmt.Errorf("读取迁移文件 %s 失败: %w", name, err)
		}

		for _, stmt := range splitStatements(string(content)) {
			if err := db.Exec(stmt).Error; err != nil {
				return fmt.Errorf("执行迁移 %s 失败: %w", name, err)
			}
		}

		if err := db.Exec("INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)", name, time.Now()).Error; err != nil {
			return err
		}
	}

	return nil
}

// splitStatements 去掉行注释并按分号切分（迁移脚本仅含简单 DDL）
func splitStatements(content string) []string {
	lines := strings.Split(content, "\n")
	cleaned := make([]string, 0, len(lines))

	for _, line := range lines {
		trimmed := strings.TrimSpace(line)

		if trimmed == "" || strings.HasPrefix(trimmed, "--") {
			continue
		}

		cleaned = append(cleaned, line)
	}

	parts := strings.Split(strings.Join(cleaned, "\n"), ";")
	statements := make([]string, 0, len(parts))

	for _, part := range parts {
		if stmt := strings.TrimSpace(part); stmt != "" {
			statements = append(statements, stmt)
		}
	}

	return statements
}
