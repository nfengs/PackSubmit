-- 方案变更（版本迭代）支持
-- 1. 记录变更来源方案，变更方案归档后据此把原归档方案置为「已归档-已变更-作废」
-- 2. 状态枚举扩展 5 = 已归档-已变更-作废
ALTER TABLE `pf_pack_proposal`
  ADD COLUMN `source_proposal_id` int unsigned NOT NULL DEFAULT 0 COMMENT '变更来源方案ID，0表示非变更方案' AFTER `version`,
  ADD KEY `idx_source_proposal_id` (`source_proposal_id`),
  MODIFY COLUMN `status` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '状态 0草稿 1待评审 2评审通过 3驳回 4已归档 5已归档-已变更-作废';
