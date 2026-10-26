/** 角色枚举 */
export const ROLE = {
  SUBMITTER: 1,
  REVIEWER: 2,
  ADMIN: 3,
};

/** 角色文字 */
export const ROLE_TEXT = {
  [ROLE.SUBMITTER]: '供应商',
  [ROLE.REVIEWER]: '评审员',
  [ROLE.ADMIN]: '管理员',
};

/** 角色下拉选项 */
export const ROLE_OPTIONS = [
  { label: '供应商', value: ROLE.SUBMITTER },
  { label: '评审员', value: ROLE.REVIEWER },
  { label: '管理员', value: ROLE.ADMIN },
];

/** 业务状态枚举 */
export const STATUS = {
  DRAFT: 0,
  PENDING: 1,
  APPROVED: 2,
  REJECTED: 3,
  ARCHIVED: 4,
  ARCHIVED_OBSOLETE: 5,
};

/** 状态渲染配置（label + el-tag type） */
export const STATUS_MAP = {
  [STATUS.DRAFT]: { label: '草稿', type: 'info' },
  [STATUS.PENDING]: { label: '待评审', type: 'warning' },
  [STATUS.APPROVED]: { label: '评审通过', type: 'success' },
  [STATUS.REJECTED]: { label: '驳回', type: 'danger' },
  [STATUS.ARCHIVED]: { label: '已归档', type: 'primary' },
  [STATUS.ARCHIVED_OBSOLETE]: { label: '已归档-已变更-作废', type: 'info' },
};

/** 状态下拉选项 */
export const STATUS_OPTIONS = Object.entries(STATUS_MAP).map(([value, item]) => ({
  label: item.label,
  value: Number(value),
}));

/** 字典类型 */
export const DICT_TYPE = {
  CUSTOMER: 'customer',
};

/** 允许上传的扩展名 */
export const ACCEPT_EXT = '.pdf,.dwg,.xlsx,.docx,.png,.jpg,.jpeg';

/** 允许上传的扩展名数组 */
export const ACCEPT_EXT_LIST = ['pdf', 'dwg', 'xlsx', 'docx', 'png', 'jpg', 'jpeg'];

/** 允许上传的图片扩展名 */
export const IMAGE_EXT_LIST = ['png', 'jpg', 'jpeg'];

/** 单文件大小限制 10MB */
export const MAX_FILE_SIZE = 10 * 1024 * 1024;

/** 允许在线预览的扩展名 */
export const PREVIEW_EXT = ['pdf', 'png', 'jpg', 'jpeg'];

/** 审计日志动作映射 */
export const ACTION_TEXT = {
  create: '新增方案',
  update: '编辑方案',
  submit: '提交评审',
  review_pass: '评审通过',
  review_reject: '评审驳回',
  archive: '方案归档',
  change: '发起变更',
  obsolete: '变更作废原方案',
  delete: '删除方案',
  upload: '上传附件',
  delete_attachment: '删除附件',
  login: '登录系统',
  logout: '退出登录',
  password_change: '修改密码',
  user_create: '新增用户',
  user_update: '编辑用户',
  user_status: '启用/禁用用户',
  user_reset: '重置密码',
  dict_create: '新增字典',
  dict_update: '编辑字典',
  dict_delete: '删除字典',
  customer_create: '新增客户',
  customer_update: '编辑客户',
  customer_delete: '删除客户',
  supplier_create: '新增供应商',
  supplier_update: '编辑供应商',
  supplier_delete: '删除供应商',
  part_import: '导入指定零件号',
  part_update: '修改指定零件号',
  part_delete: '删除指定零件号',
};

/** 审计日志动作下拉选项 */
export const ACTION_OPTIONS = Object.entries(ACTION_TEXT).map(([value, label]) => ({
  label,
  value,
}));

/** 附件类型（包装确认单图片区） */
export const ATTACH_TYPE = {
  PART: 'part',
  INNER: 'inner',
  FINAL: 'final',
  PACKAGING: 'packaging',
  ATTACHMENT: 'attachment',
};

/** 图片类附件类型 */
export const IMAGE_ATTACH_TYPES = [
  ATTACH_TYPE.PART,
  ATTACH_TYPE.INNER,
  ATTACH_TYPE.FINAL,
  ATTACH_TYPE.PACKAGING,
];

/** 附件类型文字 */
export const ATTACH_TYPE_LABEL = {
  [ATTACH_TYPE.PART]: '零件图片',
  [ATTACH_TYPE.INNER]: '内部排列',
  [ATTACH_TYPE.FINAL]: '整体包装',
  [ATTACH_TYPE.PACKAGING]: '包装图片',
  [ATTACH_TYPE.ATTACHMENT]: '其他附件',
};

/** 包装箱材料选项 */
export const PACK_MATERIAL_OPTIONS = [
  { label: '塑料', value: 'plastic' },
  { label: '金属', value: 'metal' },
  { label: '纸箱', value: 'carton' },
  { label: '其他', value: 'other' },
];
