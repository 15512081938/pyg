<?php

namespace app\common\model;

use think\Model;

class Cart extends Model
{
    protected $hidden = ['create_time', 'update_time', 'delete_time'];

    public function goods()
    {
        return $this->belongsTo('Goods', 'goods_id', 'id');
    }

    public function specGoods()
    {
        return $this->belongsTo('specGoods', 'spec_goods_id', 'id');
    }
}
