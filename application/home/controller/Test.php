<?php

namespace app\home\controller;

use think\Controller;

class Test extends Controller
{
    //创建索引
    public function es()
    {
        $es = \Elasticsearch\ClientBuilder::create()->setHosts(['127.0.0.1:9200'])->build();

//        $params = [
//            'index' => 'test_index'
//        ];
//        $r = $es->indices()->create($params);

//        $params = [
//            'index' => 'test_index',
//            'type' => 'test_type',
//            'id' => 1,
//            'body' => ['id'=>1, 'title'=>'PHP从入门到精通', 'author' => '张三']
//        ];
//        $r = $es->index($params);

//        $params = [
//            'index' => 'test_index',
//            'type' => 'test_type',
//            'id' => 1,
//            'body' => [
//                'doc' => ['id'=>1, 'title'=>'ES从入门到精通', 'author' => '张三']
//            ]
//        ];
//        $r = $es->update($params);

//        $params = [
//            'index' => 'test_index',
//            'type' => 'test_type',
//            'id' => 1,
//        ];
//        $r = $es->delete($params);

//        dump($r);die;
    }
}
