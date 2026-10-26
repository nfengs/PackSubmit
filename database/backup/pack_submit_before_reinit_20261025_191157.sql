-- MySQL dump 10.13  Distrib 8.4.5, for macos14.5 (arm64)
--
-- Host: localhost    Database: pack_submit
-- ------------------------------------------------------
-- Server version	8.4.5

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `pf_attachment`
--

DROP TABLE IF EXISTS `pf_attachment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pf_attachment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `proposal_id` int unsigned NOT NULL DEFAULT '0' COMMENT '方案ID，0表示暂存未关联',
  `attach_type` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'attachment' COMMENT '附件类型 part/inner/final/packaging/attachment',
  `user_id` int unsigned NOT NULL COMMENT '上传人ID',
  `file_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL COMMENT '原始文件名',
  `file_path` varchar(500) COLLATE utf8mb4_general_ci NOT NULL COMMENT '相对存储路径',
  `file_ext` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '扩展名',
  `file_size` int unsigned NOT NULL DEFAULT '0' COMMENT '文件字节数',
  `mime_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'MIME类型',
  `create_time` datetime DEFAULT NULL COMMENT '上传时间',
  PRIMARY KEY (`id`),
  KEY `idx_proposal_id` (`proposal_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='方案附件表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pf_attachment`
--

/*!40000 ALTER TABLE `pf_attachment` DISABLE KEYS */;
/*!40000 ALTER TABLE `pf_attachment` ENABLE KEYS */;

--
-- Table structure for table `pf_dict`
--

DROP TABLE IF EXISTS `pf_dict`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pf_dict` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `dict_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT '字典类型',
  `dict_label` varchar(100) COLLATE utf8mb4_general_ci NOT NULL COMMENT '显示名称',
  `dict_value` varchar(100) COLLATE utf8mb4_general_ci NOT NULL COMMENT '存储值',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态 1启用 0禁用',
  `remark` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_type_value` (`dict_type`,`dict_value`),
  KEY `idx_dict_type` (`dict_type`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='数据字典表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pf_dict`
--

/*!40000 ALTER TABLE `pf_dict` DISABLE KEYS */;
INSERT INTO `pf_dict` VALUES (1,'pack_type','纸箱包装','carton',10,1,'瓦楞纸箱','2026-10-25 18:33:29','2026-10-25 18:33:29'),(2,'pack_type','木箱包装','wooden',20,1,'熏蒸木箱','2026-10-25 18:33:29','2026-10-25 18:33:29'),(3,'pack_type','托盘包装','pallet',30,1,'木托盘/塑料托盘','2026-10-25 18:33:29','2026-10-25 18:33:29'),(4,'pack_type','缓冲包装','cushion',40,1,'EPE/EPS 缓冲','2026-10-25 18:33:29','2026-10-25 18:33:29'),(5,'pack_type','真空包装','vacuum',50,1,'防潮真空','2026-10-25 18:33:29','2026-10-25 18:33:29'),(6,'pack_type','其他','other',99,1,'','2026-10-25 18:33:29','2026-10-25 18:33:29'),(7,'customer','客户A','cust_a',10,1,'','2026-10-25 18:33:29','2026-10-25 18:33:29'),(8,'customer','客户B','cust_b',20,1,'','2026-10-25 18:33:29','2026-10-25 18:33:29'),(9,'customer','客户C','cust_c',30,1,'','2026-10-25 18:33:29','2026-10-25 18:33:29');
/*!40000 ALTER TABLE `pf_dict` ENABLE KEYS */;

--
-- Table structure for table `pf_pack_proposal`
--

DROP TABLE IF EXISTS `pf_pack_proposal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pf_pack_proposal` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `proposal_no` varchar(32) COLLATE utf8mb4_general_ci NOT NULL COMMENT '方案编号',
  `title` varchar(200) COLLATE utf8mb4_general_ci NOT NULL COMMENT '方案名称',
  `material_no` varchar(100) COLLATE utf8mb4_general_ci NOT NULL COMMENT '物料号',
  `customer` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '客户',
  `pack_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '包装类型(字典 dict_value)',
  `spec` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '规格尺寸',
  `quantity` int unsigned NOT NULL DEFAULT '0' COMMENT '数量',
  `supplier` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '供应商',
  `contact` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '联系人',
  `phone` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '联系电话',
  `description` text COLLATE utf8mb4_general_ci COMMENT '方案说明',
  `write_date` date DEFAULT NULL COMMENT '填写日期',
  `project_model` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '项目车型',
  `part_name` varchar(200) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '零件名称',
  `part_no` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '零件号',
  `part_material` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '零件材料',
  `customer_address` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '客户地址',
  `customer_contact` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '客户联系人',
  `customer_phone` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '客户电话',
  `customer_email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '客户邮箱',
  `customer_fax` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '客户传真',
  `supplier_code` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '供应商代码',
  `supplier_address` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '供应商地址',
  `supplier_email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '供应商邮箱',
  `supplier_fax` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '供应商传真',
  `pack_box_no` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '包装箱编号',
  `pack_material` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '包装箱材料 plastic/metal/carton/other',
  `part_weight` decimal(10,3) NOT NULL DEFAULT '0.000' COMMENT '产品净重kg',
  `part_size_l` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '产品外形长mm',
  `part_size_w` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '产品外形宽mm',
  `part_size_h` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '产品外形高mm',
  `box_size_l` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '包装箱外长mm',
  `box_size_w` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '包装箱外宽mm',
  `box_size_h` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '包装箱外高mm',
  `gross_weight` decimal(10,3) NOT NULL DEFAULT '0.000' COMMENT '毛重kg',
  `returnable` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '包装箱是否返回 1是 0否',
  `dunnage_returnable` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '内衬是否返回 1是 0否',
  `packing_qty` int unsigned NOT NULL DEFAULT '0' COMMENT '存放数量(件/箱)',
  `pallet_size_l` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '满托盘长mm',
  `pallet_size_w` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '满托盘宽mm',
  `pallet_size_h` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '满托盘高mm',
  `pallet_layer_qty` int unsigned NOT NULL DEFAULT '0' COMMENT '每层包装数量',
  `pallet_layer_count` int unsigned NOT NULL DEFAULT '0' COMMENT '堆码层数',
  `pallet_parts_qty` int unsigned NOT NULL DEFAULT '0' COMMENT '每托盘零件数量',
  `packaging_instruction` text COLLATE utf8mb4_general_ci COMMENT '包装操作说明',
  `version` int unsigned NOT NULL DEFAULT '1' COMMENT '版本号',
  `status` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '状态 0草稿 1待评审 2通过 3驳回 4归档',
  `user_id` int unsigned NOT NULL COMMENT '提交人ID',
  `reviewer_id` int unsigned DEFAULT NULL COMMENT '最近评审人ID',
  `review_remark` varchar(500) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '最近评审意见',
  `submit_time` datetime DEFAULT NULL COMMENT '提交时间',
  `review_time` datetime DEFAULT NULL COMMENT '评审时间',
  `archive_time` datetime DEFAULT NULL COMMENT '归档时间',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  `delete_time` datetime DEFAULT NULL COMMENT '软删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_proposal_no` (`proposal_no`),
  KEY `idx_material_no` (`material_no`),
  KEY `idx_customer` (`customer`),
  KEY `idx_status` (`status`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='包装方案表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pf_pack_proposal`
--

/*!40000 ALTER TABLE `pf_pack_proposal` DISABLE KEYS */;
INSERT INTO `pf_pack_proposal` VALUES (1,'PS202610250001','精密减速机出口包装方案','MAT-2026-0001','cust_a','wooden','1200*800*900mm',10,'供应商A','张三','13800000003','采用熏蒸木箱，内部 EPE 缓冲，满足海运防潮要求。',NULL,'','','','','','','','','','','','','','','',0.000,0.00,0.00,0.00,0.00,0.00,0.00,0.000,1,1,0,0.00,0.00,0.00,0,0,0,NULL,1,1,3,NULL,'','2026-10-25 18:33:29',NULL,NULL,'2026-10-25 18:33:29','2026-10-25 18:33:29',NULL),(2,'PS202610250002','电机定子托盘包装方案','MAT-2026-0002','cust_b','pallet','1200*1000*150mm',50,'供应商A','张三','13800000003','托盘堆码，缠绕膜固定，待完善缓冲设计。',NULL,'','','','','','','','','','','','','','','',0.000,0.00,0.00,0.00,0.00,0.00,0.00,0.000,1,1,0,0.00,0.00,0.00,0,0,0,NULL,1,1,3,NULL,'','2026-10-25 18:44:16',NULL,NULL,'2026-10-25 18:33:29','2026-10-25 18:44:17',NULL);
/*!40000 ALTER TABLE `pf_pack_proposal` ENABLE KEYS */;

--
-- Table structure for table `pf_review_log`
--

DROP TABLE IF EXISTS `pf_review_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pf_review_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `proposal_id` int unsigned NOT NULL DEFAULT '0' COMMENT '方案ID',
  `user_id` int unsigned NOT NULL COMMENT '操作人ID',
  `action` varchar(30) COLLATE utf8mb4_general_ci NOT NULL COMMENT '动作标识',
  `action_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '动作名称',
  `from_status` tinyint unsigned DEFAULT NULL COMMENT '变更前状态',
  `to_status` tinyint unsigned DEFAULT NULL COMMENT '变更后状态',
  `version` int unsigned NOT NULL DEFAULT '1' COMMENT '操作时的方案版本号',
  `content` varchar(1000) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '说明/评审意见',
  `ip` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '操作IP',
  `create_time` datetime DEFAULT NULL COMMENT '操作时间',
  PRIMARY KEY (`id`),
  KEY `idx_proposal_id` (`proposal_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_create_time` (`create_time`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='评审与审计日志表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pf_review_log`
--

/*!40000 ALTER TABLE `pf_review_log` DISABLE KEYS */;
INSERT INTO `pf_review_log` VALUES (1,1,3,'create','新增方案',NULL,0,1,'创建方案：精密减速机出口包装方案','127.0.0.1','2026-10-25 18:33:29'),(2,1,3,'submit','提交评审',0,1,1,'第 1 版提交评审','127.0.0.1','2026-10-25 18:33:29'),(3,2,3,'create','新增方案',NULL,0,1,'创建方案：电机定子托盘包装方案','127.0.0.1','2026-10-25 18:33:29'),(4,0,1,'login','登录系统',NULL,NULL,1,'用户登录','127.0.0.1','2026-10-25 18:42:11'),(5,0,1,'login','登录系统',NULL,NULL,1,'用户登录','127.0.0.1','2026-10-25 18:42:43'),(6,0,1,'login','登录系统',NULL,NULL,1,'用户登录','127.0.0.1','2026-10-25 18:42:52'),(7,0,2,'login','登录系统',NULL,NULL,1,'用户登录','127.0.0.1','2026-10-25 18:43:17'),(8,0,2,'logout','退出登录',NULL,NULL,1,'退出登录','127.0.0.1','2026-10-25 18:43:37'),(9,0,3,'login','登录系统',NULL,NULL,1,'用户登录','127.0.0.1','2026-10-25 18:43:46'),(10,2,3,'submit','提交评审',0,1,1,'第 1 版提交评审','127.0.0.1','2026-10-25 18:44:17'),(11,0,3,'logout','退出登录',NULL,NULL,1,'退出登录','127.0.0.1','2026-10-25 18:52:36'),(12,0,1,'login','登录系统',NULL,NULL,1,'用户登录','127.0.0.1','2026-10-25 18:52:44'),(13,0,1,'login','登录系统',NULL,NULL,1,'用户登录','127.0.0.1','2026-10-25 19:02:53'),(14,0,1,'login','登录系统',NULL,NULL,1,'用户登录','127.0.0.1','2026-10-25 19:02:57'),(15,0,1,'login','登录系统',NULL,NULL,1,'用户登录','127.0.0.1','2026-10-25 19:03:02'),(16,3,1,'update','编辑方案',0,0,1,'编辑方案：导出测试方案','127.0.0.1','2026-10-25 19:03:03'),(17,0,1,'login','登录系统',NULL,NULL,1,'用户登录','127.0.0.1','2026-10-25 19:03:13'),(18,0,1,'login','登录系统',NULL,NULL,1,'用户登录','127.0.0.1','2026-10-25 19:03:32'),(19,0,1,'login','登录系统',NULL,NULL,1,'用户登录','127.0.0.1','2026-10-25 19:07:41'),(20,0,1,'login','登录系统',NULL,NULL,1,'用户登录','127.0.0.1','2026-10-25 19:07:53'),(21,0,1,'login','登录系统',NULL,NULL,1,'用户登录','127.0.0.1','2026-10-25 19:08:01'),(22,0,1,'login','登录系统',NULL,NULL,1,'用户登录','127.0.0.1','2026-10-25 19:10:02');
/*!40000 ALTER TABLE `pf_review_log` ENABLE KEYS */;

--
-- Table structure for table `pf_user`
--

DROP TABLE IF EXISTS `pf_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pf_user` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT '登录账号',
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL COMMENT '密码(password_hash)',
  `real_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '姓名',
  `role` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '角色 1提交人 2评审员 3管理员',
  `dept` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '部门/供应商',
  `phone` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '联系电话',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态 1启用 0禁用',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pf_user`
--

/*!40000 ALTER TABLE `pf_user` DISABLE KEYS */;
INSERT INTO `pf_user` VALUES (1,'admin','$2y$10$8pNrz3GIcjUPQe0PXRlzMO.ZnIlmalFyGwwErj.ATzBXsNrxKIszm','系统管理员',3,'信息部','13800000001',1,'2026-10-25 18:33:29','2026-10-25 18:33:29'),(2,'reviewer','$2y$10$9x9A7ZNiQdD/qKSbyDvEr.SADgLc1BxTFL5ANhQQMSDOjsORqFKIy','包装评审员',2,'包装工程部','13800000002',1,'2026-10-25 18:33:29','2026-10-25 18:33:29'),(3,'submitter','$2y$10$CFTSdQm28Lt/FkbQ1NIedecg7W1GN08P0FebXQDHJnuKKXYPCZQFC','供应商工程师',1,'供应商A','13800000003',1,'2026-10-25 18:33:29','2026-10-25 18:33:29');
/*!40000 ALTER TABLE `pf_user` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-25 19:11:58
