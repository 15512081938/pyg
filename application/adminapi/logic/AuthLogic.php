<?php
/**
 * Created by PhpStorm.
 * User: lenovo
 * Date: 2019/9/20
 * Time: 20:46
 */

namespace app\adminapi\logic;

use app\common\model\Admin;
use app\common\model\Auth;
use app\common\model\Role;

class AuthLogic
{
    public static function check()
    {
        $pages = ['index/index', 'login/logout'];
        $controller = request()->controller();
        $action = request()->action();
        $path = strtolower($controller . '/' . $action); //单个数据
        if (in_array($path, $pages)) {
            return true;
        }

        $id = input('user_id');
        $admin = Admin::find($id);
        if ($admin['role_id'] == 1) {
            return true;
        }

        $role = Role::find($admin['role_id']);
        $role_auth_ids = explode(',', $role['role_auth_ids']);
        $auth = Auth::where(['auth_c' => $controller, 'auth_a' => $action])->find();
        if (in_array($auth['id'], $role_auth_ids)) {
            return true;
        }

        return false;
    }
}