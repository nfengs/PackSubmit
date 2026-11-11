package service

import (
	"bytes"
	"fmt"
	"image"
	_ "image/jpeg"
	_ "image/png"
	"math"
	"os"
	"regexp"
	"strings"

	"github.com/xuri/excelize/v2"
	"gorm.io/gorm"

	"packsubmit/internal/model"
	"packsubmit/internal/pkg/resp"
)

// 模板中需要清空的数据单元格
var clearCells = []string{
	"BH4", "BF13",
	"Q5", "Q7", "Q9", "BB9", "Q11", "BB11",
	"Q14", "BH14", "Q16", "Q18", "BB18", "Q20", "BB20",
	"CL5", "CL7", "CL9", "CL11", "CL17", "CL20", "CS20", "CZ20",
	"CL14", "DL16", "DV16", "DL18", "DV18", "CL23", "DL23", "DV23",
	"CL26", "CS26", "CZ26", "CL29", "DL29", "DV29",
	"BB44", "BJ44", "BR44", "BB48", "BN48", "BB51",
	"Q54",
}

// 图片占位：锚点、附件类型、最大宽高（px）
var imageSlots = []struct {
	Anchor     string
	AttachType string
	MaxWidth   int
	MaxHeight  int
}{
	{"DK6", model.AttachPart, 520, 138},
	{"E26", model.AttachInner, 520, 230},
	{"AR26", model.AttachFinal, 470, 300},
	{"H40", model.AttachPackaging, 520, 230},
}

var illegalFileName = regexp.MustCompile(`[\\/:*?"<>|\r\n\t]+`)

type ExportService struct {
	DB           *gorm.DB
	Attachments  *AttachmentService
	TemplatePath string
}

func NewExportService(db *gorm.DB, attachments *AttachmentService, templatePath string) *ExportService {
	return &ExportService{DB: db, Attachments: attachments, TemplatePath: templatePath}
}

// Excel 按包装确认单模板生成 xlsx，返回文件字节与建议文件名
func (s *ExportService) Excel(proposal *model.Proposal) ([]byte, string, error) {
	book, err := excelize.OpenFile(s.TemplatePath)
	if err != nil {
		return nil, "", resp.New(resp.CodeServer, "读取导出模板失败："+err.Error())
	}
	defer book.Close()

	sheets := book.GetSheetList()
	if len(sheets) == 0 {
		return nil, "", resp.New(resp.CodeServer, "导出模板缺少工作表")
	}

	sheet := sheets[0]

	// 仅保留主表
	for i := len(sheets) - 1; i >= 1; i-- {
		_ = book.DeleteSheet(sheets[i])
	}

	for _, cell := range clearCells {
		_ = book.SetCellValue(sheet, cell, "")
	}

	// 移除模板示例图片（保留 C2 的 logo）
	if cells, err := book.GetPictureCells(sheet); err == nil {
		for _, cell := range cells {
			if cell != "C2" {
				_ = book.DeletePicture(sheet, cell)
			}
		}
	}

	texts := map[string]string{
		"BH4":  proposal.WriteDate,
		"BF13": proposal.WriteDate,

		"Q5":   proposal.CustomerName,
		"Q7":   proposal.CustomerAddress,
		"Q9":   proposal.CustomerContact,
		"BB9":  proposal.CustomerEmail,
		"Q11":  proposal.CustomerPhone,
		"BB11": proposal.CustomerFax,

		"Q14":  proposal.SupplierName,
		"BH14": proposal.SupplierCode,
		"Q16":  proposal.SupplierAddress,
		"Q18":  proposal.Contact,
		"BB18": proposal.SupplierEmail,
		"Q20":  proposal.Phone,
		"BB20": proposal.SupplierFax,

		"CL5":  proposal.ProjectModel,
		"CL7":  proposal.PartName,
		"CL9":  proposal.PartNo,
		"CL11": proposal.PartMaterial,
		"CL14": proposal.PackBoxNo,
		"Q54":  proposal.PackagingInstruction,
	}

	for cell, value := range texts {
		_ = book.SetCellStr(sheet, cell, value)
	}

	numbers := map[string]float64{
		"CL17": proposal.PartWeight,
		"CL20": proposal.PartSizeL,
		"CS20": proposal.PartSizeW,
		"CZ20": proposal.PartSizeH,
		"CL23": float64(proposal.PackingQty),
		"CL26": proposal.BoxSizeL,
		"CS26": proposal.BoxSizeW,
		"CZ26": proposal.BoxSizeH,
		"CL29": proposal.GrossWeight,

		"BB44": proposal.PalletSizeL,
		"BJ44": proposal.PalletSizeW,
		"BR44": proposal.PalletSizeH,
		"BB48": float64(proposal.PalletLayerQty),
		"BN48": float64(proposal.PalletLayerCount),
		"BB51": float64(proposal.PalletPartsQty),
	}

	for cell, value := range numbers {
		_ = book.SetCellFloat(sheet, cell, value, -1, 64)
	}

	// 包装箱材料勾选
	materialChecks := map[string]string{
		"plastic": "DL16",
		"metal":   "DV16",
		"carton":  "DL18",
		"other":   "DV18",
	}

	if cell, ok := materialChecks[proposal.PackMaterial]; ok {
		_ = book.SetCellStr(sheet, cell, "√")
	}

	if proposal.Returnable != nil {
		cell := "DV23"
		if *proposal.Returnable == 1 {
			cell = "DL23"
		}
		_ = book.SetCellStr(sheet, cell, "√")
	}

	if proposal.DunnageReturnable != nil {
		cell := "DV29"
		if *proposal.DunnageReturnable == 1 {
			cell = "DL29"
		}
		_ = book.SetCellStr(sheet, cell, "√")
	}

	for _, slot := range imageSlots {
		paths := s.Attachments.ImagePaths(proposal.ID, slot.AttachType)
		if len(paths) == 0 {
			continue
		}

		data, err := os.ReadFile(paths[0])
		if err != nil {
			continue
		}

		cfg, format, err := image.DecodeConfig(bytes.NewReader(data))
		if err != nil || cfg.Width <= 0 || cfg.Height <= 0 {
			continue
		}

		scale := math.Min(float64(slot.MaxWidth)/float64(cfg.Width), float64(slot.MaxHeight)/float64(cfg.Height))

		picture := &excelize.Picture{
			Extension: "." + format,
			File:      data,
			Format: &excelize.GraphicOptions{
				OffsetX: 4,
				OffsetY: 6,
				ScaleX:  scale,
				ScaleY:  scale,
			},
		}

		if err := book.AddPictureFromBytes(sheet, slot.Anchor, picture); err != nil {
			continue
		}
	}

	var buffer bytes.Buffer
	if err := book.Write(&buffer); err != nil {
		return nil, "", resp.New(resp.CodeServer, "生成 Excel 失败："+err.Error())
	}

	return buffer.Bytes(), s.FileName(proposal), nil
}

// FileName 包装确认单-供应商-零件号-零件名称.xlsx
func (s *ExportService) FileName(proposal *model.Proposal) string {
	segments := []string{
		safeSegment(proposal.SupplierName),
		safeSegment(proposal.PartNo),
		safeSegment(proposal.PartName),
	}

	return fmt.Sprintf("包装确认单-%s.xlsx", strings.Join(segments, "-"))
}

func safeSegment(value string) string {
	value = strings.TrimSpace(illegalFileName.ReplaceAllString(value, ""))
	if value == "" {
		return "未填写"
	}

	return value
}
