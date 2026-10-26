-- ----------------------------------------------------------------------------
-- 移除不再使用的字段：方案名称(title)、物料号(material_no)、包装类型(pack_type)
-- 在已有库上执行：mysql -uroot -p pack_submit < database/upgrade_drop_legacy_fields.sql
-- ----------------------------------------------------------------------------

ALTER TABLE `pf_pack_proposal`
  DROP COLUMN `title`,
  DROP COLUMN `material_no`,
  DROP COLUMN `pack_type`;

-- 包装类型字典项一并清理
DELETE FROM `pf_dict` WHERE `dict_type` = 'pack_type';
