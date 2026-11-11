import request from './request';

export function login(data) {
  return request.post('/auth/login', data);
}

export function getProfile() {
  return request.get('/auth/me');
}

export function logout() {
  return request.post('/auth/logout');
}

export function changePassword(data) {
  return request.put('/auth/password', data);
}
