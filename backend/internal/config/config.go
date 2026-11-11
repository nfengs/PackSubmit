package config

import (
	"errors"
	"fmt"
	"os"
	"strconv"
	"strings"

	"gopkg.in/yaml.v3"
)

type serverConfig struct {
	Addr      string `yaml:"addr"`
	Mode      string `yaml:"mode"`
	StaticDir string `yaml:"static_dir"`
}

type dbConfig struct {
	Host     string `yaml:"host"`
	Port     int    `yaml:"port"`
	User     string `yaml:"user"`
	Password string `yaml:"password"`
	Name     string `yaml:"name"`
	Params   string `yaml:"params"`
}

type jwtConfig struct {
	Secret         string `yaml:"secret"`
	TTLMinutes     int    `yaml:"ttl_minutes"`
	RefreshMinutes int    `yaml:"refresh_minutes"`
}

type storageConfig struct {
	Attachments string `yaml:"attachments"`
}

type templateConfig struct {
	Pack string `yaml:"pack"`
}

type corsConfig struct {
	AllowOrigins []string `yaml:"allow_origins"`
}

type loginConfig struct {
	MaxAttempts   int `yaml:"max_attempts"`
	MaxIPAttempts int `yaml:"max_ip_attempts"`
	LockSeconds   int `yaml:"lock_seconds"`
}

type migrationConfig struct {
	Dir string `yaml:"dir"`
}

type Config struct {
	Server     serverConfig    `yaml:"server"`
	DB         dbConfig        `yaml:"db"`
	JWT        jwtConfig       `yaml:"jwt"`
	Storage    storageConfig   `yaml:"storage"`
	Template   templateConfig  `yaml:"template"`
	CORS       corsConfig      `yaml:"cors"`
	Login      loginConfig     `yaml:"login"`
	Migrations migrationConfig `yaml:"migrations"`
}

func defaults() *Config {
	return &Config{
		Server: serverConfig{Addr: ":8090", Mode: "debug", StaticDir: ""},
		DB: dbConfig{
			Host: "127.0.0.1", Port: 3306, User: "root", Password: "",
			Name: "pack_submit", Params: "charset=utf8mb4&parseTime=True&loc=Local",
		},
		JWT:        jwtConfig{TTLMinutes: 120, RefreshMinutes: 20160},
		Storage:    storageConfig{Attachments: "storage/attachments"},
		Template:   templateConfig{Pack: "templates/包装确认单-模板 20260730.xlsx"},
		Login:      loginConfig{MaxAttempts: 5, MaxIPAttempts: 20, LockSeconds: 900},
		Migrations: migrationConfig{Dir: "migrations"},
	}
}

// Load 读取 YAML 配置并用环境变量覆盖（PS_ 前缀）
func Load(path string) (*Config, error) {
	cfg := defaults()

	if path != "" {
		data, err := os.ReadFile(path)
		if err != nil {
			return nil, fmt.Errorf("读取配置文件失败: %w", err)
		}

		if err := yaml.Unmarshal(data, cfg); err != nil {
			return nil, fmt.Errorf("解析配置文件失败: %w", err)
		}
	}

	applyEnv(cfg)

	if strings.TrimSpace(cfg.JWT.Secret) == "" {
		return nil, errors.New("JWT secret 未配置（jwt.secret 或 PS_JWT_SECRET）")
	}

	if strings.TrimSpace(cfg.DB.Name) == "" {
		return nil, errors.New("数据库名称未配置")
	}

	return cfg, nil
}

func applyEnv(cfg *Config) {
	setString(&cfg.Server.Addr, "PS_SERVER_ADDR")
	setString(&cfg.Server.Mode, "PS_SERVER_MODE")
	setString(&cfg.Server.StaticDir, "PS_SERVER_STATIC_DIR")

	setString(&cfg.DB.Host, "PS_DB_HOST")
	setInt(&cfg.DB.Port, "PS_DB_PORT")
	setString(&cfg.DB.User, "PS_DB_USER")
	setString(&cfg.DB.Password, "PS_DB_PASSWORD")
	setString(&cfg.DB.Name, "PS_DB_NAME")
	setString(&cfg.DB.Params, "PS_DB_PARAMS")

	setString(&cfg.JWT.Secret, "PS_JWT_SECRET")
	setInt(&cfg.JWT.TTLMinutes, "PS_JWT_TTL_MINUTES")
	setInt(&cfg.JWT.RefreshMinutes, "PS_JWT_REFRESH_MINUTES")

	setString(&cfg.Storage.Attachments, "PS_STORAGE_ATTACHMENTS")
	setString(&cfg.Template.Pack, "PS_TEMPLATE_PACK")
	setString(&cfg.Migrations.Dir, "PS_MIGRATIONS_DIR")

	setInt(&cfg.Login.MaxAttempts, "PS_LOGIN_MAX_ATTEMPTS")
	setInt(&cfg.Login.MaxIPAttempts, "PS_LOGIN_MAX_IP_ATTEMPTS")
	setInt(&cfg.Login.LockSeconds, "PS_LOGIN_LOCK_SECONDS")

	if raw := strings.TrimSpace(os.Getenv("PS_CORS_ALLOW_ORIGINS")); raw != "" {
		parts := strings.Split(raw, ",")
		origins := make([]string, 0, len(parts))

		for _, part := range parts {
			if origin := strings.TrimSpace(part); origin != "" {
				origins = append(origins, origin)
			}
		}

		cfg.CORS.AllowOrigins = origins
	}
}

func setString(target *string, key string) {
	if value := strings.TrimSpace(os.Getenv(key)); value != "" {
		*target = value
	}
}

func setInt(target *int, key string) {
	raw := strings.TrimSpace(os.Getenv(key))
	if raw == "" {
		return
	}

	if value, err := strconv.Atoi(raw); err == nil {
		*target = value
	}
}

// DSN 拼接 MySQL 连接串
func (c *Config) DSN() string {
	return fmt.Sprintf("%s:%s@tcp(%s:%d)/%s?%s",
		c.DB.User, c.DB.Password, c.DB.Host, c.DB.Port, c.DB.Name, c.DB.Params)
}

// DSNWithoutDatabase 用于自动建库
func (c *Config) DSNWithoutDatabase() string {
	return fmt.Sprintf("%s:%s@tcp(%s:%d)/?%s",
		c.DB.User, c.DB.Password, c.DB.Host, c.DB.Port, c.DB.Params)
}
