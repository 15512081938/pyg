<?php

namespace app\common\model;

use think\Model;

class OrderGoods extends Model
{
    public function goods()
    {
        return $this->belongsTo('Goods', 'goods_id', 'id');
    }

    public function specGoods()
    {
        return $this->belongsTo('specGoods', 'spec_goods_id', 'id');
    }
}
