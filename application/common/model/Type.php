<?php

namespace app\common\model;

use think\Model;

class Type extends Model
{
    protected $hidden = ['create_time', 'update_time', 'delete_time'];

    public function specs()
    {
        return $this->hasMany('Spec', 'type_id', 'id');
    }

    public function attrs()
    {
        return $this->hasMany('Attribute', 'type_id', 'id');
    }
}
