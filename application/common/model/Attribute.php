<?php

namespace app\common\model;

use think\Model;

class Attribute extends Model
{
    protected $hidden = ['create_time', 'update_time', 'delete_time'];

    public function getAttrValuesAttr($value)  //获取器  ： get在前，Attr在后，中间是字段名
    {
        return $value ? explode(',', $value) : [];
    }
}
