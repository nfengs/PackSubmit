-- ----------------------------------------------------------------------------
-- 是/否单选项改为可空：新建/编辑表单默认不选中，保存草稿时保持 NULL
-- 执行：mysql -uroot -p pack_submit < database/upgrade_nullable_yesno.sql
-- ----------------------------------------------------------------------------

ALTER TABLE `pf_pack_proposal`
  MODIFY COLUMN `returnable` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT '包装箱是否返回 1是 0否',
  MODIFY COLUMN `dunnage_returnable` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT '内衬是否返回 1是 0否',
  MODIFY COLUMN `with_pallet` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT '是否带托盘交付 1是 0否';
