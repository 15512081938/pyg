<?php

namespace app\home\logic;

use app\common\model\Cart;
use think\Collection;

class OrderLogic
{
    public static function getCartWithGoods()
    {
        //购物车、商品、SKU 信息
        $user_id = session('user_info.id');
        $cart_data = Cart::with('goods,spec_goods')
            ->where('user_id', $user_id)
            ->where('is_selected', 1)
            ->select();
        $cart_data = (new Collection($cart_data))->toArray();

        //使用sku的价格、库存 覆盖 商品价格、库存
        $total_number = 0;
        $total_price = 0;
//        dump($cart_data);die;
        foreach ($cart_data as $k => $v) {
            if ($v['spec_goods_id']) {
                $cart_data[$k]['goods']['goods_price'] = $v['spec_goods']['price'];
                $cart_data[$k]['goods']['cost_price'] = $v['spec_goods']['cost_price'];
                $cart_data[$k]['goods']['goods_number'] = $v['spec_goods']['store_count'];
                $cart_data[$k]['goods']['frozen_number'] = $v['spec_goods']['store_frozen'];
            }
            //累加  计算总数量和金额
            $total_number += $v['number'];
            $total_price += $v['number'] * $v['goods']['goods_price'];
        }
        /*return [
            'cart_data' => $cart_data,
            'total_number' => $total_number,
            'total_price' => $total_price,
        ];*/
        return compact('cart_data','total_number', 'total_price');
    }
}