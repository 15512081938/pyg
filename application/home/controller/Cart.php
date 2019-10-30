<?php

namespace app\home\controller;

use app\common\model\Category;
use app\common\model\Goods;
use app\home\logic\CartLogic;
use app\home\logic\GoodsLogic;
use think\Collection;

class Cart extends Base
{
    public function addcart()
    {
        //如果是get请求（相当于直接在浏览器上输入网址） 就直接让他返回到购物车主页
        if (request()->isGet()) {
            $this->redirect('home/cart/index');
        }

        $params = input();
        $number = $params['number'];
        $validate = $this->validate($params, [
            'goods_id' => 'require|integer|gt:0',
            'spec_goods_id' => 'integer|egt:0',
            'number' => 'require|integer|gt:0'
        ]);
        if ($validate !== true) {
            $this->error($validate);
        }
        //处理数据
        CartLogic::addCart($params['goods_id'], $params['spec_goods_id'], $params['number']);
        $goods = GoodsLogic::getGoodsWithSpecGoods($params['goods_id'], $params['spec_goods_id']);


//                $cate_three = \app\common\model\Goods::where('id',$params['goods_id'])->find();
//                $cate_id = $cate_three['cate_id'];
//        //        $res = \app\common\model\Goods::where('cate_id',$cate_id)->where('id','not in',$cate_three['id'])->select();
//                $cate_two = \app\common\model\Category::where('id',$cate_id)->find();
//                $res = \app\common\model\Category::where('pid',$cate_two['pid'])->select();
//                $three_id = array_column($res,'id');
//                $three_id = implode(',',$three_id);
//
//        //        $res = \app\common\model\Goods::where('cate_id','in',$three_id)->where('id','not in',$cate_three['id'])->select();
//
//                $cate_one = \app\common\model\Category::where('id',$cate_two['pid'])->find();
//                $res = \app\common\model\Category::where('pid',$cate_one['pid'])->select();
//                $one_id = array_column($res,'id');
//                $one_id = implode(',',$one_id);
//
//                $two_id = \app\common\model\Category::where('pid','in',$one_id)->select();
//                $two_id = array_column($two_id,'id');
//                $two_id = implode(',',$two_id);
//
//                $res = \app\common\model\Goods::where('cate_id','in',$two_id)->where('id','not in',$cate_three['id'])->select();
//                $res = (new Collection($res))->toArray();
//
//                $res1 = \app\common\model\Goods::where('cate_id','not in',$two_id)->limit(24 - count($res))->select();
//
//                $res = array_merge($res,$res1);
//                dump($res);die;

        $tuijian = $this->tuijian($params['goods_id']);
        return view('addcart', ['number' => $number, 'goods' => $goods, 'tuijian' => $tuijian]);
    }

    public function tuijian($goods_id)
    {
        //查询商品表的分类id
        $cate_id = Goods::where('id', $goods_id)->value('cate_id');
        $data = Goods::where('cate_id', $cate_id)->where('id', '<>', $goods_id)->limit(24)->select();
        //判断数量是否小于24条
        $length = count($data);
        if ($length < 24) {
            //小于24条，继续查找 查询所属三级分类信息 取到 pid  pid_path 得到所属二级分类id
            $pid_path = Category::where('id', $cate_id)->value('pid_path'); //0_2_71
            $pid_temp = explode('_', $pid_path); // [0,2,71]
            //查找 所属二级分类id 下有哪些三级分类id（排除所属三级分类id）  [73,74,75]
            $cate_ids = Category::where('pid', $pid_temp[2])->where('id', '<>', $cate_id)->column('id');
            //继续查询商品 取剩余的个数
            $data1 = Goods::where('cate_id', 'in', $cate_ids)->limit(24 - $length)->select();
            $data = array_merge($data, $data1);
            $length2 = count($data);
            if ($length2 < 24) {
                //小于24条，继续查找 查询所属一级分类下的二级分类id（排除$pid_temp[2]）
                $cate_two_ids = Category::where('pid', $pid_temp[1])->where('id', '<>', $pid_temp[2])->column('id'); //[77,81,92,.....]
                //再查询二级分类下的三级分类id
                $cate_three_ids = Category::where('pid', 'in', $cate_two_ids)->column('id');
                //继续查询商品
                $data2 = Goods::where('cate_id', 'in', $cate_three_ids)->limit(24 - $length2)->select();
                $data = array_merge($data, $data2);
                $length3 = count($data);
                //小于24条，随机取剩下数量的商品
                $ids = array_column($data, 'id');
                if ($length3 < 24) {
                    $data3 = Goods::where('id', 'not in', $ids)->limit(24 - $length3)->select();
                    $data = array_merge($data, $data3);
                }
            }
        }
        return $data;
    }

    public function index()
    {
//        cookie('cart',null);die;
        //查询购物车记录
        /* $list = {
        [0] => array(5) {
            ["id"] => string(6) "70_889"
            ["goods_id"] => string(2) "70"
            ["number"] => string(1) "1"
            ["spec_goods_id"] => string(3) "889"
            ["is_selected"] => string(1) "1"
          }
        }*/
        $list = CartLogic::getAllCart();
//        dump($list);die;
        //对每条记录 查询商品和sku信息
        foreach ($list as $k => $v) {
            $list[$k]['goods'] = GoodsLogic::getGoodsWithSpecGoods($v['goods_id'], $v['spec_goods_id']);
        }
//        dump($list);die;
        /*$list =  {
        [0] => array(6) {
            ["id"] => string(6) "70_889"
            ["goods_id"] => string(2) "70"
            ["number"] => string(1) "1"
            ["spec_goods_id"] => string(3) "889"
            ["is_selected"] => string(1) "1"
            ["goods"] => array(13) {
                ["id"] => int(70)
                ["goods_name"] => string(18) "小宝个人情况"
                ["goods_price"] => string(9) "123456.00"
                ["cost_price"] => string(9) "123456.00"
                ["goods_number"] => int(100)
                ["frozen_number"] => int(0)
                ["goods_logo"] => string(90) "\uploads\goods\20190923\thumb_thumb_thumb_thumb_thumb_b77081316c23dd41ff53fa95ff6815ae.jpg"
                ["spec_goods_id"] => int(889)
                ["value_names"] => string(27) "身高:160-170 体重:60-80"
                ["price"] => string(9) "123456.00"
                ["cost_price2"] => string(9) "123456.00"
                ["store_count"] => int(100)
                ["store_frozen"] => int(0)
            }
          }
        }*/
        return view('index', ['list' => $list]);
    }

    //状态修改ajax
    public function changestatus()
    {
        $params = input();
        $validate = $this->validate($params, [
            'id' => 'require',
            'status' => 'require|in:0,1'
        ]);
        if ($validate !== true) {
            return json(['code' => 400, 'msg' => $validate]);
        }
        CartLogic::changeStatus($params['id'], $params['status']);
        return json(['code' => 200, 'msg' => 'success']);
    }

    //数量修改ajax
    public function changenum()
    {
        $params = input();
        $validate = $this->validate($params, [
            'id' => 'require',
            'number' => 'require|integer|gt:0'
        ]);
        if ($validate !== true) {
            return json(['code' => 400, 'msg' => $validate]);
        }
        CartLogic::changeNum($params['id'], $params['number']);
        return json(['code' => 200, 'msg' => 'success']);
    }

    //删除购物车记录ajax
    public function deletecart()
    {
        $params = input();
        if (empty($params['id'])) {
            return json(['code' => 400, 'msg' => '参数错误']);
        }
        CartLogic::deleteCart($params['id']);
        return json(['code' => 200, 'msg' => 'success']);
    }
}
