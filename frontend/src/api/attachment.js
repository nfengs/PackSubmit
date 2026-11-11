import { ElMessage } from 'element-plus';
import request from './request';

// blob 请求遇到业务错误时后端返回 JSON，需要转成可读提示
async function ensureBlob(response) {
  const data = response.data;
  if (data && data.type && data.type.includes('application/json')) {
    const text = await data.text();
    let message = '操作失败';

    try {
      message = JSON.parse(text).message || message;
    } catch (e) {
      // 非 JSON 内容按默认提示处理
    }

    ElMessage.error(message);
    throw new Error(message);
  }

  return data;
}

export function uploadAttachment({ file, proposalId, attachType }) {
  const form = new FormData();
  form.append('file', file);
  if (proposalId) form.append('proposal_id', proposalId);
  if (attachType) form.append('attach_type', attachType);
  return request.post('/attachments/upload', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
}

export function bindAttachment(attachmentId, proposalId) {
  return request.post('/attachments/bind', { attachment_id: attachmentId, proposal_id: proposalId });
}

export function deleteAttachment(id) {
  return request.delete(`/attachments/${id}`);
}

// 附件受鉴权保护，需带 token 读取为 blob 再预览
export async function fetchAttachmentBlob(id, mode = 'preview') {
  const response = await request.get(`/attachments/${id}/${mode}`, { responseType: 'blob' });
  return URL.createObjectURL(response.data);
}

export async function downloadAttachment(id, fileName) {
  const response = await request.get(`/attachments/${id}/download`, { responseType: 'blob' });
  const url = URL.createObjectURL(await ensureBlob(response));
  const link = document.createElement('a');
  link.href = url;
  link.download = fileName || 'attachment';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

export async function saveBlob(response, fileName) {
  const url = URL.createObjectURL(await ensureBlob(response));
  const link = document.createElement('a');
  link.href = url;
  link.download = fileName || 'download';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}
