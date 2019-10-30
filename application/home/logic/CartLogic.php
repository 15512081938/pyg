<?php

namespace app\home\logic;

use app\common\model\Cart;
use think\Collection;

class CartLogic
{
    /**
     * 加入购物车
     */
    public static function addCart($goods_id, $spec_goods_id, $number, $is_selected = 1)
    {
        //判断登录状态：已登录，添加到数据表；未登录，添加到cookie
        if (session('?user_info')) {
            $user_id = session('user_info.id');
            //判断是否存在相同购物记录（同一个用户，同一个商品，同一个sku）
            $where = [       //这里的where数既是条件， 也是data（添加数据用的数组）
                'user_id' => $user_id,
                'goods_id' => $goods_id,
                'spec_goods_id' => $spec_goods_id
            ];
            $info = Cart::where($where)->find();
            if ($info) {
                //存在相同记录，累加购买数量
                $info->number += $number;
                $info->is_selected = $is_selected;
                $info->save();
            } else {
                //不存在，添加新记录
                $where['number'] = $number;
                $where['is_selected'] = $is_selected;
                Cart::create($where, true);
            }
        } else {
            $data = cookie('cart') ?? [];
            //拼接当前记录的下标
            $k = $goods_id . '_' . $spec_goods_id;
            //判断是否存在相同购物记录
            if (isset($data[$k])) {
                //更新数量（累加） ，修改数量和选中参数
                $data[$k]['number'] += $number;
                $data[$k]['is_selected'] = $is_selected;
            } else {
                //添加新记录
                $data[$k] = [
                    'id' => $k,
                    'goods_id' => $goods_id,
                    'spec_goods_id' => $spec_goods_id,
                    'number' => $number,
                    'is_selected' => $is_selected,
                ];
            }
            //重新保存到cookie
            cookie('cart', $data, 86400 * 7);
        }
    }

    public static function getAllCart()
    {
        //判断登录状态：未登录：取cookie; 已登录：取数据表
        if (session('?user_info')) {
            $user_id = session('user_info.id');
            $data = Cart::where('user_id', $user_id)->select();
            $data = (new Collection($data))->toArray();
        } else {
            $data = cookie('cart') ?? [];
//            dump(cookie('cart'));
            $data = array_values($data);   //讲下标变成0 .. 索引数组
        }
//        dump($data);die;
        return $data;
    }

    public static function changeStatus($id, $is_selected)
    {
        if (session('?user_info')) {
            $user_id = session('user_info.id');
            $where['user_id'] = $user_id;  //肯定有用户，user_id必须有
            if ($id != 'all') {
                //修改指定的一条
                $where['id'] = $id;    //如果是单个，就修改单个id就可以,默认是all,就不需要加id字段了
            }
            Cart::update(['is_selected' => $is_selected], $where, true);
        } else {
            //修改cookie
            $data = cookie('cart') ?: [];
            if ($id != 'all') {
                //修改指定的一条
                $data[$id]['is_selected'] = $is_selected;
            } else {
                //修改所有
                foreach ($data as $k => $v) {
                    $data[$k]['is_selected'] = $is_selected;
                }
            }
            //重新保存到cookie
            cookie('cart', $data, 86400 * 7);
        }
    }

    public static function changeNum($id, $number)
    {
        if (session('?user_info')) {
            $user_id = session('user_info.id');
            //修改数据
            Cart::update(['number' => $number], ['id' => $id, 'user_id' => $user_id], true);
        } else {
            $data = cookie('cart') ?: [];
            //修改数据
            $data[$id]['number'] = $number;
            //重新保存到cookie
            cookie('cart', $data, 86400 * 7);
        }
    }

    public static function deleteCart($id)
    {
        if (session('?user_info')) {
            $user_id = session('user_info.id');
            //删除数据表
            Cart::destroy(['id' => $id, 'user_id' => $user_id]);
        } else {
            $data = cookie('cart') ?: [];
            //删除
            unset($data[$id]);
            cookie('cart', $data, 86400 * 7);
        }
    }
}