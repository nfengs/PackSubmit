-- ----------------------------
-- 包装方案提交评审系统 数据库初始化脚本
-- MySQL 5.7+ / 8.0+，字符集 utf8mb4，表前缀 pf_
-- ----------------------------
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `pack_submit` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `pack_submit`;

-- ----------------------------
-- 用户表
-- ----------------------------
DROP TABLE IF EXISTS `pf_user`;
CREATE TABLE `pf_user` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `username`    varchar(50)  NOT NULL COMMENT '登录账号',
  `password`    varchar(255) NOT NULL COMMENT '密码(password_hash)',
  `real_name`   varchar(50)  NOT NULL DEFAULT '' COMMENT '姓名',
  `role`        tinyint unsigned NOT NULL DEFAULT '1' COMMENT '角色 1提交人 2评审员 3管理员',
  `dept`        varchar(100) NOT NULL DEFAULT '' COMMENT '部门/供应商',
  `phone`       varchar(30)  NOT NULL DEFAULT '' COMMENT '联系电话',
  `status`      tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态 1启用 0禁用',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户表';

-- ----------------------------
-- 包装方案表
-- ----------------------------
DROP TABLE IF EXISTS `pf_pack_proposal`;
CREATE TABLE `pf_pack_proposal` (
  `id`            int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `proposal_no`   varchar(32)  NOT NULL COMMENT '方案编号',
  `customer`      varchar(100) NOT NULL DEFAULT '' COMMENT '客户',
  `spec`          varchar(255) NOT NULL DEFAULT '' COMMENT '规格尺寸',
  `quantity`      int unsigned NOT NULL DEFAULT '0' COMMENT '数量',
  `supplier`      varchar(100) NOT NULL DEFAULT '' COMMENT '供应商',
  `contact`       varchar(50)  NOT NULL DEFAULT '' COMMENT '联系人',
  `phone`         varchar(30)  NOT NULL DEFAULT '' COMMENT '联系电话',
  `description`   text COMMENT '方案说明',
  `version`       int unsigned NOT NULL DEFAULT '1' COMMENT '版本号',
  `status`        tinyint unsigned NOT NULL DEFAULT '0' COMMENT '状态 0草稿 1待评审 2通过 3驳回 4归档 5已归档-已变更-作废',
  `user_id`       int unsigned NOT NULL COMMENT '提交人ID',
  `reviewer_id`   int unsigned DEFAULT NULL COMMENT '最近评审人ID',
  `review_remark` varchar(500) NOT NULL DEFAULT '' COMMENT '最近评审意见',
  `submit_time`   datetime DEFAULT NULL COMMENT '提交时间',
  `review_time`   datetime DEFAULT NULL COMMENT '评审时间',
  `archive_time`  datetime DEFAULT NULL COMMENT '归档时间',
  `create_time`   datetime DEFAULT NULL COMMENT '创建时间',
  `update_time`   datetime DEFAULT NULL COMMENT '更新时间',
  `delete_time`   datetime DEFAULT NULL COMMENT '软删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_proposal_no` (`proposal_no`),
  KEY `idx_customer` (`customer`),
  KEY `idx_status` (`status`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='包装方案表';

-- ----------------------------
-- 附件表
-- ----------------------------
DROP TABLE IF EXISTS `pf_attachment`;
CREATE TABLE `pf_attachment` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `proposal_id` int unsigned NOT NULL DEFAULT '0' COMMENT '方案ID，0表示暂存未关联',
  `user_id`     int unsigned NOT NULL COMMENT '上传人ID',
  `file_name`   varchar(255) NOT NULL COMMENT '原始文件名',
  `file_path`   varchar(500) NOT NULL COMMENT '相对存储路径',
  `file_ext`    varchar(20)  NOT NULL DEFAULT '' COMMENT '扩展名',
  `file_size`   int unsigned NOT NULL DEFAULT '0' COMMENT '文件字节数',
  `mime_type`   varchar(100) NOT NULL DEFAULT '' COMMENT 'MIME类型',
  `md5`         varchar(32)  NOT NULL DEFAULT '' COMMENT '文件内容MD5，用于去重',
  `create_time` datetime DEFAULT NULL COMMENT '上传时间',
  PRIMARY KEY (`id`),
  KEY `idx_proposal_id` (`proposal_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_md5` (`md5`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='方案附件表';

-- ----------------------------
-- 评审/审计日志表
-- ----------------------------
DROP TABLE IF EXISTS `pf_review_log`;
CREATE TABLE `pf_review_log` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `proposal_id` int unsigned NOT NULL DEFAULT '0' COMMENT '方案ID',
  `user_id`     int unsigned NOT NULL COMMENT '操作人ID',
  `action`      varchar(30)  NOT NULL COMMENT '动作标识',
  `action_name` varchar(50)  NOT NULL DEFAULT '' COMMENT '动作名称',
  `from_status` tinyint unsigned DEFAULT NULL COMMENT '变更前状态',
  `to_status`   tinyint unsigned DEFAULT NULL COMMENT '变更后状态',
  `version`     int unsigned NOT NULL DEFAULT '1' COMMENT '操作时的方案版本号',
  `content`     varchar(1000) NOT NULL DEFAULT '' COMMENT '说明/评审意见',
  `ip`          varchar(64)  NOT NULL DEFAULT '' COMMENT '操作IP',
  `create_time` datetime DEFAULT NULL COMMENT '操作时间',
  PRIMARY KEY (`id`),
  KEY `idx_proposal_id` (`proposal_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_create_time` (`create_time`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='评审与审计日志表';

-- ----------------------------
-- 字典表
-- ----------------------------
DROP TABLE IF EXISTS `pf_dict`;
CREATE TABLE `pf_dict` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `dict_type`   varchar(50)  NOT NULL COMMENT '字典类型',
  `dict_label`  varchar(100) NOT NULL COMMENT '显示名称',
  `dict_value`  varchar(100) NOT NULL COMMENT '存储值',
  `sort`        int NOT NULL DEFAULT '0' COMMENT '排序',
  `status`      tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态 1启用 0禁用',
  `remark`      varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_type_value` (`dict_type`,`dict_value`),
  KEY `idx_dict_type` (`dict_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='数据字典表';

-- ----------------------------
-- 客户档案表（选择客户后自动带出资料）
-- ----------------------------
DROP TABLE IF EXISTS `pf_customer`;
CREATE TABLE `pf_customer` (
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
  `delete_time` datetime DEFAULT NULL COMMENT '删除时间（软删除）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='客户档案表';

-- ----------------------------
-- 供应商档案表（选择供应商后自动带出资料）
-- ----------------------------
DROP TABLE IF EXISTS `pf_supplier`;
CREATE TABLE `pf_supplier` (
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
  `delete_time` datetime DEFAULT NULL COMMENT '删除时间（软删除）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='供应商档案表';

-- ----------------------------
-- 供应商指定零件号（提交人只能从这些零件号中选择）
-- ----------------------------
DROP TABLE IF EXISTS `pf_supplier_part`;
CREATE TABLE `pf_supplier_part` (
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

-- ----------------------------
-- 供应商联系人（一个供应商多个联系人，供应商自行维护）
-- ----------------------------
DROP TABLE IF EXISTS `pf_supplier_contact`;
CREATE TABLE `pf_supplier_contact` (
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

-- ----------------------------
-- 初始账号（密码：admin123 / reviewer123 / submitter123，请登录后立即修改）
-- ----------------------------
INSERT INTO `pf_user` (`username`, `password`, `real_name`, `role`, `dept`, `phone`, `status`, `create_time`, `update_time`) VALUES
('admin',     '$2y$10$8pNrz3GIcjUPQe0PXRlzMO.ZnIlmalFyGwwErj.ATzBXsNrxKIszm', '系统管理员', 3, '信息部', '13800000001', 1, NOW(), NOW()),
('reviewer',  '$2y$10$9x9A7ZNiQdD/qKSbyDvEr.SADgLc1BxTFL5ANhQQMSDOjsORqFKIy', '包装评审员', 2, '包装工程部', '13800000002', 1, NOW(), NOW()),
('submitter', '$2y$10$CFTSdQm28Lt/FkbQ1NIedecg7W1GN08P0FebXQDHJnuKKXYPCZQFC', '供应商工程师', 1, '供应商A', '13800000003', 1, NOW(), NOW());

-- ----------------------------
-- 字典初始数据
-- ----------------------------
INSERT INTO `pf_dict` (`dict_type`, `dict_label`, `dict_value`, `sort`, `status`, `remark`, `create_time`, `update_time`) VALUES
('customer',  '客户A',    'cust_a',   10, 1, '', NOW(), NOW()),
('customer',  '客户B',    'cust_b',   20, 1, '', NOW(), NOW()),
('customer',  '客户C',    'cust_c',   30, 1, '', NOW(), NOW());

-- ----------------------------
-- 客户 / 供应商档案演示数据（可直接删除）
-- ----------------------------
INSERT INTO `pf_customer` (`name`,`address`,`contact`,`phone`,`email`,`fax`,`status`,`create_time`,`update_time`) VALUES
('客户A','上海市浦东新区金桥路 100 号','李经理','021-60000001','cust_a@example.com','021-60000009',1,NOW(),NOW()),
('客户B','上海市嘉定区安亭镇园大路 200 号','王经理','021-60000002','cust_b@example.com','021-60000008',1,NOW(),NOW()),
('客户C','江苏省南京市江宁区秣周东路 300 号','赵经理','025-60000003','cust_c@example.com','025-60000007',1,NOW(),NOW()),
('客户D','湖北省武汉市经济技术开发区东风大道 400 号','陈经理','027-60000004','cust_d@example.com','027-60000006',1,NOW(),NOW()),
('客户E','广东省广州市黄埔区开泰大道 500 号','周经理','020-60000005','cust_e@example.com','020-60000005',1,NOW(),NOW());

INSERT INTO `pf_supplier` (`name`,`code`,`address`,`contact`,`phone`,`email`,`fax`,`status`,`create_time`,`update_time`) VALUES
('供应商A','SUP-A001','江苏省苏州市工业园区新平街 8 号','张三','0512-60000001','sup_a@example.com','0512-60000009',1,NOW(),NOW()),
('供应商B','SUP-B002','浙江省宁波市北仑区大港工业城 18 号','李四','0574-60000002','sup_b@example.com','0574-60000008',1,NOW(),NOW()),
('供应商C','SUP-C003','安徽省合肥市经开区繁华大道 28 号','王五','0551-60000003','sup_c@example.com','0551-60000007',1,NOW(),NOW()),
('供应商D','SUP-D004','山东省青岛市城阳区锦宏西路 38 号','孙六','0532-60000004','sup_d@example.com','0532-60000006',1,NOW(),NOW());

INSERT INTO `pf_supplier_part` (`supplier_name`,`project_name`,`part_no`,`part_name`,`create_time`,`update_time`) VALUES
('供应商A','新能源重卡 X1','PN-2026-0001','精密减速机壳体',NOW(),NOW()),
('供应商A','电驱系统 E2','PN-2026-0002','电机定子总成',NOW(),NOW()),
('供应商A','智能座舱 C3','PN-2026-0003','电控箱体',NOW(),NOW()),
('供应商A','商用车 D4','PN-2026-0004','传动轴组件',NOW(),NOW()),
('供应商A','轻卡 K5','PN-2026-0005','后桥壳体总成',NOW(),NOW()),
('供应商B','新能源重卡 X1','PN-B-1001','电机端盖',NOW(),NOW()),
('供应商B','电驱系统 E2','PN-B-1002','减速器壳体',NOW(),NOW());

INSERT INTO `pf_supplier_contact` (`supplier_id`,`name`,`phone`,`email`,`fax`,`is_default`,`create_time`,`update_time`) VALUES
(1,'张三','0512-60000001','sup_a@example.com','0512-60000009',1,NOW(),NOW()),
(1,'李助理','0512-60000011','sup_a_assist@example.com','0512-60000019',0,NOW(),NOW()),
(2,'李四','0574-60000002','sup_b@example.com','0574-60000008',1,NOW(),NOW()),
(3,'王五','0551-60000003','sup_c@example.com','0551-60000007',1,NOW(),NOW()),
(4,'孙六','0532-60000004','sup_d@example.com','0532-60000006',1,NOW(),NOW());

-- ----------------------------
-- 演示数据（可直接删除）
-- ----------------------------
INSERT INTO `pf_pack_proposal`
(`proposal_no`,`customer`,`spec`,`quantity`,`supplier`,`contact`,`phone`,`description`,`version`,`status`,`user_id`,`submit_time`,`create_time`,`update_time`) VALUES
('PS202610250001','客户A','1200*800*900mm',10,'供应商A','张三','13800000003','采用熏蒸木箱，内部 EPE 缓冲，满足海运防潮要求。',1,1,3,NOW(),NOW(),NOW()),
('PS202610250002','客户B','1200*1000*150mm',50,'供应商A','张三','13800000003','托盘堆码，缠绕膜固定，待完善缓冲设计。',1,0,3,NULL,NOW(),NOW());

INSERT INTO `pf_review_log` (`proposal_id`,`user_id`,`action`,`action_name`,`from_status`,`to_status`,`version`,`content`,`ip`,`create_time`) VALUES
(1, 3, 'create', '新增方案', NULL, 0, 1, '创建方案：精密减速机出口包装方案', '127.0.0.1', NOW()),
(1, 3, 'submit', '提交评审', 0, 1, 1, '第 1 版提交评审', '127.0.0.1', NOW()),
(2, 3, 'create', '新增方案', NULL, 0, 1, '创建方案：电机定子托盘包装方案', '127.0.0.1', NOW());

SET FOREIGN_KEY_CHECKS = 1;
