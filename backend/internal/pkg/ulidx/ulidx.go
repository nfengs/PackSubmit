package ulidx

import (
	"crypto/rand"
	"sync"
	"time"

	"github.com/oklog/ulid/v2"
)

var (
	mu      sync.Mutex
	entropy = ulid.Monotonic(rand.Reader, 0)
)

// New 生成 26 位 ULID（按时间有序）
func New() string {
	mu.Lock()
	defer mu.Unlock()

	return ulid.MustNew(ulid.Timestamp(time.Now()), entropy).String()
}
