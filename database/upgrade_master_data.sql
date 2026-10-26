-- ----------------------------------------------------------------------------
-- 客户 / 供应商档案（包装确认单选客户、供应商后自动带出资料）
-- 执行：mysql -uroot -p pack_submit < database/upgrade_master_data.sql
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `pf_customer` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name`        varchar(100) NOT NULL COMMENT '客户名称',
  `address`     varchar(255) NOT NULL DEFAULT '' COMMENT '供货地址',
  `contact`     varchar(50)  NOT NULL DEFAULT '' COMMENT '联系人',
  `phone`       varchar(30)  NOT NULL DEFAULT '' COMMENT '联系电话',
  `email`       varchar(100) NOT NULL DEFAULT '' COMMENT '电子邮箱',
  `fax`         varchar(30)  NOT NULL DEFAULT '' COMMENT '传真',
  `status`      tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态：1启用 0禁用',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='客户档案表';

CREATE TABLE IF NOT EXISTS `pf_supplier` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name`        varchar(100) NOT NULL COMMENT '供应商名称',
  `code`        varchar(50)  NOT NULL DEFAULT '' COMMENT '供应商代码',
  `address`     varchar(255) NOT NULL DEFAULT '' COMMENT '供应商地址',
  `contact`     varchar(50)  NOT NULL DEFAULT '' COMMENT '联系人',
  `phone`       varchar(30)  NOT NULL DEFAULT '' COMMENT '联系电话',
  `email`       varchar(100) NOT NULL DEFAULT '' COMMENT '电子邮箱',
  `fax`         varchar(30)  NOT NULL DEFAULT '' COMMENT '传真',
  `status`      tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态：1启用 0禁用',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='供应商档案表';

-- 用现有字典客户、历史方案的供应商初始化档案（已存在则跳过）
INSERT IGNORE INTO `pf_customer` (`name`, `create_time`, `update_time`)
SELECT `dict_label`, NOW(), NOW() FROM `pf_dict` WHERE `dict_type` = 'customer' AND `status` = 1;

INSERT IGNORE INTO `pf_supplier` (`name`, `create_time`, `update_time`)
SELECT DISTINCT `supplier`, NOW(), NOW() FROM `pf_pack_proposal` WHERE `supplier` <> '';

-- 历史单据的客户值由字典 value（如 cust_a）对齐为客户名称，与档案表 name 保持一致
UPDATE `pf_pack_proposal` p
  JOIN `pf_dict` d ON d.`dict_type` = 'customer' AND d.`dict_value` = p.`customer`
SET p.`customer` = d.`dict_label`;
