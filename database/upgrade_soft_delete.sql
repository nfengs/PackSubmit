-- ----------------------------------------------------------------------------
-- 客户 / 供应商档案启用 ThinkPHP 软删除
-- 在已有库上执行：mysql -uroot -p pack_submit < database/upgrade_soft_delete.sql
-- 说明：
--   1. 删除时写入 delete_time，列表与查重自动排除已删除数据
--   2. 删除时名称会改写为「原名称#ID」，释放唯一索引，便于重新创建同名档案
-- ----------------------------------------------------------------------------

ALTER TABLE `pf_customer`
  ADD COLUMN `delete_time` datetime DEFAULT NULL COMMENT '删除时间（软删除）' AFTER `update_time`;

ALTER TABLE `pf_supplier`
  ADD COLUMN `delete_time` datetime DEFAULT NULL COMMENT '删除时间（软删除）' AFTER `update_time`;
