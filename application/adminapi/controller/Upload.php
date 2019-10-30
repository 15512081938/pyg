<?php

namespace app\adminapi\controller;

class Upload extends BaseApi
{
    public function logo()
    {
        //接收参数
        $params = input();
        //检测类型参数，type值是否为空，是否值在三个类型中
        if (empty($params['type'])) {
            $this->fail('参数错误');
        }
        if (!in_array($params['type'], ['goods', 'brand', 'category'])) {
            $params['type'] = 'other';
        }
        //处理上传操作
        $file = request()->file('logo');  //获取请求的图片文件
        if (empty($file)) {
            $this->fail('必须上传图片');
        }
        //设置路径并且创建路径
        $path = ROOT_PATH . 'public' . DS . 'uploads' . DS . $params['type'];
        if (!is_dir($path)) mkdir($path);
        //上传图片,并检测规格
        $info = $file->validate([
            'size' => 20 * 1024 * 1024,
            'ext' => 'jpg,jpeg,gif,png',
            'type' => 'image/jpeg,image/png,image/gif'
        ])->move($path);
        //上传失败结果处理
        if (!$info) {
            $this->fail($file->getError());
        }
        //返回地址
        $data = DS . 'uploads' . DS . $params['type'] . DS . $info->getSaveName();
        $this->ok($data);
    }

    public function images()
    {
        //接收参数
        $params = input();
        $files = request()->file('images');
        //检测type类型，默认是goods
        $params['type'] = $params['type'] ?? 'goods';
        if (!in_array($params['type'], ['goods', 'brand', 'category'])) {
            $params['type'] = 'goods';
        }
        //检测上传文件，数组
        if (empty($files)) {
            $this->fail('必须上传图片');
        }
        if (!is_array($files)) {
            $this->fail('必须是数组形式上传图片');
        }
        //设置返回值
        $data = [
            'success' => [],
            'error' => []
        ];
        //设置路径并且创建路径
        $path = ROOT_PATH . 'public' . DS . 'uploads' . DS . $params['type'];
        if (!is_dir($path)) mkdir($path);
        //遍历 上传图片,并检测规格
        foreach ($files as $file) {
            $info = $file->validate([
                'size' => 20 * 1024 * 1024,
                'ext' => 'jpg,jpeg,gif,png',
                'type' => 'image/jpeg,image/png,image/gif'
            ])->move($path);
            if ($info) {
                $data['success'][] = DS . 'uploads' . DS . $params['type'] . DS . $info->getSaveName();
            } else {
                $data['error'][] = [
                    'name' => $file->getInfo('name'), //获取失败图片名
                    'msg' => $file->getError()
                ];
            }
        }
        $this->ok($data);
    }
}
