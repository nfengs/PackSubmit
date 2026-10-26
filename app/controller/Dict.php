<?php
declare (strict_types = 1);

namespace app\controller;

use app\BaseController;
use app\model\Dict as DictModel;
use think\response\Json;

/**
 * 字典读取控制器（登录用户可用）
 */
class Dict extends BaseController
{
    /**
     * 获取字典选项
     * 支持 ?type=customer 或 ?types=customer,pack_type
     * @return Json
     */
    public function options(): Json
    {
        $type  = trim((string) $this->request->param('type', ''));
        $types = trim((string) $this->request->param('types', ''));

        $query = DictModel::where('status', DictModel::STATUS_ENABLED);

        if ($type !== '') {
            $query->where('dict_type', $type);
        } elseif ($types !== '') {
            $query->whereIn('dict_type', array_filter(explode(',', $types)));
        }

        $list    = $query->order('dict_type')->order('sort')->select();
        $grouped = [];

        foreach ($list as $item) {
            $grouped[$item->dict_type][] = [
                'label' => $item->dict_label,
                'value' => $item->dict_value,
            ];
        }

        return $this->success($grouped);
    }

    /**
     * 字典类型列表
     * @return Json
     */
    public function types(): Json
    {
        $types = DictModel::where('status', DictModel::STATUS_ENABLED)
            ->distinct(true)
            ->column('dict_type');

        return $this->success($types);
    }
}
