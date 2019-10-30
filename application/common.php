<?php

// 应用公共文件
//密码加密
if (!function_exists('encrypt_password')) {
    function encrypt_password($password)
    {
        $pre = 'pyg';
        return md5(md5($pre . trim($password)));
    }
}
if (!function_exists('get_cate_list')) {
    //递归函数 实现无限级分类列表
    function get_cate_list($list, $pid = 0, $level = 0)
    {
        static $tree = array();
        foreach ($list as $row) {
            if ($row['pid'] == $pid) {
                $row['level'] = $level;
                $tree[] = $row;
                get_cate_list($list, $row['id'], $level + 1);
            }
        }
        return $tree;
    }
}

if (!function_exists('get_tree_list')) {
    //引用方式实现 父子级树状结构
    function get_tree_list($list)
    {
        //将每条数据中的id值作为其下标
        $temp = [];
        foreach ($list as $v) {
            $v['son'] = [];
            $temp[$v['id']] = $v;
        }
        //获取分类树
        foreach ($temp as $k => $v) {
            $temp[$v['pid']]['son'][] = &$temp[$v['id']];
        }
        return isset($temp[0]['son']) ? $temp[0]['son'] : [];
    }
}

//预防xxs攻击
if (!function_exists('remove_xss')) {
    //使用htmlpurifier防范xss攻击
    function remove_xss($string)
    {
        //composer安装的，不需要此步骤。相对index.php入口文件，引入HTMLPurifier.auto.php核心文件
//         require_once './plugins/htmlpurifier/HTMLPurifier.auto.php';
        // 生成配置对象
        $cfg = HTMLPurifier_Config::createDefault();
        // 以下就是配置：
        $cfg->set('Core.Encoding', 'UTF-8');
        // 设置允许使用的HTML标签
        $cfg->set('HTML.Allowed', 'div,b,strong,i,em,a[href|title],ul,ol,li,br,p[style],span[style],img[width|height|alt|src]');
        // 设置允许出现的CSS样式属性
        $cfg->set('CSS.AllowedProperties', 'font,font-size,font-weight,font-style,font-family,text-decoration,padding-left,color,background-color,text-align');
        // 设置a标签上是否允许使用target="_blank"
        $cfg->set('HTML.TargetBlank', TRUE);
        // 使用配置生成过滤用的对象
        $obj = new HTMLPurifier($cfg);
        // 过滤字符串
        return $obj->purify($string);
    }
}

//加密电话号码 如形式 155****1938
if (!function_exists('enctype_phone')) {
    function enctype_phone($phone)
    {
        return substr($phone, 0, 4) . '****' . substr($phone, 7);
    }
}
//获取接口返回的返回值
//  $url      传入的地址
//  $post     是否是post请求
//  $params   传入的地址参数
//  $https    是否是https使用本地整数
if (!function_exists('curl_request')) {
    function curl_request($url, $post = false, $params = [], $https = false)
    {
        $ch = curl_init($url);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }
        if ($https) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  //返回值形式接受
        $res = curl_exec($ch);
        if (!$res) {
            return [curl_error($ch)]; //数组形式传回错误
        }
        curl_close($ch);
        return $res;
    }
}
//发送短信的函数
// phone  电话号码
// msg    接收的短信内容
if (!function_exists('send_msg')) {
    function send_msg($phone, $msg)
    {
        $gateway = config('msg.gateway');  //网址
        $appkey = config('msg.appkey');   //授权码
        $url = $gateway . '?appkey=' . $appkey . '&moblie=' . $phone . '&content=' . $msg;

        $res = curl_request($url, false, [], true);
        if (is_array($res)) {
            return $res[0];   //返回错误
        }
        //请求成功，转换数据，
        $arr = json_decode($res, true);  //转换成json格式
        if (!isset($arr['code']) || $arr['code'] != 10000) {
            return $arr['msg'] ?? '短信接口异常';
        }
        if (!isset($arr['result']['ReturnStatus']) || $arr['result']['ReturnStatus'] != 'Success') {
            return $arr['result']['Message'] ?? '短信发送失败';
        }
        return true;
    }
}