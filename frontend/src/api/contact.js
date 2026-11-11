import request from './request';

export function getContacts(params) {
  return request.get('/supplier-contacts', { params });
}

export function createContact(data) {
  return request.post('/supplier-contacts', data);
}

export function updateContact(id, data) {
  return request.put(`/supplier-contacts/${id}`, data);
}

export function deleteContact(id) {
  return request.delete(`/supplier-contacts/${id}`);
}
