<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

//return [
//    '__pattern__' => [
//        'name' => '\w+',
//    ],
//    '[hello]'     => [
//        ':id'   => ['index/hello', ['method' => 'get'], ['id' => '\d+']],
//        ':name' => ['index/hello', ['method' => 'post']],
//    ],
//
//];
use think\Route;

Route::domain('adminapi.pyg.com', function () {
    //后台主页域名
    Route::get('/', 'adminapi/index/index');

    //验证码
    Route::get('captcha/:id', "\\think\\captcha\\CaptchaController@index"); //显示验证码
    Route::get('captcha', 'adminapi/login/captcha');  //获取数据

    Route::post('login', 'adminapi/login/login');

    Route::get('logout', 'adminapi/login/logout');


    //单文件上传
    Route::post('logo', 'adminapi/upload/logo');
    Route::post('images', 'adminapi/upload/images');

    //商品分类
    Route::resource('categorys', 'adminapi/category', [], ['id' => '\d+']);
    //商品品牌
    Route::resource('brands', 'adminapi/brand', [], ['id' => '\d+']);
    //商品类型
    Route::resource('types', 'adminapi/type', [], ['id' => '\d+']);
    //商品接口
    Route::resource('goods', 'adminapi/goods', [], ['id' => '\d+']);
    //商品相册图片删除
    Route::delete('delpics/:id', 'adminapi/goods/delpics', [], ['id' => '\d+']);

    //权限接口
    Route::resource('auths', 'adminapi/auth', [], ['id' => '\d+']);
    //菜单权限
    Route::get('nav', 'adminapi/auth/nav');
    //角色接口
    Route::resource('roles', 'adminapi/role', [], ['id' => '\d+']);
    //管理员接口
    Route::resource('admins', 'adminapi/admin', [], ['id' => '\d+']);
});