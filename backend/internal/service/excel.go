package service

import (
	"bytes"
	"fmt"
	"io"
	"strings"

	"github.com/xuri/excelize/v2"

	"packsubmit/internal/pkg/resp"
)

// readXlsxRows 读取第一个工作表的全部行（含表头），跳过纯空行
func readXlsxRows(reader io.Reader) ([][]string, error) {
	book, err := excelize.OpenReader(reader)
	if err != nil {
		return nil, resp.Biz("文件解析失败，请使用 .xlsx 模板")
	}
	defer book.Close()

	sheets := book.GetSheetList()
	if len(sheets) == 0 {
		return nil, resp.Biz("Excel 中没有工作表")
	}

	rows, err := book.GetRows(sheets[0])
	if err != nil {
		return nil, resp.Biz("读取 Excel 内容失败")
	}

	cleaned := make([][]string, 0, len(rows))

	for _, row := range rows {
		empty := true

		for _, cell := range row {
			if strings.TrimSpace(cell) != "" {
				empty = false
				break
			}
		}

		if empty {
			continue
		}

		cleaned = append(cleaned, row)
	}

	if len(cleaned) == 0 {
		return nil, resp.Biz("Excel 内容为空")
	}

	return cleaned, nil
}

// buildTemplate 生成只有表头的导入模板
func buildTemplate(header []string) ([]byte, error) {
	book := excelize.NewFile()
	defer book.Close()

	sheet := book.GetSheetName(0)

	for index, title := range header {
		cell, err := excelize.CoordinatesToCellName(index+1, 1)
		if err != nil {
			return nil, err
		}

		if err := book.SetCellValue(sheet, cell, title); err != nil {
			return nil, err
		}
	}

	if len(header) > 0 {
		style, err := book.NewStyle(&excelize.Style{Font: &excelize.Font{Bold: true}})
		if err == nil {
			last, _ := excelize.CoordinatesToCellName(len(header), 1)
			_ = book.SetCellStyle(sheet, "A1", last, style)
		}
	}

	var buffer bytes.Buffer
	if err := book.Write(&buffer); err != nil {
		return nil, err
	}

	return buffer.Bytes(), nil
}

// ParseXlsx 供 handler 复用的 Excel 读取入口
func ParseXlsx(reader io.Reader) ([][]string, error) {
	return readXlsxRows(reader)
}

// BuildTemplate 供 handler 复用生成导入模板
func BuildTemplate(header []string) ([]byte, error) {
	return buildTemplate(header)
}

func rowValue(row []string, index int) string {
	if index < 0 || index >= len(row) {
		return ""
	}

	return strings.TrimSpace(row[index])
}

func importError(rowIndex int, message string) string {
	// rowIndex 从 1 开始（含表头），错误信息带上 Excel 行号便于定位
	return fmt.Sprintf("第 %d 行：%s", rowIndex, message)
}
