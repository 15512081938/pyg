<?php

namespace app\adminapi\controller;

use app\adminapi\logic\AuthLogic;
use think\Controller;
use tools\jwt\Token;

class BaseApi extends Controller
{

    public function _initialize()
    {
        parent::_initialize();
        //允许的源域名
        header("Access-Control-Allow-Origin: http://localhost:8080");
        //允许的请求头信息
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
        //允许的请求类型
        header('Access-Control-Allow-Methods: GET, POST, PUT,DELETE,OPTIONS,PATCH');
        //允许携带证书式访问（携带cookie）
        header('Access-Control-Allow-Credentials:true');

        //检查token
        $this->checkLogin();

        //检查角色权限
        $res = AuthLogic::check();
        if (!$res) {
            $this->fail('无权访问', 402);
        }
    }

    //封装的返回参数
    public static function response($code = 200, $msg = 'success', $data = [])
    {
        $res = [
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ];
        json($res)->send();
        die;
    }

    public function ok($data = [], $code = 200, $msg = 'success')
    {
        return self::response($code, $msg, $data);
    }

    public function fail($msg = 'error', $code = 400)
    {
        return self::response($code, $msg);
    }

    //无需进行登录检测的请求
    protected $loginAggregate = ['login/login', 'login/captcha'];

    public function checkLogin()
    {
        try {
            //如果是登录界面（包含验证码），则不需要验证token
            $path = strtolower(request()->controller() . '/' . request()->action());
            if (in_array($path, $this->loginAggregate)) {
                return;
            }
            //如果不是登录界面，就从token中获取用户id
            $user_id = Token::getUserId();
            if (!$user_id) {
                $this->fail('未登录或token无效');
            }
            //将用户id保存到请求信息中，从这里获取get当前用户的id  input('user_id')
            $this->request->get(['user_id' => $user_id]);
            $this->request->post(['user_id' => $user_id]);

        } catch (\Exception $e) {
//            $this->fail('token解析失败');
            $this->fail($e->getMessage() . ';file:' . $e->getFile() . ';line:' . $e->getLine());
        }
    }
}
