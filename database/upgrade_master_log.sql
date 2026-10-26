-- 主数据（客户 / 供应商）维护日志，供维护页面按行查询
CREATE TABLE IF NOT EXISTS `pf_master_log` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `biz_type`    varchar(30)   NOT NULL DEFAULT '' COMMENT '业务类型 customer/supplier',
  `target_id`   int unsigned  NOT NULL DEFAULT '0' COMMENT '客户/供应商ID',
  `target_name` varchar(150)  NOT NULL DEFAULT '' COMMENT '名称快照',
  `user_id`     int unsigned  NOT NULL DEFAULT '0' COMMENT '操作人ID',
  `action`      varchar(30)   NOT NULL DEFAULT '' COMMENT '动作标识',
  `action_name` varchar(50)   NOT NULL DEFAULT '' COMMENT '动作名称',
  `content`     varchar(1000) NOT NULL DEFAULT '' COMMENT '变更内容',
  `create_time` datetime DEFAULT NULL COMMENT '操作时间',
  PRIMARY KEY (`id`),
  KEY `idx_target` (`biz_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='主数据维护日志';
