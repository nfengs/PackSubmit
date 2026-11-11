-- PackSubmit 初始化脚本（新 schema：无表前缀、ULID 主键、GORM 软删）
-- 仅需对目标库执行；脚本本身不创建数据库

CREATE TABLE IF NOT EXISTS `users` (
  `id`            char(26)     NOT NULL COMMENT 'ULID',
  `username`      varchar(50)  NOT NULL COMMENT '登录账号',
  `password_hash` varchar(255) NOT NULL COMMENT 'bcrypt 哈希',
  `real_name`     varchar(50)  NOT NULL DEFAULT '' COMMENT '姓名',
  `role`          tinyint      NOT NULL DEFAULT 1 COMMENT '1供应商 2评审员 3管理员 4查看者',
  `dept`          varchar(100) NOT NULL DEFAULT '' COMMENT '部门',
  `supplier_id`   char(26)     DEFAULT NULL COMMENT '供应商角色所属供应商',
  `phone`         varchar(30)  NOT NULL DEFAULT '' COMMENT '联系电话',
  `status`        tinyint      NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
  `token_version` int          NOT NULL DEFAULT 1 COMMENT 'token 版本号，改密后递增',
  `created_at`    datetime(3)  DEFAULT NULL,
  `updated_at`    datetime(3)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_supplier` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户';

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id`         char(26)     NOT NULL,
  `name`       varchar(100) NOT NULL COMMENT '供应商名称',
  `code`       varchar(50)  NOT NULL DEFAULT '' COMMENT '供应商代码',
  `address`    varchar(255) NOT NULL DEFAULT '' COMMENT '地址',
  `status`     tinyint      NOT NULL DEFAULT 1,
  `created_at` datetime(3)  DEFAULT NULL,
  `updated_at` datetime(3)  DEFAULT NULL,
  `deleted_at` datetime(3)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_suppliers_name` (`name`),
  KEY `idx_suppliers_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='供应商档案';

CREATE TABLE IF NOT EXISTS `customers` (
  `id`         char(26)     NOT NULL,
  `name`       varchar(100) NOT NULL COMMENT '客户名称',
  `address`    varchar(255) NOT NULL DEFAULT '',
  `contact`    varchar(50)  NOT NULL DEFAULT '',
  `phone`      varchar(30)  NOT NULL DEFAULT '',
  `email`      varchar(100) NOT NULL DEFAULT '',
  `fax`        varchar(30)  NOT NULL DEFAULT '',
  `status`     tinyint      NOT NULL DEFAULT 1,
  `created_at` datetime(3)  DEFAULT NULL,
  `updated_at` datetime(3)  DEFAULT NULL,
  `deleted_at` datetime(3)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_customers_name` (`name`),
  KEY `idx_customers_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='客户档案';

CREATE TABLE IF NOT EXISTS `supplier_contacts` (
  `id`          char(26)     NOT NULL,
  `supplier_id` char(26)     NOT NULL,
  `name`        varchar(50)  NOT NULL DEFAULT '' COMMENT '联系人姓名',
  `phone`       varchar(30)  NOT NULL DEFAULT '',
  `email`       varchar(100) NOT NULL DEFAULT '',
  `fax`         varchar(30)  NOT NULL DEFAULT '',
  `is_default`  tinyint      NOT NULL DEFAULT 0 COMMENT '1默认联系人',
  `created_at`  datetime(3)  DEFAULT NULL,
  `updated_at`  datetime(3)  DEFAULT NULL,
  `deleted_at`  datetime(3)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_contacts_supplier` (`supplier_id`),
  KEY `idx_contacts_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='供应商联系人';

CREATE TABLE IF NOT EXISTS `supplier_parts` (
  `id`            char(26)     NOT NULL,
  `supplier_id`   char(26)     NOT NULL,
  `supplier_name` varchar(100) NOT NULL DEFAULT '' COMMENT '供应商名称快照',
  `project_name`  varchar(150) NOT NULL DEFAULT '' COMMENT '项目名称',
  `part_no`       varchar(100) NOT NULL COMMENT '零件号',
  `part_name`     varchar(200) NOT NULL DEFAULT '' COMMENT '零件名称',
  `created_at`    datetime(3)  DEFAULT NULL,
  `updated_at`    datetime(3)  DEFAULT NULL,
  `deleted_at`    datetime(3)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_supplier_part` (`supplier_id`, `part_no`),
  KEY `idx_parts_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='供应商指定零件号';

CREATE TABLE IF NOT EXISTS `dicts` (
  `id`         char(26)     NOT NULL,
  `dict_type`  varchar(50)  NOT NULL DEFAULT '',
  `dict_label` varchar(100) NOT NULL DEFAULT '',
  `dict_value` varchar(100) NOT NULL DEFAULT '',
  `sort`       int          NOT NULL DEFAULT 0,
  `status`     tinyint      NOT NULL DEFAULT 1,
  `remark`     varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime(3)  DEFAULT NULL,
  `updated_at` datetime(3)  DEFAULT NULL,
  `deleted_at` datetime(3)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dict_type_value` (`dict_type`, `dict_value`),
  KEY `idx_dicts_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='数据字典';

CREATE TABLE IF NOT EXISTS `proposals` (
  `id`                  char(26)     NOT NULL,
  `proposal_no`         varchar(32)  NOT NULL COMMENT 'PS+YYYYMMDD+4位流水',
  `version`             int          NOT NULL DEFAULT 1,
  `source_proposal_id`  char(26)     DEFAULT NULL COMMENT '变更来源方案',
  `status`              tinyint      NOT NULL DEFAULT 0 COMMENT '0草稿 1待评审 2通过 3驳回 4已归档 5已作废',
  `supplier_id`         char(26)     NOT NULL,
  `supplier_name`       varchar(100) NOT NULL DEFAULT '' COMMENT '供应商快照',
  `created_by`          char(26)     NOT NULL,
  `reviewer_id`         char(26)     DEFAULT NULL,
  `review_remark`       varchar(500) NOT NULL DEFAULT '',

  `write_date`          varchar(10)  NOT NULL DEFAULT '' COMMENT '填写日期（固定当天）',
  `project_model`       varchar(100) NOT NULL DEFAULT '' COMMENT '项目车型',
  `part_no`             varchar(100) NOT NULL DEFAULT '',
  `part_name`           varchar(200) NOT NULL DEFAULT '',
  `part_material`       varchar(100) NOT NULL DEFAULT '',
  `spec`                varchar(255) NOT NULL DEFAULT '' COMMENT '规格尺寸（后端派生）',
  `quantity`            int          NOT NULL DEFAULT 0 COMMENT '数量（=存放数量）',
  `customer_id`         char(26)     DEFAULT NULL,
  `customer_name`       varchar(100) NOT NULL DEFAULT '',

  `customer_address`    varchar(255) NOT NULL DEFAULT '',
  `customer_contact`    varchar(50)  NOT NULL DEFAULT '',
  `customer_phone`      varchar(30)  NOT NULL DEFAULT '',
  `customer_email`      varchar(100) NOT NULL DEFAULT '',
  `customer_fax`        varchar(30)  NOT NULL DEFAULT '',

  `supplier_code`       varchar(50)  NOT NULL DEFAULT '',
  `supplier_address`    varchar(255) NOT NULL DEFAULT '',
  `contact`             varchar(50)  NOT NULL DEFAULT '',
  `phone`               varchar(30)  NOT NULL DEFAULT '',
  `supplier_email`      varchar(100) NOT NULL DEFAULT '',
  `supplier_fax`        varchar(30)  NOT NULL DEFAULT '',

  `pack_box_no`         varchar(50)  NOT NULL DEFAULT '',
  `pack_material`       varchar(20)  NOT NULL DEFAULT '' COMMENT 'plastic/metal/carton/other',
  `part_weight`         decimal(10,3) NOT NULL DEFAULT 0,
  `part_size_l`         decimal(10,2) NOT NULL DEFAULT 0,
  `part_size_w`         decimal(10,2) NOT NULL DEFAULT 0,
  `part_size_h`         decimal(10,2) NOT NULL DEFAULT 0,
  `box_size_l`          decimal(10,2) NOT NULL DEFAULT 0,
  `box_size_w`          decimal(10,2) NOT NULL DEFAULT 0,
  `box_size_h`          decimal(10,2) NOT NULL DEFAULT 0,
  `gross_weight`        decimal(10,3) NOT NULL DEFAULT 0,

  `returnable`          tinyint      DEFAULT NULL COMMENT '1是 0否 NULL未选',
  `dunnage_returnable`  tinyint      DEFAULT NULL,
  `with_pallet`         tinyint      DEFAULT NULL,
  `packing_qty`         int          NOT NULL DEFAULT 0 COMMENT '存放数量（件/箱）',

  `packaging_instruction` text       NULL,
  `description`         text         NULL,

  `pallet_size_l`       decimal(10,2) NOT NULL DEFAULT 0,
  `pallet_size_w`       decimal(10,2) NOT NULL DEFAULT 0,
  `pallet_size_h`       decimal(10,2) NOT NULL DEFAULT 0,
  `pallet_layer_qty`    int          NOT NULL DEFAULT 0,
  `pallet_layer_count`  int          NOT NULL DEFAULT 0,
  `pallet_parts_qty`    int          NOT NULL DEFAULT 0 COMMENT '每层×层数×存放数量（后端派生）',

  `submit_time`         datetime(3)  DEFAULT NULL,
  `review_time`         datetime(3)  DEFAULT NULL,
  `archive_time`        datetime(3)  DEFAULT NULL,
  `created_at`          datetime(3)  DEFAULT NULL,
  `updated_at`          datetime(3)  DEFAULT NULL,
  `deleted_at`          datetime(3)  DEFAULT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_proposals_no` (`proposal_no`),
  KEY `idx_proposals_status` (`status`),
  KEY `idx_proposals_supplier` (`supplier_id`),
  KEY `idx_proposals_part` (`part_no`),
  KEY `idx_proposals_source` (`source_proposal_id`),
  KEY `idx_proposals_customer` (`customer_id`),
  KEY `idx_proposals_created_by` (`created_by`),
  KEY `idx_proposals_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='包装方案';

CREATE TABLE IF NOT EXISTS `attachments` (
  `id`          char(26)     NOT NULL,
  `proposal_id` char(26)     DEFAULT NULL COMMENT 'NULL 表示暂存',
  `attach_type` varchar(20)  NOT NULL DEFAULT 'attachment',
  `uploaded_by` char(26)     NOT NULL,
  `file_name`   varchar(255) NOT NULL DEFAULT '',
  `file_path`   varchar(500) NOT NULL DEFAULT '' COMMENT '相对存储根目录',
  `file_ext`    varchar(20)  NOT NULL DEFAULT '',
  `file_size`   bigint       NOT NULL DEFAULT 0,
  `mime_type`   varchar(100) NOT NULL DEFAULT '',
  `md5`         varchar(32)  NOT NULL DEFAULT '',
  `created_at`  datetime(3)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_attachments_proposal` (`proposal_id`),
  KEY `idx_attachments_md5` (`md5`),
  KEY `idx_attachments_path` (`file_path`),
  KEY `idx_attachments_user` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='方案附件';

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`          char(26)     NOT NULL,
  `proposal_id` char(26)     DEFAULT NULL,
  `user_id`     char(26)     NOT NULL,
  `action`      varchar(30)  NOT NULL DEFAULT '',
  `action_name` varchar(50)  NOT NULL DEFAULT '',
  `from_status` tinyint      DEFAULT NULL,
  `to_status`   tinyint      DEFAULT NULL,
  `version`     int          NOT NULL DEFAULT 1,
  `content`     varchar(1000) NOT NULL DEFAULT '',
  `ip`          varchar(64)  NOT NULL DEFAULT '',
  `created_at`  datetime(3)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_proposal` (`proposal_id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='全局审计与方案日志';

CREATE TABLE IF NOT EXISTS `master_logs` (
  `id`          char(26)     NOT NULL,
  `biz_type`    varchar(30)  NOT NULL DEFAULT '' COMMENT 'customer/supplier/user',
  `target_id`   char(26)     NOT NULL,
  `target_name` varchar(150) NOT NULL DEFAULT '',
  `user_id`     char(26)     NOT NULL,
  `action`      varchar(30)  NOT NULL DEFAULT '',
  `action_name` varchar(50)  NOT NULL DEFAULT '',
  `content`     varchar(1000) NOT NULL DEFAULT '',
  `created_at`  datetime(3)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_master_logs_target` (`biz_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='主数据维护日志';

CREATE TABLE IF NOT EXISTS `part_logs` (
  `id`            char(26)     NOT NULL,
  `supplier_id`   char(26)     NOT NULL,
  `supplier_name` varchar(100) NOT NULL DEFAULT '',
  `part_no`       varchar(100) NOT NULL DEFAULT '',
  `user_id`       char(26)     NOT NULL,
  `action`        varchar(30)  NOT NULL DEFAULT '',
  `action_name`   varchar(50)  NOT NULL DEFAULT '',
  `content`       varchar(1000) NOT NULL DEFAULT '',
  `created_at`    datetime(3)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_part_logs` (`supplier_id`, `part_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='零件号维护日志';
