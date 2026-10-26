-- ----------------------------------------------------------------------------
-- 客户 / 供应商档案虚拟数据（演示、联调用，可直接删除或按需修改）
-- 说明：
--   1. INSERT ... ON DUPLICATE KEY UPDATE，可重复执行
--   2. 供应商名称需与提交人账号的「部门/供应商」(pf_user.dept) 保持一致，
--      提交人提交方案时只能使用自己所属的供应商
-- 执行：mysql -uroot -p pack_submit < database/seed_master_data.sql
-- ----------------------------------------------------------------------------

INSERT INTO `pf_customer`
(`name`, `address`, `contact`, `phone`, `email`, `fax`, `status`, `create_time`, `update_time`) VALUES
('客户A', '上海市浦东新区金桥路 100 号',       '李经理', '021-60000001', 'cust_a@example.com', '021-60000009', 1, NOW(), NOW()),
('客户B', '上海市嘉定区安亭镇园大路 200 号',   '王经理', '021-60000002', 'cust_b@example.com', '021-60000008', 1, NOW(), NOW()),
('客户C', '江苏省南京市江宁区秣周东路 300 号', '赵经理', '025-60000003', 'cust_c@example.com', '025-60000007', 1, NOW(), NOW()),
('客户D', '湖北省武汉市经济技术开发区东风大道 400 号', '陈经理', '027-60000004', 'cust_d@example.com', '027-60000006', 1, NOW(), NOW()),
('客户E', '广东省广州市黄埔区开泰大道 500 号', '周经理', '020-60000005', 'cust_e@example.com', '020-60000005', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `address` = VALUES(`address`),
  `contact` = VALUES(`contact`),
  `phone`   = VALUES(`phone`),
  `email`   = VALUES(`email`),
  `fax`     = VALUES(`fax`),
  `status`  = VALUES(`status`),
  `update_time` = NOW();

INSERT INTO `pf_supplier`
(`name`, `code`, `address`, `contact`, `phone`, `email`, `fax`, `status`, `create_time`, `update_time`) VALUES
('供应商A', 'SUP-A001', '江苏省苏州市工业园区新平街 8 号',   '张三', '0512-60000001', 'sup_a@example.com', '0512-60000009', 1, NOW(), NOW()),
('供应商B', 'SUP-B002', '浙江省宁波市北仑区大港工业城 18 号', '李四', '0574-60000002', 'sup_b@example.com', '0574-60000008', 1, NOW(), NOW()),
('供应商C', 'SUP-C003', '安徽省合肥市经开区繁华大道 28 号',   '王五', '0551-60000003', 'sup_c@example.com', '0551-60000007', 1, NOW(), NOW()),
('供应商D', 'SUP-D004', '山东省青岛市城阳区锦宏西路 38 号',   '孙六', '0532-60000004', 'sup_d@example.com', '0532-60000006', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `code`    = VALUES(`code`),
  `address` = VALUES(`address`),
  `contact` = VALUES(`contact`),
  `phone`   = VALUES(`phone`),
  `email`   = VALUES(`email`),
  `fax`     = VALUES(`fax`),
  `status`  = VALUES(`status`),
  `update_time` = NOW();

-- ----------------------------
-- 供应商指定零件号（提交人只能从这些零件号中选择）
-- ----------------------------
INSERT INTO `pf_supplier_part`
(`supplier_name`, `project_name`, `part_no`, `part_name`, `create_time`, `update_time`) VALUES
('供应商A', '新能源重卡 X1', 'PN-2026-0001', '精密减速机壳体', NOW(), NOW()),
('供应商A', '电驱系统 E2',   'PN-2026-0002', '电机定子总成', NOW(), NOW()),
('供应商A', '智能座舱 C3',   'PN-2026-0003', '电控箱体',     NOW(), NOW()),
('供应商A', '商用车 D4',     'PN-2026-0004', '传动轴组件',   NOW(), NOW()),
('供应商A', '轻卡 K5',       'PN-2026-0005', '后桥壳体总成', NOW(), NOW()),
('供应商B', '新能源重卡 X1', 'PN-B-1001',    '电机端盖',     NOW(), NOW()),
('供应商B', '电驱系统 E2',   'PN-B-1002',    '减速器壳体',   NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `project_name` = VALUES(`project_name`),
  `part_name`    = VALUES(`part_name`),
  `update_time`  = NOW();
