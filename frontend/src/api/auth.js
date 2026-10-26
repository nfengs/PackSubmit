import request from '@/utils/request';

/**
 * 登录
 * @param {{username:string, password:string}} data
 */
export function login(data) {
  return request.post('/auth/login', data);
}

/** 退出登录 */
export function logout() {
  return request.post('/auth/logout');
}

/** 当前登录用户信息 */
export function getProfile() {
  return request.get('/auth/profile');
}

/**
 * 修改自己的登录密码
 * @param {{old_password:string, new_password:string, confirm_password:string}} data
 */
export function changePassword(data) {
  return request.put('/auth/password', data);
}
