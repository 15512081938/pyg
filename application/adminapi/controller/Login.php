<?php

namespace app\adminapi\controller;

use app\common\model\Admin;
use tools\jwt\Token;

class Login extends BaseApi
{
    //验证码接口 负责提供验证码路径和随机码
    public function captcha()
    {
        $uniqid = uniqid('pyg', true);
        $data = [
            'url' => captcha_src($uniqid),
            'uniqid' => $uniqid
        ];
        $this->ok($data);
    }

    //登录接口
    public function login()
    {
        $params = input();
        //验证输入的信息，验证码
        $validate = $this->validate($params, [
            'username' => 'require',
            'password' => 'require',
            'uniqid' => 'require',
            'code' => 'require|captcha:' . $params['uniqid'] //验证是否值符合要求（防止前端出错）+ 验证码
        ]);
        if ($validate !== true) {
            $this->fail($validate);
        }

        //验证用户名，密码（确认数据正确）
        $where = [
            'username' => $params['username'],
            'password' => encrypt_password($params['password'])
        ];
        $info = Admin::where($where)->find();  //返回值：对象
        if (!$info) {
            $this->fail('用户名或者密码错误');
        }
        //将接口值存到data里返回给前端
        $data = [
            'token' => Token::setToken($info->id),
            'user_id' => $info['id'],
            'username' => $info['username'],
            'nickname' => $info['nickname'],
            'email' => $info['email'],
        ];
        //将接口值存到data里返回给前端
//        $data['token'] = Token::setToken($info->id);
//        $data['user_id'] = $info->id;
//        $data['username'] = $info->username;
//        $data['nickname'] = $info->nickname;
//        $data['email'] = $info->email;
        $this->ok($data);
    }

    //退出接口
    public function logout()
    {
        //清理缓冲字段delete_token
        $delete_token = cache('delete_token') ?: []; //如果缓冲有值，就按原来的,没有就定义空数组
        $delete_token[] = Token::getRequestToken();  //将token存入缓冲中
        cache('delete_token', $delete_token, 3600 * 24); //设置缓冲数据，一天有效期
        $this->ok();
    }
}
