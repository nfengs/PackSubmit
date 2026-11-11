package resp

import (
	"errors"
	"net/http"

	"github.com/gin-gonic/gin"
	"gorm.io/gorm"
)

// 统一响应码：0 表示成功，其余为业务/系统错误
const (
	CodeOK           = 0
	CodeParam        = 1001
	CodeUnauthorized = 2001
	CodeForbidden    = 2002
	CodeBiz          = 3001
	CodeTooMany      = 3002
	CodeServer       = 5001
)

type Body struct {
	Code    int    `json:"code"`
	Message string `json:"message"`
	Data    any    `json:"data"`
}

type PageData struct {
	List     any   `json:"list"`
	Total    int64 `json:"total"`
	Page     int   `json:"page"`
	PageSize int   `json:"page_size"`
}

// AppError 业务错误，带响应码
type AppError struct {
	Code int
	Msg  string
}

func (e *AppError) Error() string { return e.Msg }

func New(code int, msg string) *AppError { return &AppError{Code: code, Msg: msg} }

func Param(msg string) *AppError        { return New(CodeParam, msg) }
func Biz(msg string) *AppError          { return New(CodeBiz, msg) }
func Forbidden(msg string) *AppError    { return New(CodeForbidden, msg) }
func Unauthorized(msg string) *AppError { return New(CodeUnauthorized, msg) }

var ErrNotFound = New(CodeBiz, "记录不存在或已被删除")

func OK(c *gin.Context, data any) {
	c.JSON(http.StatusOK, Body{Code: CodeOK, Message: "success", Data: data})
}

func OKMsg(c *gin.Context, msg string) {
	c.JSON(http.StatusOK, Body{Code: CodeOK, Message: msg})
}

func OKPage(c *gin.Context, list any, total int64, page, pageSize int) {
	c.JSON(http.StatusOK, Body{Code: CodeOK, Message: "success", Data: PageData{
		List: list, Total: total, Page: page, PageSize: pageSize,
	}})
}

func Fail(c *gin.Context, code int, msg string) {
	c.JSON(http.StatusOK, Body{Code: code, Message: msg})
}

// Error 统一错误出口：401/403 对齐 HTTP 状态码，其余业务错误保持 200
func Error(c *gin.Context, err error) {
	var appErr *AppError

	if errors.As(err, &appErr) {
		status := http.StatusOK

		switch appErr.Code {
		case CodeUnauthorized:
			status = http.StatusUnauthorized
		case CodeForbidden:
			status = http.StatusForbidden
		case CodeTooMany:
			status = http.StatusTooManyRequests
		}

		c.JSON(status, Body{Code: appErr.Code, Message: appErr.Msg})
		return
	}

	if errors.Is(err, gorm.ErrRecordNotFound) {
		c.JSON(http.StatusOK, Body{Code: CodeBiz, Message: "记录不存在或已被删除"})
		return
	}

	c.JSON(http.StatusOK, Body{Code: CodeServer, Message: err.Error()})
}
