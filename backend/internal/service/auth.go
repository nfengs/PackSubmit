package service

import (
	"fmt"
	"strings"
	"sync"
	"time"

	"gorm.io/gorm"

	"packsubmit/internal/model"
	"packsubmit/internal/pkg/auth"
	"packsubmit/internal/pkg/resp"
)

// Session 登录态：token + 用户 + 动态菜单/路由/权限
type Session struct {
	Token       string      `json:"token,omitempty"`
	User        *model.User `json:"user"`
	Menus       []auth.Menu `json:"menus"`
	Routes      []auth.Menu `json:"routes"`
	Permissions []string    `json:"permissions"`
}

type limitEntry struct {
	count   int
	expires time.Time
}

// LoginLimiter 进程内登录失败计数（单实例部署，无需 Redis）
type LoginLimiter struct {
	mu     sync.Mutex
	items  map[string]*limitEntry
	max    int
	maxIP  int
	window time.Duration
}

func NewLoginLimiter(maxAttempts, maxIPAttempts, lockSeconds int) *LoginLimiter {
	if maxAttempts <= 0 {
		maxAttempts = 5
	}
	if maxIPAttempts <= 0 {
		maxIPAttempts = 20
	}
	if lockSeconds < 60 {
		lockSeconds = 900
	}

	return &LoginLimiter{
		items:  make(map[string]*limitEntry),
		max:    maxAttempts,
		maxIP:  maxIPAttempts,
		window: time.Duration(lockSeconds) * time.Second,
	}
}

func (l *LoginLimiter) entry(key string) *limitEntry {
	now := time.Now()

	if item, ok := l.items[key]; ok {
		if now.Before(item.expires) {
			return item
		}
		delete(l.items, key)
	}

	item := &limitEntry{expires: now.Add(l.window)}
	l.items[key] = item

	return item
}

func (l *LoginLimiter) Blocked(userKey, ipKey string) bool {
	l.mu.Lock()
	defer l.mu.Unlock()

	if item := l.entry(userKey); item.count >= l.max {
		return true
	}

	return l.entry(ipKey).count >= l.maxIP
}

func (l *LoginLimiter) Fail(keys ...string) {
	l.mu.Lock()
	defer l.mu.Unlock()

	for _, key := range keys {
		item := l.entry(key)
		item.count++
		item.expires = time.Now().Add(l.window)
	}
}

func (l *LoginLimiter) Reset(key string) {
	l.mu.Lock()
	defer l.mu.Unlock()

	delete(l.items, key)
}

func (l *LoginLimiter) LockMinutes() int {
	return int(l.window.Minutes())
}

type AuthService struct {
	DB      *gorm.DB
	JWT     *auth.Manager
	Limiter *LoginLimiter
}

func NewAuthService(db *gorm.DB, manager *auth.Manager, limiter *LoginLimiter) *AuthService {
	return &AuthService{DB: db, JWT: manager, Limiter: limiter}
}

func (s *AuthService) Login(username, password, ip string) (*Session, error) {
	username = strings.TrimSpace(username)

	if username == "" || password == "" {
		return nil, resp.Param("请输入账号和密码")
	}

	userKey := "login_fail:" + strings.ToLower(username) + "|" + ip
	ipKey := "login_fail_ip:" + ip

	if s.Limiter.Blocked(userKey, ipKey) {
		return nil, resp.New(resp.CodeTooMany, fmt.Sprintf("登录失败次数过多，请 %d 分钟后再试", s.Limiter.LockMinutes()))
	}

	var user model.User
	if err := s.DB.Where("username = ?", username).First(&user).Error; err != nil {
		s.Limiter.Fail(userKey, ipKey)
		return nil, resp.Biz("账号或密码错误")
	}

	if !auth.CheckPassword(user.PasswordHash, password) {
		s.Limiter.Fail(userKey, ipKey)
		return nil, resp.Biz("账号或密码错误")
	}

	// 登录成功只清零「账号 + IP」计数，IP 维度保留用于防爆破
	s.Limiter.Reset(userKey)

	if user.Status != model.UserEnabled {
		return nil, resp.Forbidden("账号已被禁用，请联系管理员")
	}

	token, err := s.JWT.Issue(user.ID, user.Role, user.TokenVersion)
	if err != nil {
		return nil, resp.New(resp.CodeServer, "签发登录凭证失败")
	}

	_ = Record(s.DB, AuditEntry{UserID: user.ID, Action: "login", Content: "用户登录", IP: ip})

	return s.buildSession(&user, token)
}

func (s *AuthService) Profile(user *model.User) *Session {
	session, _ := s.buildSession(user, "")

	return session
}

func (s *AuthService) buildSession(user *model.User, token string) (*Session, error) {
	session := &Session{
		Token:       token,
		User:        user,
		Menus:       auth.Menus(user.Role),
		Routes:      auth.Routes(user.Role),
		Permissions: auth.Permissions(user.Role),
	}

	if user.SupplierID != nil {
		var supplier model.Supplier
		if err := s.DB.First(&supplier, "id = ?", *user.SupplierID).Error; err == nil {
			session.User.Supplier = &supplier
			session.User.Dept = supplier.Name
		}
	}

	return session, nil
}

func (s *AuthService) Refresh(raw string) (*Session, error) {
	claims, err := s.JWT.Parse(raw, true)
	if err != nil {
		return nil, resp.Unauthorized("登录状态已失效，请重新登录")
	}

	var user model.User
	if err := s.DB.First(&user, "id = ?", claims.UID).Error; err != nil {
		return nil, resp.Unauthorized("账号不存在或已被删除")
	}

	if user.Status != model.UserEnabled {
		return nil, resp.Forbidden("账号已被禁用，请联系管理员")
	}

	if claims.Ver != user.TokenVersion {
		return nil, resp.Unauthorized("登录状态已失效，请重新登录")
	}

	token, err := s.JWT.Issue(user.ID, user.Role, user.TokenVersion)
	if err != nil {
		return nil, resp.New(resp.CodeServer, "签发登录凭证失败")
	}

	// 旧 token 立即拉黑，避免刷新后的 token 继续可用
	s.JWT.Revoke(claims)

	return s.buildSession(&user, token)
}

func (s *AuthService) Logout(raw string, userID, ip string) {
	if claims, err := s.JWT.Parse(raw, true); err == nil {
		s.JWT.Revoke(claims)
	}

	_ = Record(s.DB, AuditEntry{UserID: userID, Action: "logout", Content: "退出登录", IP: ip})
}

// ChangePassword 改密成功后递增 token_version，所有旧 token 立即失效
func (s *AuthService) ChangePassword(user *model.User, oldPassword, newPassword string, ip string) (string, error) {
	if !auth.CheckPassword(user.PasswordHash, oldPassword) {
		return "", resp.Biz("原密码不正确")
	}

	if oldPassword == newPassword {
		return "", resp.Biz("新密码不能与原密码相同")
	}

	if err := auth.AssertPassword(newPassword); err != nil {
		return "", resp.Biz(err.Error())
	}

	hash, err := auth.HashPassword(newPassword)
	if err != nil {
		return "", resp.New(resp.CodeServer, "密码加密失败")
	}

	newVersion := user.TokenVersion + 1

	if err := s.DB.Model(&model.User{}).Where("id = ?", user.ID).Updates(map[string]any{
		"password_hash": hash,
		"token_version": newVersion,
	}).Error; err != nil {
		return "", resp.New(resp.CodeServer, "修改密码失败")
	}

	token, err := s.JWT.Issue(user.ID, user.Role, newVersion)
	if err != nil {
		return "", resp.New(resp.CodeServer, "签发登录凭证失败")
	}

	_ = Record(s.DB, AuditEntry{UserID: user.ID, Action: "password_change", Content: "修改登录密码", IP: ip})

	return token, nil
}
