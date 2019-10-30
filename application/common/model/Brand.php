<?php

namespace app\common\model;

use think\Model;

class Brand extends Model
{
    protected $hidden = ['create_time', 'update_time', 'delete_time'];

    public function category()
    {
        return $this->belongsTo('Category', 'cate_id', 'id');
    }

    public function categoryBind()
    {
        return $this->belongsTo('Category', 'cate_id', 'id')->bind(['cate_name', 'hot' => 'is_hot']); //设置别名，用关联数组。别名=>真实名
    }
}
