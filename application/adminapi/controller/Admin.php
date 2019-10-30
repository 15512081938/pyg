<?php

namespace app\adminapi\controller;

use app\common\model\Admin as AdminModel;
use think\Request;

class Admin extends BaseApi
{
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        $params = input();
        $where = [];
        if (!empty($params['keyword'])) {
            $where['username'] = ['like', "%{$params['keyword']}%"];
        }
        $size = $params['size'] ?? 10;
        $list = AdminModel::with('role_bind')->where($where)->paginate($size);
        //返回数据
        $this->ok($list);
    }

    /**
     * 保存新建的资源
     *
     * @param  \think\Request $request
     * @return \think\Response
     */
    public function save(Request $request)
    {
        $params = input();
        $validate = $this->validate($params, [
            'username|用户名' => 'require|unique:admin',  //admin表示数据表，前缀可以省略
            'email|邮箱' => 'require|email',
            'password|初始密码' => 'length:6,20',
            'role_id|角色' => 'require|integer|gt:0'
        ]);
        if ($validate !== true) {
            $this->fail($validate);
        }

        //添加数据（密码加密）  模型修改器自动加密：在模型中setPasswordAttr中
        $params['password'] = $params['password'] ?? '123456';
        $res = AdminModel::create($params, true);
        //返回数据
        $info = AdminModel::find($res['id']);
        $this->ok($info);
    }

    /**
     * 显示指定的资源
     *
     * @param  int $id
     * @return \think\Response
     */
    public function read($id)
    {
        $info = AdminModel::find($id);
        $this->ok($info);
    }

    /**
     * 保存更新的资源
     *
     * @param  \think\Request $request
     * @param  int $id
     * @return \think\Response
     */
    public function update(Request $request, $id)
    {
        if ($id == 1) {
            $this->fail('无权修改此管理员');
        }
        $params = input();
        if (isset($params['type']) && $params['type'] == 'reset_pwd') {
            AdminModel::update(['password' => '123456'], ['id' => $id], true);
        } else {
            $validate = $this->validate($params, [
                'email|邮箱' => 'require|email',
                'password|初始密码' => 'length:6,20',
                'role_id|角色' => 'require|integer|gt:0'
            ]);
            if ($validate !== true) {
                $this->fail($validate);
            }
            unset($params['password']);
            $res = AdminModel::update($params, ['id' => $id], true);
        }
        //返回数据
        $info = AdminModel::find($id);
        $this->ok($info);
    }

    /**
     * 删除指定资源
     *
     * @param  int $id
     * @return \think\Response
     */
    public function delete($id)
    {
        $info = AdminModel::find($id);    //$info['role_id'] 表示角色id信息，1为超级管理员
        $user_id = input('user_id');  //$user_id表示登录的用户id，也就是现在登录的用户的$id

        if ($info['role_id'] == 1) {
            $this->fail('不能删除超级管理员');
        }
        if ($user_id == $id) {
            $this->fail('不能删除自己，傻子');
        }

        AdminModel::destroy($id);
        $this->ok();
    }
}
