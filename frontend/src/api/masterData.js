import request from '@/utils/request';

/** 客户档案列表（含地址、联系人、电话、邮箱、传真） */
export function getCustomers() {
  return request.get('/master-data/customers');
}

/** 供应商档案列表（含代码、地址、联系人、电话、邮箱、传真） */
export function getSuppliers() {
  return request.get('/master-data/suppliers');
}

/** 当前登录用户所属供应商档案（提交人仅能使用该供应商） */
export function getMySupplier() {
  return request.get('/master-data/my-supplier');
}

/**
 * 供应商指定零件号
 * @param {string} [supplier] 供应商名称，提交人可不传（默认自己所属供应商）
 */
export function getSupplierParts(supplier = '') {
  return request.get('/master-data/parts', { params: { supplier } });
}
