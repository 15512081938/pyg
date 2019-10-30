<?php

namespace app\home\logic;

class LoginLogic
{
    public static function cookieTodb()
    {
        $data = cookie('cart') ?? [];
        foreach ($data as $v) {
            //$v :  ['id'=>'69_834', 'goods_id'=>69, 'spec_goods_id'=>834, 'number' => 10, 'is_selected'=>1]
            //每一条数据，对数据表来说，都是一个加入购物车功能
            CartLogic::addCart($v['goods_id'], $v['spec_goods_id'], $v['number']);
        }
        //迁移完之后，清空本地cookie购物车数据
        cookie('cart', null);
    }
}