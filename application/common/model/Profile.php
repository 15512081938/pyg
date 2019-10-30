<?php

namespace app\common\model;

use think\Model;

class Profile extends Model
{
    public function admin()
    {
        return $this->belongsTo('Admin', 'uid', 'id');
    }

    public function adminBind()
    {
        return $this->belongsTo('Admin', 'uid', 'id')->bind('idnum,card');
    }
}
