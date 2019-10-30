<?php

namespace app\common\model;

use think\Model;

class Admin extends Model
{
    //一对一 hasOne
    public function profile()
    {
        return $this->hasOne('Profile', 'uid', 'id');
    }

    public function profileBind()
    {
        return $this->hasOne('Profile', 'uid', 'id')->bind('idnum');
    }

    public function roleBind()
    {
        return $this->belongsTo('Role', 'role_id', 'id')->bind('role_name');
    }

    public function setPasswordAttr($value)
    {
        return encrypt_password($value);
    }
}
