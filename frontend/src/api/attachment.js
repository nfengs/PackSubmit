import request from '@/utils/request';

/**
 * 上传附件
 * @param {FormData} formData
 * @param {(event:ProgressEvent)=>void} onUploadProgress
 */
export function uploadAttachment(formData, onUploadProgress) {
  return request.post('/attachments/upload', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
    onUploadProgress,
  });
}

/**
 * 绑定暂存附件到方案
 * @param {number} proposalId
 * @param {number[]} ids
 */
export function bindAttachments(proposalId, ids) {
  return request.post('/attachments/bind', { proposal_id: proposalId, ids });
}

/** 删除附件 */
export function deleteAttachment(id) {
  return request.delete(`/attachments/${id}`);
}

/** 下载附件（返回原始响应，含 headers） */
export function downloadAttachment(id) {
  return request.get(`/attachments/${id}/download`, { responseType: 'blob' });
}

/** 预览附件（pdf/png/jpg） */
export function previewAttachment(id) {
  return request.get(`/attachments/${id}/preview`, { responseType: 'blob' });
}

/**
 * 从响应头解析文件名
 * @param {object} response
 * @param {string} fallback
 */
export function resolveFileName(response, fallback = 'download') {
  const disposition = response.headers?.['content-disposition'] || '';
  const match =
    /filename\*=UTF-8''([^;]+)/i.exec(disposition) || /filename="?([^";]+)"?/i.exec(disposition);

  if (!match) return fallback;

  try {
    return decodeURIComponent(match[1]);
  } catch (e) {
    return match[1];
  }
}
