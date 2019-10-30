<?php

namespace app\home\controller;

use app\common\model\Address;
use think\Collection;

class User extends Base
{
    public function info()
    {
        $user_id = session('user_info.id');
        if ($user_id) {
            $my_info = \app\common\model\User::find($user_id)->toArray();
            $birth = explode('-', $my_info['birthday']);
            $area = explode('/', $my_info['area']);
            return view('info', ['user' => $my_info, 'birth' => $birth, 'area' => $area]);
        } else {
            $this->redirect('home/login/login');
        }
    }

    public function data()
    {
        $user_id = session('user_info.id');
        $params = input();

        $validate = $this->validate($params, [
            'nickname|昵称' => 'require',
            'gender|性别' => 'require',
            'year|年' => 'require',
            'month|月' => 'require',
            'day|日' => 'require',
            'province|省' => 'require',
            'city|市' => 'require',
            'district|县' => 'require',
            'job|工作' => 'require',
        ]);
        if ($validate !== true) {
            return json(['code' => 400, 'msg' => $validate]);
        }

        $data['nickname'] = $params['nickname'];
        $data['gender'] = $params['gender'];
        $data['birthday'] = $params['year'] . '-' . $params['month'] . '-' . $params['day'];
        $data['area'] = $params['province'] . '/' . $params['city'] . '/' . $params['district'];
        $data['job'] = $params['job'];

        $res = \app\common\model\User::update($data, ['id' => $user_id], true);

        if ($res) {
            return json(['code' => 200, 'msg' => 'success', 'data' => $res]);
        }
    }

    public function image()
    {
        $user_id = session('user_info.id');
        $file = request()->file('image');
        if (empty($file)) {
            return json(['code' => 400, 'msg' => '必须上传图片']);
        }

        $path = ROOT_PATH . 'public' . DS . 'uploads' . DS . 'user';
        if (!is_dir($path)) mkdir($path);

        $info = $file->validate([
            'size' => 20 * 1024 * 1024,
            'ext' => 'jpg,jpeg,gif,png',
            'type' => 'image/jpeg,image/png,image/gif'
        ])->move($path);

        if (!$info) {
            return json(['code' => 400, 'msg' => $file->getError()]);
        }
        $data['figure_url'] = DS . 'uploads' . DS . 'user' . DS . $info->getSaveName();
        \think\Image::open('.' . $data['figure_url'])->thumb(100, 100)->save('.' . $data['figure_url']);

        $res = \app\common\model\User::update($data, ['id' => $user_id], true);

        if ($res) {
//            echo <<<end
//            <script>alert('上传成功');location.href="info";</script>
//end;
            $this->redirect('home/user/info');
        } else {
            $this->error('上传有误');
        }
    }

    public function safe()
    {
        $user_id = session('user_info.id');
        if ($user_id) {
//            $my_info = \app\common\model\User::find($user_id)->toArray();
            return view('safe');
        } else {
            $this->redirect('home/login/login');
        }
    }

    public function confirm()
    {
        $user_id = session('user_info.id');
        $my_info = \app\common\model\User::find($user_id)->toArray();
        $params = input();
        $validate = $this->validate($params, [
            'password|旧密码' => 'require',
            'new_password|新密码' => 'require|confirm:confirm_password',
        ], ['new_password.confirm' => '两次密码不一致']);
        if ($validate !== true) {
            $this->error($validate, 'safe');
        }
        $params['password'] = encrypt_password($params['password']);
        if ($params['password'] != $my_info['password']) {
            $this->error('原密码错误，请重新输入', 'safe');
        }
        $params['new_password'] = encrypt_password($params['new_password']);
        if ($params['password'] == $params['new_password']) {
            $this->error('不能与之前的密码一致', 'safe');
        }
        $params['password'] = $params['new_password'];
        $res = \app\common\model\User::update($params, ['id' => $user_id], true);
        if ($res) {
            echo <<<end
            <script>alert('修改成功');location.href="safe";</script>
end;
//            $this->redirect('home/user/safe');
        } else {
            $this->error('修改失败');
        }
    }

    public function address()
    {
        $user_id = session('user_info.id');
        if ($user_id) {
            $my_address = \app\common\model\Address::where('user_id', $user_id)->select();
            if (empty($my_address)) {
                $my_address = [];
            }
            $my_address = (new Collection($my_address))->toArray();
//            dump($my_address);die;
            return view('address', ['address' => $my_address]);
        } else {
            $this->redirect('home/login/login');
        }
    }

    public function addAddress()
    {
        $user_id = session('user_info.id');
        $params = input();
//        dump($params);die;
        $validate = $this->validate($params, [
            'phone|电话号' => 'require|number|min:11',
            'address|具体地址' => 'require',
            'area|地址' => 'require',
            'consignee|联系人' => 'require',
        ]);
        if ($validate !== true) {
            $this->error($validate, 'address');
        }
        $params['user_id'] = $user_id;
        $data = \app\common\model\Address::create($params,true);

        return json(['code' => 200, 'msg' => '添加修改','data'=>$data]);
    }

    public function getEditAdd()
    {
        $user_id = session('user_info.id');
        $id = input('id');
//        dump($id);die;
        if ($user_id) {
            $my_address = \app\common\model\Address::where('user_id', $user_id)->where('id',$id)->find();
//            $my_address = (new Collection($my_address))->toArray();
//            dump($my_address);die;
            return json(['code' => 200, 'msg' => '获取成功','data'=>$my_address]);
        } else {
            $this->redirect('home/login/login');
        }
    }

    public function editAddress()
    {
        $params = input();
//        dump($params);die;
        $validate = $this->validate($params, [
            'phone|电话号' => 'require|number|min:11',
            'address|具体地址' => 'require',
            'area|地址' => 'require',
            'consignee|联系人' => 'require',
        ]);
        if ($validate !== true) {
            $this->error($validate, 'address');
        }
        $data = \app\common\model\Address::update($params,['id'=>$params['id']],true);

        return json(['code' => 200, 'msg' => '添加修改','data'=>$data]);
    }

    public function delete(){
        $params = input();
//        dump($params);die;
        Address::destroy($params['id']);
        return json(['code' => 200, 'msg' => '成功删除']);
    }

    public function isDefault(){
        $user_id = session('user_info.id');
        $params = input();
        $old = Address::where('user_id',$user_id)->where('is_default','1')->find();
        Address::update(['is_default'=>0],['id'=>$old['id']],true);
        Address::update(['is_default'=>1],['id'=>$params['id']],true);

//        Address::destroy($params['id']);
        return json(['code' => 200, 'msg' => '已设置成默认地址']);
    }
}
