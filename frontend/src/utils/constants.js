export const ROLE = {
  SUPPLIER: 1,
  REVIEWER: 2,
  ADMIN: 3,
  VIEWER: 4,
};

export const ROLE_TEXT = {
  [ROLE.SUPPLIER]: '供应商',
  [ROLE.REVIEWER]: '评审员',
  [ROLE.ADMIN]: '管理员',
  [ROLE.VIEWER]: '查看者',
};

export const STATUS = {
  DRAFT: 0,
  PENDING: 1,
  APPROVED: 2,
  REJECTED: 3,
  ARCHIVED: 4,
  OBSOLETE: 5,
};

export const STATUS_TEXT = {
  [STATUS.DRAFT]: '草稿',
  [STATUS.PENDING]: '待评审',
  [STATUS.APPROVED]: '评审通过',
  [STATUS.REJECTED]: '驳回',
  [STATUS.ARCHIVED]: '已归档',
  [STATUS.OBSOLETE]: '已归档-已变更-作废',
};

export const STATUS_TAG = {
  [STATUS.DRAFT]: 'info',
  [STATUS.PENDING]: 'warning',
  [STATUS.APPROVED]: 'success',
  [STATUS.REJECTED]: 'danger',
  [STATUS.ARCHIVED]: 'success',
  [STATUS.OBSOLETE]: 'info',
};

export const STATUS_OPTIONS = Object.entries(STATUS_TEXT).map(([value, label]) => ({
  value: Number(value),
  label,
}));

export const PACK_MATERIAL_OPTIONS = [
  { value: 'plastic', label: '塑料' },
  { value: 'metal', label: '金属' },
  { value: 'carton', label: '纸箱' },
  { value: 'other', label: '其他' },
];

export const PACK_MATERIAL_TEXT = PACK_MATERIAL_OPTIONS.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {});

export const ATTACH_TYPE = {
  PART: 'part',
  INNER: 'inner',
  FINAL: 'final',
  PACKAGING: 'packaging',
  OTHER: 'attachment',
};

export const ATTACH_TYPE_TEXT = {
  [ATTACH_TYPE.PART]: '零件图片',
  [ATTACH_TYPE.INNER]: '内部排列',
  [ATTACH_TYPE.FINAL]: '整体包装',
  [ATTACH_TYPE.PACKAGING]: '包装图片',
  [ATTACH_TYPE.OTHER]: '其他附件',
};

export const IMAGE_SLOTS = [
  { type: ATTACH_TYPE.PART, label: '零件图片', hint: '零件外观 / 标识' },
  { type: ATTACH_TYPE.INNER, label: '内部排列', hint: '箱内摆放方式' },
  { type: ATTACH_TYPE.FINAL, label: '整体包装', hint: '满托盘整体外观' },
  { type: ATTACH_TYPE.PACKAGING, label: '包装图片', hint: '包装箱成品' },
];

export const IMAGE_EXT = ['png', 'jpg', 'jpeg'];
export const MAX_FILE_SIZE = 10 * 1024 * 1024;
export const PREVIEW_EXT = ['pdf', 'png', 'jpg', 'jpeg'];

export const ACTION_TEXT = {
  create: '新增方案',
  update: '编辑方案',
  view: '查看方案',
  export: '导出方案',
  submit: '提交评审',
  withdraw: '撤回提交',
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
};

export const ACTION_OPTIONS = Object.entries(ACTION_TEXT).map(([value, label]) => ({ value, label }));
