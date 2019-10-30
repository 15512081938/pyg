<?php

namespace app\home\controller;

use app\common\model\Category;
use app\home\logic\CartLogic;
use app\home\logic\GoodsLogic;
use think\Collection;
use think\Controller;
use think\Request;

class Base extends Controller
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        //获取列表
        $this->getCategory();
        $this->getUser();
        $this->getNumber();
    }

    public function getNumber()
    {
        $data = CartLogic::getAllCart();
//        dump($data);
        $num = 0;
        $price = 0;
        foreach ($data as $k => $v) {
            $num += $v['number'];
            $data[$k]['goods'] = GoodsLogic::getGoodsWithSpecGoods($v['goods_id'], $v['spec_goods_id']);
        }
        foreach ($data as $k => $v) {
            $price += $v['goods']['price'] * $v['number'];
        }

        $this->assign('cart_num', $num);
        $this->assign('cart_price', $price);
        $this->assign('cart_info', $data);
    }

    public function getUser()
    {
        $user_id = session('user_info.id');
        $my_info = \app\common\model\User::find($user_id)->toArray();
        $this->assign('userinfo', $my_info);
    }

    public function getCategory()
    {
        $category = cache('category');
        if (empty($category)) {
            $category = Category::select();
            $category = (new Collection($category))->toArray();
            $category = get_tree_list($category);
            cache('category', $category, 24 * 3600);
        }
        $this->assign('category', $category);
    }
}
