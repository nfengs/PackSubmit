-- 指定零件号修改日志（管理端「零件号维护」页面按供应商+零件号查询）
CREATE TABLE IF NOT EXISTS `pf_part_log` (
  `id`            int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `supplier_name` varchar(100)  NOT NULL DEFAULT '' COMMENT '供应商名称',
  `part_no`       varchar(100)  NOT NULL DEFAULT '' COMMENT '零件号',
  `user_id`       int unsigned  NOT NULL DEFAULT '0' COMMENT '操作人ID',
  `action`        varchar(30)   NOT NULL DEFAULT '' COMMENT '动作标识',
  `action_name`   varchar(50)   NOT NULL DEFAULT '' COMMENT '动作名称',
  `content`       varchar(1000) NOT NULL DEFAULT '' COMMENT '修改内容',
  `create_time`   datetime DEFAULT NULL COMMENT '操作时间',
  PRIMARY KEY (`id`),
  KEY `idx_supplier_part` (`supplier_name`, `part_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='指定零件号修改日志';
