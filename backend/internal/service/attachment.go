package service

import (
	"bytes"
	"crypto/md5"
	"encoding/hex"
	"fmt"
	"image"
	_ "image/jpeg"
	_ "image/png"
	"io"
	"mime/multipart"
	"os"
	"path/filepath"
	"strings"
	"time"

	"gorm.io/gorm"

	"packsubmit/internal/model"
	"packsubmit/internal/pkg/auth"
	"packsubmit/internal/pkg/resp"
	"packsubmit/internal/pkg/ulidx"
)

const maxFileSize = 10 << 20

var allowedExt = map[string]struct{}{
	"pdf": {}, "dwg": {}, "xlsx": {}, "docx": {}, "png": {}, "jpg": {}, "jpeg": {},
}

var imageExt = map[string]struct{}{"png": {}, "jpg": {}, "jpeg": {}}

type AttachmentService struct {
	DB   *gorm.DB
	Root string
}

func NewAttachmentService(db *gorm.DB, root string) *AttachmentService {
	return &AttachmentService{DB: db, Root: root}
}

// Absolute 把相对路径解析为绝对路径，并限制在附件根目录内
func (s *AttachmentService) Absolute(rel string) (string, error) {
	rel = strings.TrimSpace(rel)

	if rel == "" || strings.Contains(rel, "..") || filepath.IsAbs(rel) {
		return "", resp.Biz("附件路径不合法")
	}

	root, err := filepath.Abs(s.Root)
	if err != nil {
		return "", err
	}

	abs := filepath.Join(root, filepath.FromSlash(rel))

	if !strings.HasPrefix(abs, root+string(os.PathSeparator)) {
		return "", resp.Biz("附件路径不合法")
	}

	return abs, nil
}

func (s *AttachmentService) Upload(user *model.User, header *multipart.FileHeader, proposalID, attachType string) (*model.Attachment, bool, error) {
	if user.Role == auth.RoleReviewer || user.Role == auth.RoleViewer {
		return nil, false, resp.Forbidden("评审员和查看者不能上传附件")
	}

	if attachType == "" {
		attachType = model.AttachOther
	}

	if _, ok := model.AttachTypeText[attachType]; !ok {
		return nil, false, resp.Biz("附件类型不正确")
	}

	ext := strings.ToLower(strings.TrimPrefix(filepath.Ext(header.Filename), "."))
	if _, ok := allowedExt[ext]; !ok {
		return nil, false, resp.Biz("仅支持 pdf、dwg、xlsx、docx、png、jpg 格式")
	}

	if header.Size > maxFileSize {
		return nil, false, resp.Biz("单个文件不能超过 10MB")
	}

	if isImageType(attachType) && !isImageExt(ext) {
		return nil, false, resp.Biz("图片分类仅支持上传 png、jpg 图片")
	}

	file, err := header.Open()
	if err != nil {
		return nil, false, resp.Biz("读取上传文件失败")
	}
	defer file.Close()

	data, err := io.ReadAll(io.LimitReader(file, maxFileSize+1))
	if err != nil {
		return nil, false, resp.Biz("读取上传文件失败")
	}

	if len(data) == 0 {
		return nil, false, resp.Biz("上传文件为空")
	}

	if len(data) > maxFileSize {
		return nil, false, resp.Biz("单个文件不能超过 10MB")
	}

	mimeType := "application/octet-stream"

	// 图片按文件内容识别真实格式，改后缀伪装会被拒绝
	if _, ok := imageExt[ext]; ok {
		cfg, format, err := image.DecodeConfig(bytes.NewReader(data))
		if err != nil || cfg.Width <= 0 || cfg.Height <= 0 {
			return nil, false, resp.Biz("文件内容不是有效的图片，仅支持 png、jpg 格式")
		}

		switch format {
		case "png":
			ext, mimeType = "png", "image/png"
		case "jpeg":
			ext, mimeType = "jpg", "image/jpeg"
		default:
			return nil, false, resp.Biz("文件内容不是有效的图片，仅支持 png、jpg 格式")
		}
	}

	var proposal *model.Proposal

	if proposalID != "" {
		var found model.Proposal
		if err := s.DB.First(&found, "id = ?", proposalID).Error; err != nil {
			return nil, false, resp.Biz("方案不存在或已被删除")
		}

		if err := proposalServiceForAttachment(s.DB).AssertEditable(&found, user); err != nil {
			return nil, false, err
		}

		if isImageType(attachType) {
			var count int64
			s.DB.Model(&model.Attachment{}).
				Where("proposal_id = ? AND attach_type = ?", proposalID, attachType).
				Count(&count)

			if count > 0 {
				return nil, false, resp.Biz(model.AttachTypeText[attachType] + "只能上传 1 张图片，请先删除原图片")
			}
		}

		proposal = &found
	}

	sum := md5.Sum(data)
	md5Text := hex.EncodeToString(sum[:])

	relativePath := ""
	reused := false

	// MD5 去重：只复用物理文件仍存在的记录
	var candidates []model.Attachment
	s.DB.Where("md5 = ?", md5Text).Order("created_at ASC").Find(&candidates)

	for _, candidate := range candidates {
		abs, err := s.Absolute(candidate.FilePath)
		if err != nil {
			continue
		}

		if info, err := os.Stat(abs); err == nil && !info.IsDir() {
			relativePath = candidate.FilePath
			reused = true
			break
		}
	}

	if !reused {
		month := time.Now().Format("200601")
		dir := filepath.Join(s.Root, month)

		if err := os.MkdirAll(dir, 0o755); err != nil {
			return nil, false, resp.New(resp.CodeServer, "创建上传目录失败")
		}

		fileName := time.Now().Format("20060102150405") + "_" + ulidx.New() + "." + ext
		relativePath = month + "/" + fileName

		abs, err := s.Absolute(relativePath)
		if err != nil {
			return nil, false, err
		}

		if err := os.WriteFile(abs, data, 0o644); err != nil {
			return nil, false, resp.New(resp.CodeServer, "保存附件失败")
		}
	}

	attachment := model.Attachment{
		ID:         ulidx.New(),
		AttachType: attachType,
		UploadedBy: user.ID,
		FileName:   header.Filename,
		FilePath:   relativePath,
		FileExt:    ext,
		FileSize:   int64(len(data)),
		MimeType:   mimeType,
		MD5:        md5Text,
	}

	if proposalID != "" {
		attachment.ProposalID = &proposalID
	}

	if err := s.DB.Create(&attachment).Error; err != nil {
		return nil, false, resp.New(resp.CodeServer, "保存附件记录失败")
	}

	if proposal != nil {
		_ = Record(s.DB, AuditEntry{
			ProposalID: &proposal.ID, UserID: user.ID, Action: "upload",
			Version: proposal.Version,
			Content: fmt.Sprintf("上传附件：%s%s", attachment.FileName, map[bool]string{true: "（复用已有文件）"}[reused]),
		})
	}

	return &attachment, reused, nil
}

// Bind 把暂存附件绑定到方案
func (s *AttachmentService) Bind(user *model.User, attachmentID, proposalID string) error {
	var attachment model.Attachment
	if err := s.DB.First(&attachment, "id = ?", attachmentID).Error; err != nil {
		return resp.ErrNotFound
	}

	if attachment.ProposalID != nil {
		return resp.Biz("该附件已绑定方案")
	}

	if user.Role != auth.RoleAdmin && attachment.UploadedBy != user.ID {
		return resp.Forbidden("只能绑定自己上传的附件")
	}

	var proposal model.Proposal
	if err := s.DB.First(&proposal, "id = ?", proposalID).Error; err != nil {
		return resp.Biz("方案不存在或已被删除")
	}

	if err := proposalServiceForAttachment(s.DB).AssertEditable(&proposal, user); err != nil {
		return err
	}

	if isImageType(attachment.AttachType) {
		var count int64
		s.DB.Model(&model.Attachment{}).
			Where("proposal_id = ? AND attach_type = ?", proposalID, attachment.AttachType).
			Count(&count)

		if count > 0 {
			return resp.Biz(model.AttachTypeText[attachment.AttachType] + "只能上传 1 张图片")
		}
	}

	return s.DB.Model(&attachment).Update("proposal_id", proposalID).Error
}

func (s *AttachmentService) Delete(user *model.User, id, ip string) error {
	var attachment model.Attachment
	if err := s.DB.First(&attachment, "id = ?", id).Error; err != nil {
		return resp.ErrNotFound
	}

	if user.Role != auth.RoleAdmin && attachment.UploadedBy != user.ID {
		return resp.Forbidden("只能删除自己上传的附件")
	}

	if attachment.ProposalID != nil {
		var proposal model.Proposal
		if err := s.DB.First(&proposal, "id = ?", *attachment.ProposalID).Error; err != nil {
			return resp.Biz("方案不存在或已被删除")
		}

		if err := proposalServiceForAttachment(s.DB).AssertEditable(&proposal, user); err != nil {
			return err
		}

		if err := Record(s.DB, AuditEntry{
			ProposalID: &proposal.ID, UserID: user.ID, Action: "delete_attachment",
			Version: proposal.Version, Content: "删除附件：" + attachment.FileName, IP: ip,
		}); err != nil {
			return err
		}
	}

	if err := s.DB.Delete(&attachment).Error; err != nil {
		return err
	}

	// 引用计数：仅当没有其他附件引用同一物理文件时才真正删除
	var refs int64
	s.DB.Model(&model.Attachment{}).Where("file_path = ?", attachment.FilePath).Count(&refs)

	if refs == 0 {
		if abs, err := s.Absolute(attachment.FilePath); err == nil {
			_ = os.Remove(abs)
		}
	}

	return nil
}

// Resolve 校验访问权限并返回绝对路径
func (s *AttachmentService) Resolve(user *model.User, id string) (*model.Attachment, string, error) {
	var attachment model.Attachment
	if err := s.DB.First(&attachment, "id = ?", id).Error; err != nil {
		return nil, "", resp.ErrNotFound
	}

	if attachment.ProposalID == nil {
		if user.Role != auth.RoleAdmin && attachment.UploadedBy != user.ID {
			return nil, "", resp.Forbidden("无权访问该附件")
		}
	} else {
		var proposal model.Proposal
		if err := s.DB.First(&proposal, "id = ?", *attachment.ProposalID).Error; err != nil {
			return nil, "", resp.Biz("方案不存在或已被删除")
		}

		if err := proposalServiceForAttachment(s.DB).AssertViewable(&proposal, user); err != nil {
			return nil, "", err
		}
	}

	abs, err := s.Absolute(attachment.FilePath)
	if err != nil {
		return nil, "", err
	}

	if _, err := os.Stat(abs); err != nil {
		return nil, "", resp.New(resp.CodeBiz, "附件文件不存在或已被清理")
	}

	return &attachment, abs, nil
}

func (s *AttachmentService) ByProposal(proposalID string) ([]model.Attachment, error) {
	items := make([]model.Attachment, 0)
	err := s.DB.Where("proposal_id = ?", proposalID).Order("created_at ASC").Find(&items).Error

	return items, err
}

func (s *AttachmentService) ImagePaths(proposalID, attachType string) []string {
	var items []model.Attachment
	s.DB.Where("proposal_id = ? AND attach_type = ?", proposalID, attachType).
		Order("created_at ASC").Find(&items)

	paths := make([]string, 0, len(items))

	for _, item := range items {
		if abs, err := s.Absolute(item.FilePath); err == nil {
			paths = append(paths, abs)
		}
	}

	return paths
}

func proposalServiceForAttachment(db *gorm.DB) *ProposalService {
	return &ProposalService{DB: db}
}

func isImageType(attachType string) bool {
	for _, item := range model.ImageAttachTypes {
		if item == attachType {
			return true
		}
	}

	return false
}

func isImageExt(ext string) bool {
	_, ok := imageExt[ext]

	return ok
}
