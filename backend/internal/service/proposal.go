package service

import (
	"fmt"
	"math"
	"strconv"
	"strings"
	"time"

	"gorm.io/gorm"

	"packsubmit/internal/dto"
	"packsubmit/internal/model"
	"packsubmit/internal/pkg/auth"
	"packsubmit/internal/pkg/resp"
	"packsubmit/internal/pkg/ulidx"
)

type ListFilter struct {
	Page       int
	PageSize   int
	Status     []int8
	Keyword    string
	Customer   string
	Supplier   string
	Project    string
	PartNo     string
	WithPallet string
	DateField  string
	StartDate  string
	EndDate    string
}

type ProposalService struct {
	DB          *gorm.DB
	Attachments *AttachmentService
}

func NewProposalService(db *gorm.DB, attachments *AttachmentService) *ProposalService {
	return &ProposalService{DB: db, Attachments: attachments}
}

// ------------------------------------------------------------------
// 查询
// ------------------------------------------------------------------

func (s *ProposalService) scope(q *gorm.DB, user *model.User) *gorm.DB {
	switch user.Role {
	case auth.RoleAdmin, auth.RoleViewer:
		return q
	case auth.RoleReviewer:
		return q.Where("status <> ?", model.StatusDraft)
	case auth.RoleSupplier:
		if user.SupplierID == nil {
			return q.Where("1 = 0")
		}
		return q.Where("supplier_id = ?", *user.SupplierID)
	default:
		return q.Where("1 = 0")
	}
}

func (s *ProposalService) List(user *model.User, filter ListFilter) ([]dto.ProposalView, int64, error) {
	page, size := filter.Page, filter.PageSize

	if page < 1 {
		page = 1
	}
	if size < 1 {
		size = 20
	}
	if size > 100 {
		size = 100
	}

	q := s.scope(s.DB.Model(&model.Proposal{}), user)

	if len(filter.Status) > 0 {
		q = q.Where("status IN ?", filter.Status)
	}

	if keyword := strings.TrimSpace(filter.Keyword); keyword != "" {
		like := "%" + escapeLike(keyword) + "%"
		q = q.Where("(proposal_no LIKE ? OR part_name LIKE ? OR part_no LIKE ?)", like, like, like)
	}

	if filter.Customer != "" {
		q = q.Where("customer_name = ?", filter.Customer)
	}
	if filter.Supplier != "" {
		q = q.Where("supplier_name = ?", filter.Supplier)
	}
	if filter.Project != "" {
		q = q.Where("project_model = ?", filter.Project)
	}
	if filter.PartNo != "" {
		q = q.Where("part_no LIKE ?", "%"+escapeLike(filter.PartNo)+"%")
	}
	if filter.WithPallet == "0" || filter.WithPallet == "1" {
		q = q.Where("with_pallet = ?", filter.WithPallet)
	}

	dateField := filter.DateField
	switch dateField {
	case "submit_time", "review_time", "archive_time", "created_at":
	default:
		dateField = "created_at"
	}

	if filter.StartDate != "" {
		q = q.Where(dateField+" >= ?", filter.StartDate+" 00:00:00")
	}
	if filter.EndDate != "" {
		q = q.Where(dateField+" <= ?", filter.EndDate+" 23:59:59")
	}

	var total int64
	if err := q.Count(&total).Error; err != nil {
		return nil, 0, err
	}

	var rows []model.Proposal
	if err := q.Order("id DESC").Offset((page - 1) * size).Limit(size).Find(&rows).Error; err != nil {
		return nil, 0, err
	}

	return s.enrich(rows, user), total, nil
}

func (s *ProposalService) Statistics(user *model.User) (*dto.Statistics, error) {
	stats := &dto.Statistics{}

	counts := []struct {
		target *int64
		status int8
	}{
		{&stats.Draft, model.StatusDraft},
		{&stats.Pending, model.StatusPending},
		{&stats.Approved, model.StatusApproved},
		{&stats.Archived, model.StatusArchived},
		{&stats.Obsolete, model.StatusObsolete},
	}

	for _, item := range counts {
		q := s.scope(s.DB.Model(&model.Proposal{}), user).Where("status = ?", item.status)
		if err := q.Count(item.target).Error; err != nil {
			return nil, err
		}
	}

	total := s.scope(s.DB.Model(&model.Proposal{}), user)
	if err := total.Count(&stats.Total).Error; err != nil {
		return nil, err
	}

	return stats, nil
}

func (s *ProposalService) Projects(user *model.User) ([]string, error) {
	rows := make([]string, 0)

	err := s.scope(s.DB.Model(&model.Proposal{}), user).
		Where("project_model <> ''").
		Distinct().
		Order("project_model ASC").
		Pluck("project_model", &rows).Error

	return rows, err
}

func (s *ProposalService) Get(user *model.User, id string) (*dto.ProposalDetail, error) {
	var proposal model.Proposal
	if err := s.DB.First(&proposal, "id = ?", id).Error; err != nil {
		return nil, resp.ErrNotFound
	}

	if err := s.AssertViewable(&proposal, user); err != nil {
		return nil, err
	}

	detail := &dto.ProposalDetail{}
	detail.ProposalView = s.enrich([]model.Proposal{proposal}, user)[0]

	if err := s.DB.Where("proposal_id = ?", proposal.ID).Order("created_at ASC").Find(&detail.Attachments).Error; err != nil {
		return nil, err
	}
	if detail.Attachments == nil {
		detail.Attachments = []model.Attachment{}
	}

	// 评审日志不展示附件、查看、导出等只进全局审计的动作
	hidden := []string{"upload", "delete_attachment", "view", "export"}

	var logs []model.AuditLog
	if err := s.DB.Where("proposal_id = ? AND action NOT IN ?", proposal.ID, hidden).
		Order("created_at ASC").Find(&logs).Error; err != nil {
		return nil, err
	}

	detail.Logs = []dto.HistoryItem{}
	detail.History = []dto.HistoryItem{}

	userIDs := make(map[string]struct{})

	for _, log := range logs {
		userIDs[log.UserID] = struct{}{}
	}

	names := s.userNames(userIDs)

	for _, log := range logs {
		item := dto.HistoryItem{
			Action:     log.Action,
			ActionName: log.ActionName,
			Version:    log.Version,
			Content:    log.Content,
			UserName:   names[log.UserID],
			CreatedAt:  log.CreatedAt,
		}
		detail.Logs = append(detail.Logs, item)

		switch log.Action {
		case "create", "change", "submit", "withdraw", "review_pass", "review_reject", "archive", "obsolete":
			detail.History = append(detail.History, item)
		}
	}

	if proposal.SourceProposalID != nil {
		var source model.Proposal
		if err := s.DB.First(&source, "id = ?", *proposal.SourceProposalID).Error; err == nil {
			view := s.enrich([]model.Proposal{source}, user)[0]
			detail.Source = &view
		}
	}

	if proposal.Status == model.StatusArchived {
		var active model.Proposal
		err := s.DB.Where("source_proposal_id = ? AND status IN ?", proposal.ID, model.ActiveStatuses).
			Order("id DESC").First(&active).Error

		if err == nil {
			view := s.enrich([]model.Proposal{active}, user)[0]
			detail.ActiveChange = &view
		}
	}

	return detail, nil
}

func (s *ProposalService) enrich(rows []model.Proposal, user *model.User) []dto.ProposalView {
	views := make([]dto.ProposalView, 0, len(rows))

	ids := make(map[string]struct{})

	for _, row := range rows {
		ids[row.CreatedBy] = struct{}{}

		if row.ReviewerID != nil {
			ids[*row.ReviewerID] = struct{}{}
		}
	}

	names := s.userNames(ids)

	for i := range rows {
		row := rows[i]
		view := dto.ProposalView{
			Proposal:    row,
			StatusLabel: row.StatusLabel(),
			CreatorName: names[row.CreatedBy],
			Permissions: s.permissions(user, &row),
		}

		if row.ReviewerID != nil {
			view.ReviewerName = names[*row.ReviewerID]
		}

		views = append(views, view)
	}

	return views
}

func (s *ProposalService) userNames(ids map[string]struct{}) map[string]string {
	names := make(map[string]string, len(ids))

	if len(ids) == 0 {
		return names
	}

	keys := make([]string, 0, len(ids))
	for id := range ids {
		keys = append(keys, id)
	}

	var users []model.User
	if err := s.DB.Where("id IN ?", keys).Find(&users).Error; err == nil {
		for _, item := range users {
			label := item.RealName
			if label == "" {
				label = item.Username
			}
			names[item.ID] = label
		}
	}

	return names
}

// ------------------------------------------------------------------
// 权限
// ------------------------------------------------------------------

func (s *ProposalService) CanOperateAsSupplier(proposal *model.Proposal, user *model.User) bool {
	if user.Role != auth.RoleSupplier || user.SupplierID == nil {
		return false
	}

	return *user.SupplierID == proposal.SupplierID
}

func (s *ProposalService) AssertViewable(proposal *model.Proposal, user *model.User) error {
	switch user.Role {
	case auth.RoleAdmin, auth.RoleViewer:
		return nil
	case auth.RoleSupplier:
		if s.CanOperateAsSupplier(proposal, user) {
			return nil
		}
	case auth.RoleReviewer:
		if proposal.Status != model.StatusDraft {
			return nil
		}
	}

	return resp.Forbidden("无权查看该方案")
}

func (s *ProposalService) AssertEditable(proposal *model.Proposal, user *model.User) error {
	if user.Role == auth.RoleReviewer || user.Role == auth.RoleViewer {
		return resp.Forbidden("评审员和查看者不能编辑方案")
	}

	if user.Role != auth.RoleAdmin && !s.CanOperateAsSupplier(proposal, user) {
		return resp.Forbidden("只能编辑本供应商的方案")
	}

	if proposal.Status != model.StatusDraft && proposal.Status != model.StatusRejected {
		return resp.Biz("当前状态的方案不允许编辑")
	}

	return nil
}

func (s *ProposalService) permissions(user *model.User, proposal *model.Proposal) map[string]bool {
	isAdmin := user.Role == auth.RoleAdmin
	isOwner := s.CanOperateAsSupplier(proposal, user)
	editableStatus := proposal.Status == model.StatusDraft || proposal.Status == model.StatusRejected

	activeChange := false
	if proposal.Status == model.StatusArchived {
		activeChange = s.HasActiveChange(proposal.ID)
	}

	return map[string]bool{
		"view":     true,
		"edit":     (isAdmin || isOwner) && editableStatus,
		"submit":   (isAdmin || isOwner) && editableStatus,
		"withdraw": (isAdmin || isOwner) && proposal.Status == model.StatusPending && proposal.ReviewTime == nil,
		"review":   (isAdmin || user.Role == auth.RoleReviewer) && proposal.Status == model.StatusPending,
		"archive":  isAdmin && proposal.Status == model.StatusApproved,
		"change":   (isAdmin || isOwner) && proposal.Status == model.StatusArchived && !activeChange,
		"delete":   (isAdmin || (isOwner && proposal.SourceProposalID != nil)) && proposal.Status == model.StatusDraft,
		"export":   true,
	}
}

func (s *ProposalService) HasActiveChange(sourceID string) bool {
	var count int64

	s.DB.Model(&model.Proposal{}).
		Where("source_proposal_id = ? AND status IN ?", sourceID, model.ActiveStatuses).
		Count(&count)

	return count > 0
}

// ------------------------------------------------------------------
// 字段派生与完整性校验
// ------------------------------------------------------------------

func (s *ProposalService) derive(input *dto.ProposalFields) error {
	for _, group := range []struct {
		label  string
		values [3]float64
	}{
		{"产品外形尺寸", [3]float64{input.PartSizeL, input.PartSizeW, input.PartSizeH}},
		{"包装箱外尺寸", [3]float64{input.BoxSizeL, input.BoxSizeW, input.BoxSizeH}},
		{"满托盘外尺寸", [3]float64{input.PalletSizeL, input.PalletSizeW, input.PalletSizeH}},
	} {
		for _, value := range group.values {
			if value != math.Trunc(value) {
				return resp.Biz(group.label + "只允许填写整数（mm）")
			}
		}
	}

	if input.WithPallet == nil || *input.WithPallet != 1 {
		input.PalletSizeL, input.PalletSizeW, input.PalletSizeH = 0, 0, 0
		input.PalletLayerQty, input.PalletLayerCount = 0, 0
	}

	return nil
}

func numberText(value float64) string {
	return strconv.FormatFloat(value, 'f', -1, 64)
}

func (s *ProposalService) applyDerived(p *model.Proposal) {
	p.WriteDate = time.Now().Format("2006-01-02")
	p.Spec = fmt.Sprintf("%s*%s*%smm", numberText(p.BoxSizeL), numberText(p.BoxSizeW), numberText(p.BoxSizeH))
	p.Quantity = p.PackingQty

	if p.WithPallet != nil && *p.WithPallet == 1 {
		p.PalletPartsQty = p.PalletLayerQty * p.PalletLayerCount * p.PackingQty
	} else {
		p.PalletPartsQty = 0
		p.PalletSizeL, p.PalletSizeW, p.PalletSizeH = 0, 0, 0
		p.PalletLayerQty, p.PalletLayerCount = 0, 0
	}
}

func (s *ProposalService) assertSubmitReady(p *model.Proposal) error {
	missing := make([]string, 0, 24)

	required := map[string]string{
		"填写日期": p.WriteDate, "客户名称": p.CustomerName, "客户地址": p.CustomerAddress,
		"客户联系人": p.CustomerContact, "客户电话": p.CustomerPhone, "客户邮箱": p.CustomerEmail,
		"客户传真": p.CustomerFax, "供应商名称": p.SupplierName, "供应商代码": p.SupplierCode,
		"供应商地址": p.SupplierAddress, "供应商联系人": p.Contact, "供应商电话": p.Phone,
		"供应商邮箱": p.SupplierEmail, "供应商传真": p.SupplierFax, "零件号": p.PartNo,
		"零件名称": p.PartName, "项目车型": p.ProjectModel, "零件材料": p.PartMaterial,
		"包装箱编号": p.PackBoxNo, "包装箱材料": p.PackMaterial,
	}

	for label, value := range required {
		if strings.TrimSpace(value) == "" {
			missing = append(missing, label)
		}
	}

	positive := map[string]float64{
		"产品净重": p.PartWeight, "存放数量": float64(p.PackingQty), "毛重": p.GrossWeight,
	}

	for label, value := range positive {
		if value <= 0 {
			missing = append(missing, label)
		}
	}

	for _, group := range []struct {
		label  string
		values [3]float64
	}{
		{"产品外形尺寸", [3]float64{p.PartSizeL, p.PartSizeW, p.PartSizeH}},
		{"包装箱外尺寸", [3]float64{p.BoxSizeL, p.BoxSizeW, p.BoxSizeH}},
	} {
		for _, value := range group.values {
			if value <= 0 {
				missing = append(missing, group.label)
				break
			}
		}
	}

	if p.WithPallet != nil && *p.WithPallet == 1 {
		if p.PalletSizeL <= 0 || p.PalletSizeW <= 0 || p.PalletSizeH <= 0 {
			missing = append(missing, "满托盘外尺寸")
		}
		if p.PalletLayerQty <= 0 {
			missing = append(missing, "每层包装箱数量")
		}
		if p.PalletLayerCount <= 0 {
			missing = append(missing, "堆码层数")
		}
		if p.PalletPartsQty <= 0 {
			missing = append(missing, "每托盘零件数量")
		}
	}

	if p.Returnable == nil {
		missing = append(missing, "包装箱是否返回")
	}
	if p.DunnageReturnable == nil {
		missing = append(missing, "内衬是否返回")
	}
	if p.WithPallet == nil {
		missing = append(missing, "是否带托盘交付")
	}

	missing = uniqueStrings(missing)

	var missingImages []string

	for _, attachType := range model.ImageAttachTypes {
		var count int64
		s.DB.Model(&model.Attachment{}).
			Where("proposal_id = ? AND attach_type = ?", p.ID, attachType).
			Count(&count)

		if count == 0 {
			missingImages = append(missingImages, model.AttachTypeText[attachType])
		}
	}

	problems := make([]string, 0, 3)

	if len(missing) > 0 {
		problems = append(problems, "以下必填项未填写："+strings.Join(missing, "、"))
	}
	if len(missingImages) > 0 {
		problems = append(problems, "以下图片未上传："+strings.Join(missingImages, "、"))
	}

	minGross := p.PartWeight * float64(p.PackingQty)
	if p.GrossWeight > 0 && minGross > 0 && p.GrossWeight <= minGross {
		problems = append(problems, fmt.Sprintf("毛重必须大于单件净重 × 存放件数（即大于 %s kg）", trimNumber(minGross)))
	}

	if len(problems) > 0 {
		return resp.Biz(strings.Join(problems, "；"))
	}

	return nil
}

func trimNumber(value float64) string {
	text := strconv.FormatFloat(value, 'f', 3, 64)
	text = strings.TrimRight(text, "0")
	text = strings.TrimRight(text, ".")

	if text == "" {
		return "0"
	}

	return text
}

func uniqueStrings(items []string) []string {
	seen := make(map[string]struct{}, len(items))
	out := make([]string, 0, len(items))

	for _, item := range items {
		if _, ok := seen[item]; ok {
			continue
		}
		seen[item] = struct{}{}
		out = append(out, item)
	}

	return out
}

// ------------------------------------------------------------------
// 写操作
// ------------------------------------------------------------------

func (s *ProposalService) generateNo(tx *gorm.DB) (string, error) {
	prefix := "PS" + time.Now().Format("20060102")

	var last string
	err := tx.Unscoped().Model(&model.Proposal{}).
		Where("proposal_no LIKE ?", prefix+"%").
		Order("proposal_no DESC").
		Limit(1).
		Pluck("proposal_no", &last).Error

	if err != nil {
		return "", err
	}

	seq := 1
	if len(last) >= 4 {
		if value, err := strconv.Atoi(last[len(last)-4:]); err == nil {
			seq = value + 1
		}
	}

	return fmt.Sprintf("%s%04d", prefix, seq), nil
}

func (s *ProposalService) assertPartUnique(tx *gorm.DB, supplierID, partNo, excludeID string) error {
	if supplierID == "" || partNo == "" {
		return resp.Biz("请填写供应商与零件号")
	}

	active := tx.Model(&model.Proposal{}).Where("supplier_id = ? AND part_no = ?", supplierID, partNo)
	if excludeID != "" {
		active = active.Where("id <> ?", excludeID)
	}

	var activeCount int64
	if err := active.Where("status IN ?", model.ActiveStatuses).Count(&activeCount).Error; err != nil {
		return err
	}

	if activeCount > 0 {
		return resp.Biz("该供应商的此零件号已存在未归档方案，不能重复创建")
	}

	var archivedCount int64
	if err := tx.Model(&model.Proposal{}).
		Where("supplier_id = ? AND part_no = ? AND status = ?", supplierID, partNo, model.StatusArchived).
		Count(&archivedCount).Error; err != nil {
		return err
	}

	if archivedCount > 0 {
		return resp.Biz("该零件号已有归档方案，请通过「发起变更」修改")
	}

	return nil
}

// applyInput 以档案为准填充供应商 / 客户 / 零件快照，忽略前端伪造值
// requestedSupplierID 仅管理员生效，供应商角色强制为账号所属供应商
func (s *ProposalService) applyInput(tx *gorm.DB, p *model.Proposal, input *dto.ProposalFields, requestedSupplierID string, user *model.User) error {
	supplierID := p.SupplierID

	if user.Role == auth.RoleAdmin {
		if requestedSupplierID != "" {
			supplierID = requestedSupplierID
		}
	} else if user.SupplierID != nil {
		supplierID = *user.SupplierID
	}

	if supplierID == "" {
		return resp.Biz("请选择供应商")
	}

	var supplier model.Supplier
	if err := tx.First(&supplier, "id = ?", supplierID).Error; err != nil {
		return resp.Biz("供应商不存在或已被删除")
	}

	p.SupplierID = supplier.ID
	p.SupplierName = supplier.Name
	p.SupplierCode = supplier.Code
	p.SupplierAddress = supplier.Address

	// 零件号必须在该供应商的指定零件号清单内；历史单据沿用原零件号时放行
	partNo := strings.TrimSpace(input.PartNo)
	if partNo != "" {
		var part model.SupplierPart
		err := tx.Where("supplier_id = ? AND part_no = ?", supplier.ID, partNo).First(&part).Error

		if err != nil && partNo != p.PartNo {
			return resp.Biz("该零件号不在本供应商的指定零件号清单内")
		}

		p.PartNo = partNo
		if err == nil {
			p.PartName = part.PartName
			p.ProjectModel = part.ProjectName
		}
	}

	if input.CustomerID != nil && *input.CustomerID != "" {
		var customer model.Customer
		if err := tx.First(&customer, "id = ?", *input.CustomerID).Error; err != nil {
			return resp.Biz("客户不存在或已被删除")
		}

		p.CustomerID = &customer.ID
		p.CustomerName = customer.Name
		p.CustomerAddress = customer.Address
		p.CustomerContact = customer.Contact
		p.CustomerPhone = customer.Phone
		p.CustomerEmail = customer.Email
		p.CustomerFax = customer.Fax
	} else {
		p.CustomerID = nil
		p.CustomerName = strings.TrimSpace(input.CustomerName)
		p.CustomerAddress = strings.TrimSpace(input.CustomerAddress)
		p.CustomerContact = strings.TrimSpace(input.CustomerContact)
		p.CustomerPhone = strings.TrimSpace(input.CustomerPhone)
		p.CustomerEmail = strings.TrimSpace(input.CustomerEmail)
		p.CustomerFax = strings.TrimSpace(input.CustomerFax)
	}

	p.Contact = strings.TrimSpace(input.Contact)
	p.Phone = strings.TrimSpace(input.Phone)
	p.SupplierEmail = strings.TrimSpace(input.SupplierEmail)
	p.SupplierFax = strings.TrimSpace(input.SupplierFax)

	p.PartMaterial = strings.TrimSpace(input.PartMaterial)
	p.PackBoxNo = strings.TrimSpace(input.PackBoxNo)
	p.PackMaterial = strings.TrimSpace(input.PackMaterial)
	p.PartWeight = input.PartWeight
	p.PartSizeL, p.PartSizeW, p.PartSizeH = input.PartSizeL, input.PartSizeW, input.PartSizeH
	p.BoxSizeL, p.BoxSizeW, p.BoxSizeH = input.BoxSizeL, input.BoxSizeW, input.BoxSizeH
	p.GrossWeight = input.GrossWeight
	p.Returnable = input.Returnable
	p.DunnageReturnable = input.DunnageReturnable
	p.WithPallet = input.WithPallet
	p.PackingQty = input.PackingQty
	p.PackagingInstruction = input.PackagingInstruction
	p.Description = input.Description
	p.PalletSizeL, p.PalletSizeW, p.PalletSizeH = input.PalletSizeL, input.PalletSizeW, input.PalletSizeH
	p.PalletLayerQty, p.PalletLayerCount = input.PalletLayerQty, input.PalletLayerCount

	s.applyDerived(p)

	return nil
}

func (s *ProposalService) Create(user *model.User, input *dto.ProposalCreateInput, ip string) (*dto.ProposalDetail, error) {
	if user.Role != auth.RoleAdmin {
		return nil, resp.Forbidden("仅管理员可以新增方案")
	}

	if err := s.derive(&input.ProposalFields); err != nil {
		return nil, err
	}

	var created model.Proposal

	err := s.DB.Transaction(func(tx *gorm.DB) error {
		proposal := model.Proposal{ID: ulidx.New(), Version: 1, Status: model.StatusDraft, CreatedBy: user.ID}

		if err := s.applyInput(tx, &proposal, &input.ProposalFields, input.SupplierID, user); err != nil {
			return err
		}

		if err := s.assertPartUnique(tx, proposal.SupplierID, proposal.PartNo, ""); err != nil {
			return err
		}

		no, err := s.generateNo(tx)
		if err != nil {
			return err
		}

		proposal.ProposalNo = no

		if err := tx.Create(&proposal).Error; err != nil {
			return err
		}

		created = proposal

		return Record(tx, AuditEntry{
			ProposalID: &proposal.ID, UserID: user.ID, Action: "create",
			ToStatus: Int8(model.StatusDraft), Version: 1,
			Content: "新增方案：" + proposal.PartNo, IP: ip,
		})
	})

	if err != nil {
		return nil, err
	}

	return s.Get(user, created.ID)
}

func (s *ProposalService) Update(user *model.User, id string, input *dto.ProposalUpdateInput, ip string) (*dto.ProposalDetail, error) {
	if err := s.derive(&input.ProposalFields); err != nil {
		return nil, err
	}

	err := s.DB.Transaction(func(tx *gorm.DB) error {
		var proposal model.Proposal
		if err := tx.First(&proposal, "id = ?", id).Error; err != nil {
			return resp.ErrNotFound
		}

		if err := s.AssertEditable(&proposal, user); err != nil {
			return err
		}

		originalPart := proposal.PartNo + "|" + proposal.SupplierID

		if err := s.applyInput(tx, &proposal, &input.ProposalFields, input.SupplierID, user); err != nil {
			return err
		}

		if originalPart != proposal.PartNo+"|"+proposal.SupplierID {
			if err := s.assertPartUnique(tx, proposal.SupplierID, proposal.PartNo, proposal.ID); err != nil {
				return err
			}
		}

		if err := tx.Save(&proposal).Error; err != nil {
			return err
		}

		return Record(tx, AuditEntry{
			ProposalID: &proposal.ID, UserID: user.ID, Action: "update",
			FromStatus: Int8(proposal.Status), ToStatus: Int8(proposal.Status),
			Version: proposal.Version, Content: "编辑方案：" + proposal.PartNo, IP: ip,
		})
	})

	if err != nil {
		return nil, err
	}

	return s.Get(user, id)
}

func (s *ProposalService) Submit(user *model.User, id, ip string) error {
	return s.DB.Transaction(func(tx *gorm.DB) error {
		var proposal model.Proposal
		if err := tx.First(&proposal, "id = ?", id).Error; err != nil {
			return resp.ErrNotFound
		}

		if proposal.Status != model.StatusDraft && proposal.Status != model.StatusRejected {
			return resp.Biz("仅草稿或驳回状态的方案可以提交评审")
		}

		if user.Role == auth.RoleViewer || user.Role == auth.RoleReviewer {
			return resp.Forbidden("评审员和查看者不能提交评审")
		}

		if user.Role != auth.RoleAdmin && !s.CanOperateAsSupplier(&proposal, user) {
			return resp.Forbidden("只能提交本供应商的方案")
		}

		if err := s.assertSubmitReady(&proposal); err != nil {
			return err
		}

		from := proposal.Status
		version := proposal.Version
		if from == model.StatusRejected {
			version++
		}

		now := time.Now()
		if err := tx.Model(&proposal).Updates(map[string]any{
			"status": model.StatusPending, "version": version, "submit_time": now,
		}).Error; err != nil {
			return err
		}

		return Record(tx, AuditEntry{
			ProposalID: &proposal.ID, UserID: user.ID, Action: "submit",
			FromStatus: Int8(from), ToStatus: Int8(model.StatusPending), Version: version,
			Content: fmt.Sprintf("第 %d 版提交评审", version), IP: ip,
		})
	})
}

func (s *ProposalService) Withdraw(user *model.User, id, ip string) error {
	return s.DB.Transaction(func(tx *gorm.DB) error {
		var proposal model.Proposal
		if err := tx.First(&proposal, "id = ?", id).Error; err != nil {
			return resp.ErrNotFound
		}

		if proposal.Status != model.StatusPending {
			return resp.Biz("仅待评审状态的方案可以撤回")
		}

		if user.Role == auth.RoleViewer || user.Role == auth.RoleReviewer {
			return resp.Forbidden("评审员和查看者不能撤回方案")
		}

		if user.Role != auth.RoleAdmin && !s.CanOperateAsSupplier(&proposal, user) {
			return resp.Forbidden("只能撤回本供应商的方案")
		}

		if proposal.ReviewTime != nil {
			return resp.Biz("方案已完成评审，无法撤回")
		}

		if err := tx.Model(&proposal).Updates(map[string]any{
			"status": model.StatusDraft, "submit_time": nil,
		}).Error; err != nil {
			return err
		}

		return Record(tx, AuditEntry{
			ProposalID: &proposal.ID, UserID: user.ID, Action: "withdraw",
			FromStatus: Int8(model.StatusPending), ToStatus: Int8(model.StatusDraft),
			Version: proposal.Version, Content: "撤回提交，退回草稿", IP: ip,
		})
	})
}

func (s *ProposalService) Review(user *model.User, id string, input dto.ReviewInput, ip string) error {
	if user.Role != auth.RoleAdmin && user.Role != auth.RoleReviewer {
		return resp.Forbidden("仅评审员或管理员可以评审")
	}

	remark := strings.TrimSpace(input.Remark)

	if input.Status != model.StatusApproved && input.Status != model.StatusRejected {
		return resp.Biz("评审结果不合法")
	}

	if input.Status == model.StatusRejected && remark == "" {
		return resp.Biz("驳回时必须填写评审意见")
	}

	if runes := []rune(remark); len(runes) > 500 {
		remark = string(runes[:500])
	}

	return s.DB.Transaction(func(tx *gorm.DB) error {
		var proposal model.Proposal
		if err := tx.First(&proposal, "id = ?", id).Error; err != nil {
			return resp.ErrNotFound
		}

		if proposal.Status != model.StatusPending {
			return resp.Biz("仅待评审状态的方案可以评审")
		}

		if err := tx.Model(&proposal).Updates(map[string]any{
			"status":        input.Status,
			"reviewer_id":   user.ID,
			"review_remark": remark,
			"review_time":   time.Now(),
		}).Error; err != nil {
			return err
		}

		action := "review_pass"
		if input.Status == model.StatusRejected {
			action = "review_reject"
		}

		content := remark
		if content == "" {
			content = "评审通过"
		}

		return Record(tx, AuditEntry{
			ProposalID: &proposal.ID, UserID: user.ID, Action: action,
			FromStatus: Int8(model.StatusPending), ToStatus: Int8(input.Status),
			Version: proposal.Version, Content: content, IP: ip,
		})
	})
}

// Archive 批量归档；变更方案归档生效后原方案自动作废
func (s *ProposalService) Archive(user *model.User, ids []string, ip string) (int, error) {
	unique := make([]string, 0, len(ids))
	seen := make(map[string]struct{}, len(ids))

	for _, id := range ids {
		if _, ok := seen[id]; ok || strings.TrimSpace(id) == "" {
			continue
		}
		seen[id] = struct{}{}
		unique = append(unique, id)
	}

	if len(unique) == 0 {
		return 0, resp.Param("请选择要归档的方案")
	}

	count := 0

	err := s.DB.Transaction(func(tx *gorm.DB) error {
		var proposals []model.Proposal
		if err := tx.Where("id IN ? AND status = ?", unique, model.StatusApproved).Find(&proposals).Error; err != nil {
			return err
		}

		if len(proposals) == 0 {
			return resp.Biz("所选方案中没有可归档的已通过方案")
		}

		for i := range proposals {
			proposal := proposals[i]
			now := time.Now()

			if err := tx.Model(&proposal).Updates(map[string]any{
				"status": model.StatusArchived, "archive_time": now,
			}).Error; err != nil {
				return err
			}

			if err := Record(tx, AuditEntry{
				ProposalID: &proposal.ID, UserID: user.ID, Action: "archive",
				FromStatus: Int8(model.StatusApproved), ToStatus: Int8(model.StatusArchived),
				Version: proposal.Version, Content: "方案归档：" + proposal.PartNo, IP: ip,
			}); err != nil {
				return err
			}

			if proposal.SourceProposalID != nil {
				var source model.Proposal
				if err := tx.First(&source, "id = ?", *proposal.SourceProposalID).Error; err == nil &&
					source.Status == model.StatusArchived {
					if err := tx.Model(&source).Update("status", model.StatusObsolete).Error; err != nil {
						return err
					}

					if err := Record(tx, AuditEntry{
						ProposalID: &source.ID, UserID: user.ID, Action: "obsolete",
						FromStatus: Int8(model.StatusArchived), ToStatus: Int8(model.StatusObsolete),
						Version: source.Version,
						Content: "变更方案 " + proposal.ProposalNo + " 已归档生效，原方案作废", IP: ip,
					}); err != nil {
						return err
					}
				}
			}

			count++
		}

		return nil
	})

	if err != nil {
		return 0, err
	}

	return count, nil
}

// Change 发起变更：复制源方案字段与附件，生成版本 +1 的独立草稿
func (s *ProposalService) Change(user *model.User, id, ip string) (*dto.ProposalDetail, error) {
	var newID string

	err := s.DB.Transaction(func(tx *gorm.DB) error {
		var source model.Proposal
		if err := tx.First(&source, "id = ?", id).Error; err != nil {
			return resp.ErrNotFound
		}

		if source.Status != model.StatusArchived {
			return resp.Biz("仅「已归档」的方案可以发起变更")
		}

		if user.Role == auth.RoleViewer || user.Role == auth.RoleReviewer {
			return resp.Forbidden("评审员和查看者不能发起变更")
		}

		if user.Role != auth.RoleAdmin && !s.CanOperateAsSupplier(&source, user) {
			return resp.Forbidden("只能对本供应商的方案发起变更")
		}

		var active int64
		if err := tx.Model(&model.Proposal{}).
			Where("source_proposal_id = ? AND status IN ?", source.ID, model.ActiveStatuses).
			Count(&active).Error; err != nil {
			return err
		}

		if active > 0 {
			return resp.Biz("该方案已有进行中的变更，不能重复发起")
		}

		no, err := s.generateNo(tx)
		if err != nil {
			return err
		}

		copied := source
		copied.ID = ulidx.New()
		copied.ProposalNo = no
		copied.Version = source.Version + 1
		copied.SourceProposalID = &source.ID
		copied.Status = model.StatusDraft
		copied.CreatedBy = user.ID
		copied.ReviewerID = nil
		copied.ReviewRemark = ""
		copied.SubmitTime = nil
		copied.ReviewTime = nil
		copied.ArchiveTime = nil
		copied.CreatedAt = time.Time{}
		copied.UpdatedAt = time.Time{}
		copied.DeletedAt = gorm.DeletedAt{}

		if err := tx.Create(&copied).Error; err != nil {
			return err
		}

		newID = copied.ID

		var attachments []model.Attachment
		if err := tx.Where("proposal_id = ?", source.ID).Find(&attachments).Error; err != nil {
			return err
		}

		for _, item := range attachments {
			copyItem := item
			copyItem.ID = ulidx.New()
			copyItem.ProposalID = &copied.ID
			copyItem.UploadedBy = user.ID
			copyItem.CreatedAt = time.Time{}

			if err := tx.Create(&copyItem).Error; err != nil {
				return err
			}
		}

		return Record(tx, AuditEntry{
			ProposalID: &copied.ID, UserID: user.ID, Action: "change",
			ToStatus: Int8(model.StatusDraft), Version: copied.Version,
			Content: "由方案 " + source.ProposalNo + "（V" + strconv.Itoa(source.Version) + "）发起变更", IP: ip,
		})
	})

	if err != nil {
		return nil, err
	}

	return s.Get(user, newID)
}

func (s *ProposalService) Delete(user *model.User, id, ip string) error {
	return s.DB.Transaction(func(tx *gorm.DB) error {
		var proposal model.Proposal
		if err := tx.First(&proposal, "id = ?", id).Error; err != nil {
			return resp.ErrNotFound
		}

		if proposal.Status != model.StatusDraft {
			return resp.Biz("仅草稿状态的方案可以删除")
		}

		isAdmin := user.Role == auth.RoleAdmin

		if user.Role == auth.RoleViewer {
			return resp.Forbidden("查看者不能删除方案")
		}

		if !isAdmin && !s.CanOperateAsSupplier(&proposal, user) {
			return resp.Forbidden("只能删除本供应商的方案")
		}

		if !isAdmin && proposal.SourceProposalID == nil {
			return resp.Biz("由零件号导入生成的方案不能删除，仅变更草稿可删除（终止变更）")
		}

		if err := Record(tx, AuditEntry{
			ProposalID: &proposal.ID, UserID: user.ID, Action: "delete",
			FromStatus: Int8(proposal.Status), Version: proposal.Version,
			Content: "删除方案：" + proposal.PartNo, IP: ip,
		}); err != nil {
			return err
		}

		return tx.Delete(&proposal).Error
	})
}

func escapeLike(value string) string {
	replacer := strings.NewReplacer("\\", "\\\\", "%", "\\%", "_", "\\_")

	return replacer.Replace(value)
}
