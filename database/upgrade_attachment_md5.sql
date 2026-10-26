-- ----------------------------------------------------------------------------
-- 附件去重：为 pf_attachment 增加文件内容 MD5 字段与索引
-- 在已有库上执行：mysql -uroot -p pack_submit < database/upgrade_attachment_md5.sql
-- 说明：
--   1. 上传时计算文件内容 MD5，相同内容的文件只落盘一份物理文件，新记录复用已有路径
--   2. 删除附件时，若该文件仍被其他附件记录引用（同 file_path 或同 md5）则不删除物理文件
--   3. 历史数据 md5 为空，不影响既有附件；后续上传自动补齐
-- ----------------------------------------------------------------------------

ALTER TABLE `pf_attachment`
  ADD COLUMN `md5` varchar(32) NOT NULL DEFAULT '' COMMENT '文件内容MD5，用于去重' AFTER `mime_type`,
  ADD KEY `idx_md5` (`md5`);
