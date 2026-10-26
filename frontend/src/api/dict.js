import request from '@/utils/request';

/**
 * 字典选项
 * @param {{type?:string, types?:string}} params
 */
export function getDictOptions(params) {
  return request.get('/dict/options', { params });
}

/** 字典类型列表 */
export function getDictTypes() {
  return request.get('/dict/types');
}
