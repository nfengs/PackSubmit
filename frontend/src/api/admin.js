import request from './request';

function upload(path, file) {
  const form = new FormData();
  form.append('file', file);
  return request.post(path, form, { headers: { 'Content-Type': 'multipart/form-data' } });
}

function download(path) {
  return request.get(path, { responseType: 'blob' });
}

// ---------------- 用户 ----------------
export const getUsers = (params) => request.get('/admin/users', { params });
export const createUser = (data) => request.post('/admin/users', data);
export const updateUser = (id, data) => request.put(`/admin/users/${id}`, data);
export const updateUserStatus = (id, status) => request.patch(`/admin/users/${id}/status`, { status });
export const resetUserPassword = (id, password) =>
  request.post(`/admin/users/${id}/reset-password`, { password });
export const getUserLogs = (id) => request.get(`/admin/users/${id}/logs`);
export const importUsers = (file) => upload('/admin/users/import', file);
export const downloadUserTemplate = () => download('/admin/users/template');

// ---------------- 客户 ----------------
export const getCustomerPage = (params) => request.get('/admin/customers', { params });
export const createCustomer = (data) => request.post('/admin/customers', data);
export const updateCustomer = (id, data) => request.put(`/admin/customers/${id}`, data);
export const deleteCustomer = (id) => request.delete(`/admin/customers/${id}`);
export const checkCustomerName = (params) => request.get('/admin/customers/check-name', { params });
export const getCustomerLogs = (id) => request.get(`/admin/customers/${id}/logs`);

// ---------------- 供应商 ----------------
export const getSupplierPage = (params) => request.get('/admin/suppliers', { params });
export const createSupplier = (data) => request.post('/admin/suppliers', data);
export const updateSupplier = (id, data) => request.put(`/admin/suppliers/${id}`, data);
export const deleteSupplier = (id) => request.delete(`/admin/suppliers/${id}`);
export const checkSupplierName = (params) => request.get('/admin/suppliers/check-name', { params });
export const getSupplierLogs = (id) => request.get(`/admin/suppliers/${id}/logs`);
export const importSuppliers = (file) => upload('/admin/suppliers/import', file);
export const downloadSupplierTemplate = () => download('/admin/suppliers/template');

// ---------------- 零件号 ----------------
export const getPartPage = (params) => request.get('/admin/parts', { params });
export const updatePart = (id, data) => request.put(`/admin/parts/${id}`, data);
export const deletePart = (id) => request.delete(`/admin/parts/${id}`);
export const getPartLogs = (id) => request.get(`/admin/parts/${id}/logs`);
export const importParts = (file) => upload('/admin/parts/import', file);
export const previewPartBatch = (file) => upload('/admin/parts/batch-preview', file);
export const updatePartBatch = (rows) => request.post('/admin/parts/batch-update', { rows });
export const downloadPartTemplate = () => download('/admin/parts/template');

// ---------------- 字典 ----------------
export const getDictPage = (params) => request.get('/admin/dicts', { params });
export const createDict = (data) => request.post('/admin/dicts', data);
export const updateDict = (id, data) => request.put(`/admin/dicts/${id}`, data);
export const deleteDict = (id) => request.delete(`/admin/dicts/${id}`);
