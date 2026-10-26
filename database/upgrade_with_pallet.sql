-- ----------------------------------------------------------------------------
-- 包装确认单：是否带托盘交付
-- 在已有库上执行：mysql -uroot -p pack_submit < database/upgrade_with_pallet.sql
-- 说明：with_pallet = 1 带托盘交付（需填写托盘信息）；0 不带托盘（托盘信息不填写）
-- ----------------------------------------------------------------------------

ALTER TABLE `pf_pack_proposal`
  ADD COLUMN `with_pallet` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '是否带托盘交付 1是 0否' AFTER `dunnage_returnable`;

-- 历史数据：已填写托盘信息的单据视为带托盘交付
UPDATE `pf_pack_proposal`
SET `with_pallet` = 1
WHERE `pallet_size_l` > 0
   OR `pallet_size_w` > 0
   OR `pallet_size_h` > 0
   OR `pallet_layer_qty` > 0
   OR `pallet_layer_count` > 0
   OR `pallet_parts_qty` > 0;
