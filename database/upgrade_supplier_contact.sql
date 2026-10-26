-- ----------------------------------------------------------------------------
-- 供应商联系人（一个供应商可维护多个联系人，每个联系人含电话 / 邮箱 / 传真）
-- 在已有库上执行：mysql -uroot -p pack_submit < database/upgrade_supplier_contact.sql
-- 说明：
--   1. 提交人（供应商）可自行新增 / 编辑 / 删除自己所属供应商的联系人
--   2. 方案表单中选择联系人后，带出电话 / 邮箱 / 传真并保存为快照
--   3. 档案原有的单个联系人资料会迁移为一条默认联系人
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `pf_supplier_contact` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `supplier_id` int unsigned NOT NULL DEFAULT '0' COMMENT '供应商ID',
  `name`        varchar(50)  NOT NULL DEFAULT '' COMMENT '联系人姓名',
  `phone`       varchar(30)  NOT NULL DEFAULT '' COMMENT '联系电话',
  `email`       varchar(100) NOT NULL DEFAULT '' COMMENT '电子邮箱',
  `fax`         varchar(30)  NOT NULL DEFAULT '' COMMENT '传真',
  `is_default`  tinyint unsigned NOT NULL DEFAULT '0' COMMENT '默认联系人 1是 0否',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_supplier_id` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='供应商联系人';

-- 档案中已有的联系人资料迁移为默认联系人（仅当该供应商还没有联系人时）
INSERT INTO `pf_supplier_contact` (`supplier_id`, `name`, `phone`, `email`, `fax`, `is_default`, `create_time`, `update_time`)
SELECT s.`id`, s.`contact`, s.`phone`, s.`email`, s.`fax`, 1, NOW(), NOW()
FROM `pf_supplier` s
WHERE s.`contact` <> ''
  AND NOT EXISTS (
    SELECT 1 FROM `pf_supplier_contact` c WHERE c.`supplier_id` = s.`id`
  );
