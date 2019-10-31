<?php

namespace app\home\controller;

use app\common\model\OpenUser;
use app\common\model\User;
use app\home\logic\LoginLogic;
use think\Controller;
use think\Cache;

class Login extends Controller
{
    //登录
    public function login()
    {
        $this->view->engine->layout(false);
        return view();
    }

    //注册
    public function register()
    {
        $this->view->engine->layout(false);
        return view();
    }

    //发送验证码
    public function sendcode()
    {
        $params = input();
        $validate = $this->validate($params, [
            'phone|手机号' => 'require|regex:1[3-9]\d{9}',
        ]);
        if ($validate !== true) {
            return json(['code' => 400, 'msg' => $validate]);
        }
        //验证码不能一直发送，需要定个时间
        $before_time = cache('register_time_' . $params['phone']) ?? 0;
        if (time() - $before_time < 60) {
            return json(['code' => 400, 'msg' => '发送太频繁']);
        }
        //发送短信
        $code = mt_rand(1000, 9999);
        //$msg = '【创信】你的验证码是：' . $code . '，3分钟内有效！';
//        $res = send_msg($params['phone'],$msg);
        $res = true;        //开发测试过程，假装短信发送成功 ***
        if ($res) {
            cache('register_code_' . $params['phone'], $code, 180);
            cache('register_time_' . $params['phone'], time(), 180);
            //return json(['code' => 200, 'msg' => '短信发送成功']);
            return json(['code' => 200, 'msg' => '短信发送成功', 'data' => $code]);//开发测试过程 ***
        } else {
            return json(['code' => 401, 'msg' => $res]);
        }
    }

    //注册用户：通过手机号
    public function phone()
    {
        $params = input();
        $validate = $this->validate($params, [
            'phone|手机号' => 'require|regex:1[3-9]\d{9}|unique:user',
            'code|验证码' => 'require',
            'password|密码' => 'require|length:3,20',
            'repassword|确认密码' => 'require|length:3,20|confirm:password'
        ]);
        if ($validate !== true) {
            $this->error($validate);
        }
        //检验 验证码输入的对不对   通过输入的code和缓冲的register_code_xxx对比
        $code = cache('register_code_' . $params['phone']);
        if ($params['code'] != $code) {
            $this->error('验证码错误');
        }
        //验证码使用一次后失效  清除验证码的值
        cache('register_code_' . $params['phone'], null);
        //添加用户
        $params['password'] = encrypt_password($params['password']);
        $params['username'] = $params['phone'];                 //用电话号码作为用户名
        $params['nickname'] = enctype_phone($params['phone']);  //加密中间四位数
        User::create($params, true);

        $this->redirect('home/login/login');
    }

    //登录表单提交
    public function dologin()
    {
        $params = input();
        $validate = $this->validate($params, [
            'username|用户名' => 'require',
            'password|密码' => 'require|length:3,20'
        ]);
        if ($validate !== true) {
            $this->error($validate);
        }
        $params['password'] = encrypt_password($params['password']);
        $find = User::where(function ($qurey) use ($params) {
            $qurey->where('phone', $params['username'])->whereOr('email', $params['username']);
        })->where('password', $params['password'])->find();

        $redis = new \Redis();
        $redis->connect('39.106.71.134', 6379, 100);
        $redis->auth('root');
        $logincount = $redis->get($params['username']) ?: 1;
        $lastcount = 5 - $logincount;

        //$query为了执行where语句，use将参数引入进来
        if ($logincount >= 5) {
            $this->error('您登录太久了，用户已被锁定1分钟，请稍后再试！');
        } elseif ($find) {
            //登录成功
            session('user_info', $find->toArray());
            //迁移cookie购物车到数据表中*****************************************
            LoginLogic::cookieTodb();
            //关联第三方用户
            $three_user_id = session('open_user_id');
            if ($three_user_id) {
                //关联用户
                OpenUser::update(['user_id' => $find['id']], ['id' => $three_user_id], true);
                session('open_user_id', null);      //清除session的id
            }
            //同步昵称到用户表
            $nickname = session('open_user_nickname');
            if ($nickname) {
                User::update(['nickname' => $nickname], ['id' => $find['id']], true);
                session('open_user_nickname', null);
            }
            //登录订单
            $back_url = session('back_url') ?? 'home/index/index';
            $this->redirect($back_url);

        } else {
            $redis->setex($params['username'], 60, $logincount + 1);
            $this->error("尊敬的{$params['username']}用户，您输入的用户名或者密码错误，您已登录{$logincount}次，还剩余{$lastcount}次");
        }
    }

    //退出
    public function logout()
    {
        session(null);
        $this->redirect('home/login/login');
    }

    //qq登录回调地址
    //APP ID ：101774759
    //APP Key ：7925b19272411fa2a21e138f860601c7
    //回调地址：http://www.pyg.com/home/login/qqcallback
    public function qqcallback()
    {
        require_once("./plugins/qq/API/qqConnectAPI.php");
        $qc = new \QC();
        //得到access_token 和 openid
        $access_token = $qc->qq_callback(); //接口调用过程中的临时令牌
        $openid = $qc->get_openid();        //第三方帐号在本应用中的唯一标识
        //获取用户信息
        $qc = new \QC($access_token, $openid);
        $info = $qc->get_user_info();
        //dump($info);die;
//        array(21) {
//        ["ret"] => int(0)
//        ["msg"] => string(0) ""
//        ["is_lost"] => int(0)
//        ["nickname"] => string(18) "逆风而行小宝"
//          ...
        //接下来就是自动登录和注册流程
        //判断是否已经关联绑定用户
        $three_user = OpenUser::where('open_type', 'qq')->where('openid', $openid)->find();
        if ($three_user && $three_user['user_id']) {
            //已经关联过用户，直接登录成功
            //同步用户信息到用户表
            $user = User::find($three_user['user_id']);
            $user->nickname = $info['nickname'];
            $user->save();
            //设置登录标识
            session('user_info', $user->toArray());
            //迁移cookie购物车到数据表中*****************************************
            LoginLogic::cookieTodb();
            $back_url = session('back_url') ?? 'home/index/index';
            $this->redirect($back_url);
        }
        if (!$three_user) {
            //第一次登录，没有记录，添加一条记录到open_user表
            $three_user = OpenUser::create(['open_type' => 'qq', 'openid' => $openid]);
        }
        //让第三方帐号去关联用户（可能是注册，可能是登录）
        //记录第三方帐号到session中，用于后续关联用户
        session('open_user_id', $three_user['id']);       //为了将user_id传给open_user表中
        session('open_user_nickname', $info['nickname']); //为了更新用户昵称
        $this->redirect('home/login/login');
    }
//    public function qqcallback()
//    {
//        require_once("./plugins/qq/API/qqConnectAPI.php");
//        $qc = new \QC();
//        //得到access_token 和 openid
//        $access_token = $qc->qq_callback(); //接口调用过程中的临时令牌
//        $openid = $qc->get_openid();        //第三方帐号在本应用中的唯一标识
//        //获取用户信息
//        $qc = new \QC($access_token, $openid);
//        $info = $qc->get_user_info();
//        //dump($info);die;
////        array(21) {
////        ["ret"] => int(0)
////        ["msg"] => string(0) ""
////        ["is_lost"] => int(0)
////        ["nickname"] => string(18) "逆风而行小宝"
////          ...
//        //接下来就是自动登录和注册流程
//        $user = User::where('open_type', 'qq')->where('openid', $openid)->find();
//        if ($user) {
//            //非第一次登录 同步昵称
//            $user->nickname = $info['nickname'];
//            $user->save();
//        } else {
//            //第一次登录  创建新用户
//            User::create(['open_type' => 'qq', 'openid' => $openid, 'nickname' => $info['nickname']]);
//        }
//        $user = User::where('open_type', 'qq')->where('openid', $openid)->find();
//        session('user_info', $user->toArray());
//        //迁移cookie购物车到数据表中*****************************************
//        LoginLogic::cookieTodb();
//        $back_url = session('back_url') ?? 'home/index/index';
//        $this->redirect($back_url);
//    }
    //支付宝登录回调地址
    //'app_id' => "2019092167735145",
    //'redirect_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/home/login/alicallback',
    //'oauth_url' => 'https://openauth.alipay.com/oauth2/publicAppAuthorize.htm',
    //'gatewayUrl' => "https://openapi.alipay.com/gateway.do",
    //'merchant_private_key' => "MIIEpAIBAAKCAQEAqyYGLrJ2HCcVua7P8qusVVSKcc/ujtaMOM7xTGy0CvCYVyWwiRtAf/CqLgASx87vFc7C2oeyWpdFgowLFeaZAByvPBa2XSeiQD5xKiO6hcPz70/4X006aNorGlK5Kq1Tx65qmPsaI9xZXgixcnnyMHhRIzT2dSOSZFCL0lmD775L/5It+oe0qFp/xgWrSmum4TORQQjTs+eGFMBQfxc/kkE0mRNDhtNwsnkRzqRr8hCjdmtsw5qpKECdHRGGVU4mJiisaksm/ra5zeac9CSXSOgCQsi6LJkM/QarI5PiMoruwCxvPdqD9yJQiW3+mieuWVCQylaNi9lTRTRe8dnclwIDAQABAoIBADzQQwwev5cbUD3tXtiUpaSgaJ0uTE8W7zZUWitUyNjGSutUahkdxNpxMpcr0YCMCCpQkK5D310POVS83EeI6769corAa0ZIif+X8HAPx/w195yGZvO+Jk4Rj5VH5aHDeuyODjSTrOMnLd4a6qqJqEQOzI3dCOHwpNjeQgCZbCcoe2RsoyLkcCK0gI6Unoa/Ky8Uqfp+saSzVZ9qBdwRUPdh3mId66h3HyTI5P+uQHiPkTPyf0yNAxkBi3rFwM/I7X720MeXK2idI5PI3mJHM0AcCXMEghQVcpIsW3QB8yDcVfuhoOUzpGPxov/BCs4oRENpqGAKr+T1ol0RRjEEX3ECgYEA1MaYOsJdC+Y8fGvsmaDViPrauVwfuAvQp/k2wcDSwhWqm9HPJExDpEVyntQbkPlIQPQ4IzA/i/lApooKLsNK4pQps0acP5nkIwNGOxuXDjvdqyxa7Dqnf8gaF0sVkMGN3IOI6CfYNgGcrNMS/3XJkXGC8qIZBGTWlzsEHXudPrkCgYEAzeqa6D4H3YCZ9Y3zgPy1s4EZzq4aDzrZfRsXK3N9TY8zoIdetMbcLxo2akQCwge44Dua3PkB1KON5x1ELu7xDleV28WdTb5S3gSF6JmC2BbMm127Tk6OVIpokxgYkvPH/1yfWC36uUIAd8HJucAbuyKNUhCuXwqFdnpNPflKzc8CgYEAyxZ3DHahyw7JotR8MzKBPkp5ZDzbBZc5ZGqJaiu6vodhnXKH7RRh57Rlr9WyLhDRdzXjMysFLHwOyiati27Z1iQnHTeDQW2IhCbG7Pnrlylq7cvbSOi/IUcEKEGBZvZnihd+IGCPjRTCz250DqMFbq5Sl3ZCvJ/m9tfcmKt5LfkCgYB6GP45b26ifLrNy5nzheUHxylSUBHLrg2ADSwz64sFDkCCk0Io1zGADH7vi9xGyOVqsG0nUc8wErr1q3jei3gMFQsbAZZAnvXsB6qPPVdEYmB4T5/c0t+6aUeQ0NzhZgPU+0rQspLUfrpgSFwg9DR9RgOeAZ4jZM22btaWRNeCKQKBgQCvWj+XSf2G8rpMI4dVF2u02SIw2vtumtk3R31mWcIPg4AWMjurzV/hyVtBG+ry+16cm73jLXJDfEGHFXDUWPTLmnU6svxQ3lhuePWLIsK4O5YLewxBfRwu3M9xC9SuXdY4EoC3uc94IYc+8ELsfahugLrQWUHEJMf8zqDQjSehEg==",
    public function alicallback()
    {
        require_once('./plugins/alipay/oauth/service/AlipayOauthService.php');
        require_once('./plugins/alipay/oauth/config.php');
        $AlipayOauthService = new \AlipayOauthService($config);
        //获取auth_code ， access_token
        $auth_code = $AlipayOauthService->auth_code();
        $access_token = $AlipayOauthService->get_token($auth_code);
        //获取用户信息  user_id  nick_name
        $info = $AlipayOauthService->get_user_info($access_token);
        $openid = $info['user_id'];
        //dump($info);die;

        //接下来就是关联绑定用户的过程
        //判断是否已经关联绑定用户
        $three_user = OpenUser::where('open_type', 'alipay')->where('openid', $openid)->find();
        if ($three_user && $three_user['user_id']) {
            //已经关联过用户，直接登录成功
            //同步用户信息到用户表
            $user = User::find($three_user['user_id']);
            $user->nickname = $info['nick_name'];
            $user->save();
            //设置登录标识
            session('user_info', $user->toArray());
            //迁移cookie购物车到数据表中*****************************************
            LoginLogic::cookieTodb();
            $back_url = session('back_url') ?? 'home/index/index';
            $this->redirect($back_url);
        }
        if (!$three_user) {
            //第一次登录，没有记录，添加一条记录到open_user表
            $three_user = OpenUser::create(['open_type' => 'alipay', 'openid' => $openid]);
        }
        //让第三方帐号去关联用户（可能是注册，可能是登录）
        //记录第三方帐号到session中，用于后续关联用户
        session('open_user_id', $three_user['id']);
        session('open_user_nickname', $info['nick_name']);
        $this->redirect('home/login/login');
    }

//    public function alicallback()
//    {
//        require_once('./plugins/alipay/oauth/service/AlipayOauthService.php');
//        require_once('./plugins/alipay/oauth/config.php');
//        $AlipayOauthService = new \AlipayOauthService($config);
//        //获取auth_code ， access_token
//        $auth_code = $AlipayOauthService->auth_code();
//        $access_token = $AlipayOauthService->get_token($auth_code);
//
//        //获取用户信息  user_id  nick_name
//        $info = $AlipayOauthService->get_user_info($access_token);
//
//        $openid = $info['user_id'];
////        dump($info);die;
//
//        //接下来就是自动登录和注册流程
//        $user = User::where('open_type', 'alipay')->where('openid', $openid)->find();
//        if ($user) {
//            //非第一次登录 同步昵称
//            $user->nickname = $info['nickname'] ?? $info['user_id'];
//            $user->save();
//        } else {
//            //第一次登录  创建新用户
//            User::create([
//                'open_type' => 'alipay',
//                'openid' => $openid,
//                'nickname' => $info['nickname'] ?? $info['user_id']
//            ]);
//        }
//        $user = User::where('open_type', 'alipay')->where('openid', $openid)->find();
//        session('user_info', $user->toArray());
//        //迁移cookie购物车到数据表中*****************************************
//        LoginLogic::cookieTodb();
//         $back_url = session('back_url') ?? 'home/index/index';
//          $this->redirect($back_url);
//    }
}

