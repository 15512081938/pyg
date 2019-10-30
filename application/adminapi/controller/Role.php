<?php

namespace app\adminapi\controller;

use app\common\model\Admin;
use app\common\model\Role as RoleModel;
use app\common\model\Auth;
use think\Request;
use think\Collection;

class Role extends BaseApi
{
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        //查询所有数据
        $list = RoleModel::select();
        //查询所有权限
        $auths = Auth::select();
        $auths = (new Collection($auths))->toArray();  //['id' => 1, 'auth_name' => '首页', ...],

        $new_auths = [];       //1 => ['id' => 1, 'auth_name' => '首页', ...],
        foreach ($auths as $v) {
            $new_auths[$v['id']] = $v;
        }
        //遍历数组，对每一个角色 组装拥有的权限
        foreach ($list as $k => $v) {
            if ($v['id'] == 1) {
                //超级管理员
                $list[$k]['role_auths'] = get_tree_list($auths);
                continue;
            }
            //其他管理员
            $role_auth_ids = explode(',', $v['role_auth_ids']);// [1,4,5,2,8]
            $temp = [];//拥有的权限
            //关联角色和权限数组
            foreach ($role_auth_ids as $id) {
                $temp[] = $new_auths[$id];
            }
            $list[$k]['role_auths'] = get_tree_list($temp);
        }
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
            'role_name|角色名称' => 'require',
            'auth_ids|权限' => 'require',
        ]);
        if ($validate !== true) {
            $this->fail($validate);
        }
        //添加数据（如果权限ids是数组，需要转化为字符串。这里不需要转化）
//        if (is_array($params['auth_ids'])) {
//            $params['auth_ids'] = implode(',', $params['auth_ids']);
//        }
        $res = RoleModel::create($params, true);
        $info = RoleModel::find($res['id']);
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
        $info = RoleModel::find($id);
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
        $params = input();
        $validate = $this->validate($params, [
            'role_name|角色名称' => 'require',
            'auth_ids|权限' => 'require',
        ]);
        if ($validate !== true) {
            $this->fail($validate);
        }
        if ($id == 1) {
            $this->fail('无权修改超级管理员');
        }
        RoleModel::update($params, ['id' => $id], true);
        $info = RoleModel::find($id);
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
        if ($id == 1) {
            $this->fail('无权删除超级管理员');
        }
        //角色下有管理员，不能删除
        $total = Admin::where('role_id', $id)->count('id');
        if ($total) {
            $this->fail('角色下有管理员，不能删除');
        }
        RoleModel::destroy($id);
        $this->ok();
    }
}
