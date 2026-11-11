package auth

import (
	"errors"
	"strings"
	"sync"
	"time"
	"unicode"

	"github.com/golang-jwt/jwt/v5"
	"golang.org/x/crypto/bcrypt"

	"packsubmit/internal/pkg/ulidx"
)

// 角色：与旧系统保持一致，便于历史数据迁移
const (
	RoleSupplier int8 = 1 // 供应商
	RoleReviewer int8 = 2 // 评审员
	RoleAdmin    int8 = 3 // 管理员
	RoleViewer   int8 = 4 // 查看者（只读）
)

var (
	ErrTokenInvalid = errors.New("登录状态已失效，请重新登录")
	ErrTokenExpired = errors.New("登录状态已失效，请重新登录")
)

const (
	tokenIssuer  = "packsubmit"
	passwordCost = 10
)

type Claims struct {
	UID  string `json:"uid"`
	Role int8   `json:"role"`
	Ver  int    `json:"ver"`
	jwt.RegisteredClaims
}

// Manager 负责签发 / 校验 token，并维护内存黑名单（单实例部署，无需 Redis）
type Manager struct {
	secret        []byte
	ttl           time.Duration
	refreshWindow time.Duration

	mu      sync.Mutex
	revoked map[string]time.Time
}

func NewManager(secret string, ttl, refreshWindow time.Duration) *Manager {
	if ttl <= 0 {
		ttl = 2 * time.Hour
	}
	if refreshWindow <= 0 {
		refreshWindow = 14 * 24 * time.Hour
	}

	return &Manager{
		secret:        []byte(secret),
		ttl:           ttl,
		refreshWindow: refreshWindow,
		revoked:       make(map[string]time.Time),
	}
}

func (m *Manager) Issue(uid string, role int8, ver int) (string, error) {
	now := time.Now()
	claims := Claims{
		UID:  uid,
		Role: role,
		Ver:  ver,
		RegisteredClaims: jwt.RegisteredClaims{
			ID:        ulidx.New(),
			Issuer:    tokenIssuer,
			IssuedAt:  jwt.NewNumericDate(now),
			ExpiresAt: jwt.NewNumericDate(now.Add(m.ttl)),
		},
	}

	return jwt.NewWithClaims(jwt.SigningMethodHS256, claims).SignedString(m.secret)
}

// Parse 校验 token；allowExpired 用于刷新窗口内的过期 token 续期
func (m *Manager) Parse(raw string, allowExpired bool) (*Claims, error) {
	if strings.TrimSpace(raw) == "" {
		return nil, ErrTokenInvalid
	}

	opts := []jwt.ParserOption{
		jwt.WithValidMethods([]string{jwt.SigningMethodHS256.Alg()}),
		jwt.WithIssuer(tokenIssuer),
	}

	if allowExpired {
		opts = append(opts, jwt.WithoutClaimsValidation())
	}

	claims := &Claims{}
	token, err := jwt.ParseWithClaims(raw, claims, func(*jwt.Token) (any, error) {
		return m.secret, nil
	}, opts...)

	if err != nil || !token.Valid {
		if errors.Is(err, jwt.ErrTokenExpired) {
			return nil, ErrTokenExpired
		}
		return nil, ErrTokenInvalid
	}

	if claims.UID == "" || claims.ID == "" {
		return nil, ErrTokenInvalid
	}

	if m.isRevoked(claims.ID) {
		return nil, ErrTokenInvalid
	}

	if allowExpired {
		// 只允许在刷新窗口内续期，超出窗口必须重新登录
		if claims.ExpiresAt == nil || time.Since(claims.ExpiresAt.Time) > m.refreshWindow {
			return nil, ErrTokenInvalid
		}
	}

	return claims, nil
}

func (m *Manager) Revoke(claims *Claims) {
	if claims == nil || claims.ID == "" {
		return
	}

	exp := time.Now().Add(m.refreshWindow)
	if claims.ExpiresAt != nil {
		exp = claims.ExpiresAt.Time.Add(m.refreshWindow)
	}

	m.mu.Lock()
	defer m.mu.Unlock()

	now := time.Now()
	for id, until := range m.revoked {
		if now.After(until) {
			delete(m.revoked, id)
		}
	}
	m.revoked[claims.ID] = exp
}

func (m *Manager) isRevoked(id string) bool {
	m.mu.Lock()
	defer m.mu.Unlock()

	until, ok := m.revoked[id]
	if !ok {
		return false
	}

	if time.Now().After(until) {
		delete(m.revoked, id)
		return false
	}

	return true
}

func HashPassword(raw string) (string, error) {
	hashed, err := bcrypt.GenerateFromPassword([]byte(raw), passwordCost)
	if err != nil {
		return "", err
	}

	return string(hashed), nil
}

func CheckPassword(hash, raw string) bool {
	return bcrypt.CompareHashAndPassword([]byte(hash), []byte(raw)) == nil
}

var weakPasswords = map[string]struct{}{
	"12345678": {}, "123456789": {}, "password": {}, "password1": {}, "password123": {},
	"admin123": {}, "admin1234": {}, "qwerty123": {}, "abc12345": {}, "a1234567": {},
	"1qaz2wsx": {}, "iloveyou1": {}, "welcome1": {}, "p@ssw0rd": {},
}

// AssertPassword 统一密码策略：8-32 位，同时包含字母和数字，拒绝常见弱口令
func AssertPassword(raw string) error {
	runes := []rune(raw)

	if len(runes) < 8 || len(runes) > 32 {
		return errors.New("密码长度需为 8-32 位")
	}

	var hasLetter, hasDigit bool

	for _, r := range runes {
		switch {
		case unicode.IsLetter(r):
			hasLetter = true
		case unicode.IsDigit(r):
			hasDigit = true
		}
	}

	if !hasLetter || !hasDigit {
		return errors.New("密码必须同时包含字母和数字")
	}

	if _, weak := weakPasswords[strings.ToLower(raw)]; weak {
		return errors.New("密码过于简单，请更换更复杂的密码")
	}

	return nil
}
