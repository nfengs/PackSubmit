import request from './request';

export function getCustomers(params) {
  return request.get('/master/customers', { params });
}

export function getSuppliers(params) {
  return request.get('/master/suppliers', { params });
}

export function getMySupplier() {
  return request.get('/master/my-supplier');
}

export function getParts(params) {
  return request.get('/master/parts', { params });
}
