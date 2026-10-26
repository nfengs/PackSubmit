-- ----------------------------------------------------------------------------
-- 供应商指定零件号（提交人只能从指定零件号中选择）
-- 在已有库上执行：mysql -uroot -p pack_submit < database/upgrade_supplier_part.sql
--   supplier_name + part_no 唯一，重复导入按「供应商 + 零件号」更新项目与零件名称
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `pf_supplier_part` (
  `id`            int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `supplier_name` varchar(100) NOT NULL DEFAULT '' COMMENT '供应商名称',
  `project_name`  varchar(150) NOT NULL DEFAULT '' COMMENT '项目名称',
  `part_no`       varchar(100) NOT NULL COMMENT '零件号',
  `part_name`     varchar(200) NOT NULL DEFAULT '' COMMENT '零件名称',
  `create_time`   datetime DEFAULT NULL COMMENT '创建时间',
  `update_time`   datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_supplier_part` (`supplier_name`, `part_no`),
  KEY `idx_supplier_name` (`supplier_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='供应商指定零件号';
