import request from '@/utils/request';

/**
 * 供应商联系人列表
 * @param {{supplier_id?:number,supplier?:string,keyword?:string}} [params]
 */
export function getSupplierContacts(params) {
  return request.get('/supplier-contacts', { params });
}

/** 新增联系人 */
export function createSupplierContact(data) {
  return request.post('/supplier-contacts', data);
}

/** 编辑联系人 */
export function updateSupplierContact(id, data) {
  return request.put(`/supplier-contacts/${id}`, data);
}

/** 删除联系人 */
export function deleteSupplierContact(id) {
  return request.delete(`/supplier-contacts/${id}`);
}
