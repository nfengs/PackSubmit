import request from './request';

export function getProposals(params) {
  return request.get('/proposals', { params });
}

export function getStatistics() {
  return request.get('/proposals/statistics');
}

export function getProjects() {
  return request.get('/proposals/projects');
}

export function getProposal(id) {
  return request.get(`/proposals/${id}`);
}

export function createProposal(data) {
  return request.post('/proposals', data);
}

export function updateProposal(id, data) {
  return request.put(`/proposals/${id}`, data);
}

export function deleteProposal(id) {
  return request.delete(`/proposals/${id}`);
}

export function submitProposal(id) {
  return request.post(`/proposals/${id}/submit`);
}

export function withdrawProposal(id) {
  return request.post(`/proposals/${id}/withdraw`);
}

export function reviewProposal(id, data) {
  return request.post(`/proposals/${id}/review`, data);
}

export function changeProposal(id) {
  return request.post(`/proposals/${id}/change`);
}

export function exportProposalExcel(id) {
  return request.get(`/proposals/${id}/export/excel`, { responseType: 'blob' });
}

export function archiveProposals(ids) {
  return request.post('/admin/proposals/archive', { ids });
}

export function getAuditLogs(params) {
  return request.get('/admin/audit-logs', { params });
}
