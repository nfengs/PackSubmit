import request from '@/utils/request';

/* -------------------- 用户管理 -------------------- */

/** 用户列表 */
export function getUserList(params) {
  return request.get('/admin/users', { params });
}

/** 新增用户 */
export function createUser(data) {
  return request.post('/admin/users', data);
}

/** 编辑用户 */
export function updateUser(id, data) {
  return request.put(`/admin/users/${id}`, data);
}

/** 启用/禁用用户 */
export function updateUserStatus(id, status) {
  return request.patch(`/admin/users/${id}/status`, { status });
}

/** 重置密码 */
export function resetUserPassword(id, password) {
  return request.post(`/admin/users/${id}/reset-password`, { password });
}

/** 用户维护日志 */
export function getUserLogs(id) {
  return request.get(`/admin/users/${id}/logs`);
}

/* -------------------- 字典管理 -------------------- */

/** 字典列表 */
export function getDictList(params) {
  return request.get('/admin/dicts', { params });
}

/** 新增字典 */
export function createDict(data) {
  return request.post('/admin/dicts', data);
}

/** 编辑字典 */
export function updateDict(id, data) {
  return request.put(`/admin/dicts/${id}`, data);
}

/** 删除字典 */
export function deleteDict(id) {
  return request.delete(`/admin/dicts/${id}`);
}

/* -------------------- 客户档案 -------------------- */

/** 客户档案列表 */
export function getCustomerList(params) {
  return request.get('/admin/customers', { params });
}

/** 客户名称查重 */
export function checkCustomerName(name, id = 0) {
  return request.get('/admin/customers/check-name', { params: { name, id } });
}

/** 新增客户 */
export function createCustomer(data) {
  return request.post('/admin/customers', data);
}

/** 编辑客户 */
export function updateCustomer(id, data) {
  return request.put(`/admin/customers/${id}`, data);
}

/** 客户维护日志 */
export function getCustomerLogs(id) {
  return request.get(`/admin/customers/${id}/logs`);
}

/** 删除客户 */
export function deleteCustomer(id) {
  return request.delete(`/admin/customers/${id}`);
}

/* -------------------- 供应商档案 -------------------- */

/** 供应商档案列表 */
export function getSupplierList(params) {
  return request.get('/admin/suppliers', { params });
}

/** 供应商名称查重 */
export function checkSupplierName(name, id = 0) {
  return request.get('/admin/suppliers/check-name', { params: { name, id } });
}

/** 新增供应商 */
export function createSupplier(data) {
  return request.post('/admin/suppliers', data);
}

/** 编辑供应商 */
export function updateSupplier(id, data) {
  return request.put(`/admin/suppliers/${id}`, data);
}

/** 供应商维护日志 */
export function getSupplierLogs(id) {
  return request.get(`/admin/suppliers/${id}/logs`);
}

/** 删除供应商 */
export function deleteSupplier(id) {
  return request.delete(`/admin/suppliers/${id}`);
}

/* -------------------- 指定零件号 -------------------- */

/** 指定零件号列表 */
export function getPartList(params) {
  return request.get('/admin/parts', { params });
}

/** 导入指定零件号（Excel） */
export function importParts(formData) {
  return request.post('/admin/parts/import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
}

/** 下载导入模板 */
export function downloadPartTemplate() {
  return request.get('/admin/parts/template', { responseType: 'blob' });
}

/** 批量修改 - 上传 Excel 校验预览（不写库） */
export function previewBatchUpdateParts(formData) {
  return request.post('/admin/parts/batch-preview', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
}

/** 批量修改 - 提交修改 */
export function batchUpdateParts(rows) {
  return request.post('/admin/parts/batch-update', { rows });
}

/** 修改指定零件号（项目名称 / 零件名称） */
export function updatePart(id, data) {
  return request.put(`/admin/parts/${id}`, data);
}

/** 指定零件号修改日志 */
export function getPartLogs(id) {
  return request.get(`/admin/parts/${id}/logs`);
}

/** 删除指定零件号 */
export function deletePart(id) {
  return request.delete(`/admin/parts/${id}`);
}

/* -------------------- 全量方案 -------------------- */

/** 全量方案列表 */
export function getAllProposals(params) {
  return request.get('/admin/proposals', { params });
}

/** 全量详细方案（全字段，供明细筛选与导出） */
export function getProposalDetailList(params) {
  return request.get('/admin/proposals/detail', { params });
}

/** 批量归档 */
export function archiveProposals(ids) {
  return request.post('/admin/proposals/archive', { ids });
}

/* -------------------- 审计日志 -------------------- */

/** 全局审计日志 */
export function getLogList(params) {
  return request.get('/admin/logs', { params });
}
