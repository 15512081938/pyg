<?php

namespace app\home\controller;

use app\common\model\KillOrders;
use app\common\model\KillGoods as KGoods;
use think\Controller;
use think\Request;

class KillGoods extends Controller
{
//http://www.pyg.com/home/Kill_goods/addlist/goods_id/1
    public function addlist()
    {
        $redis = new \Redis();
        $redis->connect('39.106.71.134', 6379, 100);
        $redis->auth('root');
        $params = input();
        $goods_number = KGoods::where('goods_id', $params['goods_id'])->value('goods_number');
        for ($i = 0; $i < $goods_number; $i++) {
            $redis->lpush('pyg_kill_goods' . $params['goods_id'], '1');
        }
        $res = $redis->llen('pyg_kill_goods' . $params['goods_id']);
        dump($res);
    }
//http://www.pyg.com/home/Kill_goods/goodskill/goods_id/1
    public function goodskill()
    {
        $redis = new \Redis();
        $redis->connect('39.106.71.134', 6379, 100);
        $redis->auth('root');
        $params = input();
        $res = $redis->lpop('pyg_kill_goods' . $params['goods_id']);
        if (!$res) {
            return '商品已经被抢完';
        }
        $result = KGoods::where('goods_id', $params['goods_id'])->setDec('goods_number');
        if ($result) {
            $data = [
                'user_id' => session('user_info.id'),
                'order_id' => date('YmdHis') . rand(1000, 9999)
            ];
            KillOrders::create($data,true);
            $this->success('支付成功','home/index/index');
        } else {
            $redis->lpush('pyg_kill_goods' . $params['goods_id'], '1');
            $this->error('您没有抢到商品');
        }
    }
}
