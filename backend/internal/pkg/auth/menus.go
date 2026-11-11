package auth

// Menu 前端动态菜单 / 路由节点
type Menu struct {
	Path       string `json:"path"`
	Name       string `json:"name"`
	Title      string `json:"title"`
	Icon       string `json:"icon,omitempty"`
	Component  string `json:"component,omitempty"`
	Permission string `json:"permission,omitempty"`
	Hidden     bool   `json:"hidden,omitempty"`
	Children   []Menu `json:"children,omitempty"`
}

// 权限码：前端 v-permission 与后端 RequirePermission 共用
const (
	PermProposalView     = "proposal:view"
	PermProposalUpdate   = "proposal:update"
	PermProposalSubmit   = "proposal:submit"
	PermProposalWithdraw = "proposal:withdraw"
	PermProposalDelete   = "proposal:delete"
	PermProposalReview   = "proposal:review"
	PermProposalArchive  = "proposal:archive"
	PermProposalChange   = "proposal:change"
	PermProposalExport   = "proposal:export"
	PermProposalAll      = "proposal:all"
	PermContactManage    = "contact:manage"
	PermMasterManage     = "master:manage"
	PermUserManage       = "user:manage"
	PermAuditView        = "audit:view"
	PermReportView       = "report:view"
)

var rolePermissions = map[int8][]string{
	RoleSupplier: {
		PermProposalView, PermProposalUpdate, PermProposalSubmit, PermProposalWithdraw,
		PermProposalDelete, PermProposalChange, PermProposalExport, PermContactManage,
	},
	RoleReviewer: {
		PermProposalView, PermProposalReview, PermProposalExport,
	},
	RoleViewer: {
		PermProposalView, PermProposalExport,
	},
	RoleAdmin: {
		PermProposalView, PermProposalUpdate, PermProposalSubmit, PermProposalWithdraw,
		PermProposalDelete, PermProposalReview, PermProposalArchive, PermProposalChange,
		PermProposalExport, PermProposalAll, PermContactManage, PermMasterManage,
		PermUserManage, PermAuditView, PermReportView,
	},
}

func Permissions(role int8) []string {
	perms, ok := rolePermissions[role]
	if !ok {
		return []string{}
	}

	out := make([]string, len(perms))
	copy(out, perms)

	return out
}

// HasPermission 判断角色是否具备某权限码
func HasPermission(role int8, perm string) bool {
	for _, item := range rolePermissions[role] {
		if item == perm {
			return true
		}
	}

	return false
}

// Routes 返回可动态注册的扁平路由（含隐藏详情页）
func Routes(role int8) []Menu {
	routes := []Menu{
		{Path: "/dashboard", Name: "Dashboard", Title: "工作台", Component: "dashboard/index", Permission: PermProposalView},
		{Path: "/proposals", Name: "ProposalList", Title: "包装方案", Component: "proposal/list", Permission: PermProposalView},
		{
			Path: "/proposals/:id", Name: "ProposalDetail", Title: "方案详情",
			Component: "proposal/detail", Permission: PermProposalView, Hidden: true,
		},
	}

	if HasPermission(role, PermProposalUpdate) {
		routes = append(routes, Menu{
			Path: "/proposals/:id/edit", Name: "ProposalEdit", Title: "编辑方案",
			Component: "proposal/form", Permission: PermProposalUpdate, Hidden: true,
		})
	}

	if HasPermission(role, PermContactManage) {
		routes = append(routes, Menu{
			Path: "/contacts", Name: "SupplierContacts", Title: "联系人维护",
			Component: "contacts/index", Permission: PermContactManage,
		})
	}

	if HasPermission(role, PermUserManage) {
		routes = append(routes, Menu{
			Path: "/system/users", Name: "SystemUsers", Title: "用户管理",
			Component: "admin/user", Permission: PermUserManage,
		})
	}

	if HasPermission(role, PermMasterManage) {
		routes = append(routes,
			Menu{Path: "/system/customers", Name: "SystemCustomers", Title: "客户管理", Component: "admin/customer", Permission: PermMasterManage},
			Menu{Path: "/system/suppliers", Name: "SystemSuppliers", Title: "供应商管理", Component: "admin/supplier", Permission: PermMasterManage},
			Menu{Path: "/system/parts", Name: "SystemParts", Title: "零件号管理", Component: "admin/part", Permission: PermMasterManage},
			Menu{Path: "/system/dicts", Name: "SystemDicts", Title: "字典管理", Component: "admin/dict", Permission: PermMasterManage},
		)
	}

	if HasPermission(role, PermProposalAll) {
		routes = append(routes, Menu{
			Path: "/admin/proposals", Name: "AdminProposals", Title: "全量方案",
			Component: "proposal/list", Permission: PermProposalAll,
		})
	}

	if HasPermission(role, PermReportView) {
		routes = append(routes, Menu{
			Path: "/admin/proposals/detail", Name: "AdminProposalReport", Title: "全量详细方案",
			Component: "admin/report", Permission: PermReportView,
		})
	}

	if HasPermission(role, PermAuditView) {
		routes = append(routes, Menu{
			Path: "/audit", Name: "AuditLogs", Title: "审计日志",
			Component: "audit/index", Permission: PermAuditView,
		})
	}

	return routes
}

// Menus 返回侧边栏菜单树
func Menus(role int8) []Menu {
	menus := []Menu{
		{Path: "/dashboard", Name: "Dashboard", Title: "工作台", Icon: "Odometer", Component: "dashboard/index"},
		{Path: "/proposals", Name: "ProposalList", Title: "包装方案", Icon: "Document", Component: "proposal/list"},
	}

	if HasPermission(role, PermContactManage) {
		menus = append(menus, Menu{
			Path: "/contacts", Name: "SupplierContacts", Title: "联系人维护",
			Icon: "Phone", Component: "contacts/index",
		})
	}

	if HasPermission(role, PermUserManage) || HasPermission(role, PermMasterManage) ||
		HasPermission(role, PermProposalAll) || HasPermission(role, PermAuditView) {
		children := make([]Menu, 0, 7)

		if HasPermission(role, PermUserManage) {
			children = append(children, Menu{Path: "/system/users", Name: "SystemUsers", Title: "用户管理", Icon: "User"})
		}

		if HasPermission(role, PermMasterManage) {
			children = append(children,
				Menu{Path: "/system/customers", Name: "SystemCustomers", Title: "客户管理", Icon: "OfficeBuilding"},
				Menu{Path: "/system/suppliers", Name: "SystemSuppliers", Title: "供应商管理", Icon: "Shop"},
				Menu{Path: "/system/parts", Name: "SystemParts", Title: "零件号管理", Icon: "Box"},
				Menu{Path: "/system/dicts", Name: "SystemDicts", Title: "字典管理", Icon: "Collection"},
			)
		}

		if HasPermission(role, PermProposalAll) {
			children = append(children, Menu{
				Path: "/admin/proposals", Name: "AdminProposals", Title: "全量方案", Icon: "Files",
			})
		}

		if HasPermission(role, PermReportView) {
			children = append(children, Menu{
				Path: "/admin/proposals/detail", Name: "AdminProposalReport", Title: "全量详细方案", Icon: "DataAnalysis",
			})
		}

		if HasPermission(role, PermAuditView) {
			children = append(children, Menu{
				Path: "/audit", Name: "AuditLogs", Title: "审计日志", Icon: "Tickets",
			})
		}

		menus = append(menus, Menu{
			Path: "/system", Name: "System", Title: "系统管理", Icon: "Setting", Children: children,
		})
	}

	return menus
}
