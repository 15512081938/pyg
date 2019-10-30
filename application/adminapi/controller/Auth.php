<?php

namespace app\adminapi\controller;

use app\common\model\Admin;
use think\Collection;
use think\Request;
use app\common\model\Auth as AuthModel;
use app\common\model\Role;

class Auth extends BaseApi
{
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        $type = input('type', '');
        $list = AuthModel::select();
        $list = (new Collection($list))->toArray();
//        dump($type);die;
        if ($type == 'tree') {
            $list = get_tree_list($list);
        } else {
            $list = get_cate_list($list);
        }
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
            'auth_name|权限名称' => 'require',
            'pid|上级权限' => 'require|integer|egt:0',
            'auth_c|控制器名称' => 'length:1,30',
            'auth_a|方法名称' => 'length:1,30',
            'is_nav|是否菜单项' => 'require|in:0,1'
        ]);
        if ($validate !== true) {
            $this->fail($validate);
        }
        if ($params['pid'] == 0) {
            $params['level'] = 0;
            $params['pid_path'] = 0;
        } else {
            //二三四级权限,先查询父级
            $p_info = AuthModel::find($params['pid']);
//            if (!$p_info) {
//                $this->fail('数据异常');
//            }
            $params['level'] = $p_info['level'] + 1;
            $params['pid_path'] = $p_info['pid_path'] . '_' . $p_info['id'];
        }
        $data = AuthModel::create($params, true);
        $this->ok($data);
    }

    /**
     * 显示指定的资源
     *
     * @param  int $id
     * @return \think\Response
     */
    public function read($id)
    {
        $data = AuthModel::find($id);
        $this->ok($data);
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
        {
            $params = input();
            $validate = $this->validate($params, [
                'auth_name|权限名称' => 'require',
                'pid|上级权限' => 'require|integer|egt:0',
                'auth_c|控制器名称' => 'length:1,30',
                'auth_a|方法名称' => 'length:1,30',
                'is_nav|是否菜单项' => 'require|in:0,1'
            ]);
            if ($validate !== true) {
                $this->fail($validate);
            }
            if ($params['pid'] == 0) {
                $params['level'] = 0;
                $params['pid_path'] = 0;
            } else {
                $p_info = AuthModel::find($params['pid']);
//                if (!$p_info) {
//                    $this->fail('数据异常');
//                }
                $params['level'] = $p_info['level'] + 1;
                $params['pid_path'] = $p_info['pid_path'] . '_' . $p_info['id'];
            }
            $info = AuthModel::find($id);
            if ($params['level'] > $info['level']) {
                $this->fail('不能降级');
            }
            AuthModel::update($params, ['id' => $id], true);
            $data = AuthModel::find($id);
            $this->ok($data);
        }
    }

    /**
     * 删除指定资源
     *
     * @param  int $id
     * @return \think\Response
     */
    public function delete($id)
    {
        //权限下有子权限，不能删除
        $total = AuthModel::where('pid', $id)->count('id');
        if ($total) {
            $this->fail('有子分类，不能删除');
        }
        AuthModel::destroy($id);
        $this->ok();
    }

    //菜单权限
    public function nav()
    {
        try {
            //获取当前登录用户id
            $admin = Admin::find(input('user_id'));
            $role_id = $admin['role_id'];

            if ($role_id == 1) {
                //超级管理员  全部是导航栏的权限表
                $list = AuthModel::where('is_nav', '1')->select();
            } else {
                //其他管理员  获取role_auth_ids字符串，然后检测权限表的id是否在字符串中
                $role = Role::find($role_id);
                $role_auth_ids = $role['role_auth_ids'];
                $list = AuthModel::where('id', 'in', $role_auth_ids)->where('is_nav', '1')->select();
            }
            $list = (new Collection($list))->toArray();
            $list = get_tree_list($list);
            $this->ok($list);
        } catch (\Exception $e) {
            $this->fail('错误信息：' . $e->getMessage() . ';文件路径：' . $e->getFile() . '行数：' . $e->getLine());
        }
    }
}
