<?php

namespace app\common\model;

use think\Model;

class Category extends Model
{
    protected $hidden = ['create_time', 'update_time', 'delete_time'];
    //一对多 hasMany 多条数据不能绑定bind
    public function brands()
    {
        return $this->hasMany('Brand', 'cate_id', 'id');
    }
}
