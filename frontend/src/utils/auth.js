const TOKEN_KEY = 'pack_submit_token';

/**
 * 读取 token
 * @returns {string}
 */
export function getToken() {
  return localStorage.getItem(TOKEN_KEY) || '';
}

/**
 * 保存 token
 * @param {string} token
 */
export function setToken(token) {
  localStorage.setItem(TOKEN_KEY, token);
}

/**
 * 移除 token
 */
export function removeToken() {
  localStorage.removeItem(TOKEN_KEY);
}
