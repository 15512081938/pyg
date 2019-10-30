<?php

namespace app\common\model;

use think\Model;

class Spec extends Model
{
    protected $hidden = ['create_time', 'update_time', 'delete_time'];

    public function specValues()
    {
        return $this->hasMany('SpecValue', 'spec_id', 'id');
    }
}
