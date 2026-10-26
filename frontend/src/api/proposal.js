import request from '@/utils/request';

/** 方案列表 */
export function getProposalList(params) {
  return request.get('/proposals', { params });
}

/** 项目列表（筛选下拉） */
export function getProjects() {
  return request.get('/proposals/projects');
}

/** 方案详情 */
export function getProposalDetail(id) {
  return request.get(`/proposals/${id}`);
}

/** 新增方案 */
export function createProposal(data) {
  return request.post('/proposals', data);
}

/** 编辑方案 */
export function updateProposal(id, data) {
  return request.put(`/proposals/${id}`, data);
}

/** 复制方案（生成当前用户的新草稿） */
export function copyProposal(id) {
  return request.post(`/proposals/${id}/copy`);
}

/** 发起变更（仅已归档方案，生成版本 +1 的变更草稿） */
export function changeProposal(id) {
  return request.post(`/proposals/${id}/change`);
}

/** 删除方案 */
export function deleteProposal(id) {
  return request.delete(`/proposals/${id}`);
}

/** 提交评审 */
export function submitProposal(id) {
  return request.post(`/proposals/${id}/submit`);
}

/** 评审（通过/驳回） */
export function reviewProposal(id, data) {
  return request.post(`/proposals/${id}/review`, data);
}

/** 工作台统计 */
export function getStatistics() {
  return request.get('/proposals/statistics');
}

/**
 * 导出包装确认单
 * 注意：PDF 导出功能已暂停，当前仅开放 excel
 * @param {number} id
 * @param {'excel'|'pdf'} format
 */
export function exportProposal(id, format) {
  return request.get(`/proposals/${id}/export/${format}`, { responseType: 'blob' });
}

/**
 * 撤回提交（待评审 -> 草稿）
 * @param {number} id
 */
export function withdrawProposal(id) {
  return request.post(`/proposals/${id}/withdraw`);
}
