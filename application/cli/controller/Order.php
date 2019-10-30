<?php

namespace app\cli\controller;

use app\common\model\Goods;
use app\common\model\OrderGoods;
use app\common\model\SpecGoods;
use think\Controller;
use app\common\model\Order as OrderModer;

class Order extends Controller
{
    //支付超时，自动取消订单
    public function autoCancel()
    {
//        dump(time() - 30 * 60);
//        die;
        while (true) {
            //查询超时的订单 30分钟之前创建的订单，未付款状态
            $order = OrderModer::where('order_status', 0)
                ->where('create_time', '<', time() - 30 * 60)
                ->find();
            if ($order) {
                //取消订单
                $order->order_status = 5;
                $order->save();

                //恢复冻结的库存
                $order_goods = OrderGoods::with('goods,spec_goods')
                    ->where('order_id', $order['id'])
                    ->select();
                $goods = [];
                $spec_goods = [];
                foreach ($order_goods as $k => $v) {
                    if ($v['spec_goods_id']) {
                        //恢复sku表的冻结库存
                        $spec_goods[] = [
                            'id' => $v['spec_goods_id'],
                            'store_frozen' => $v['spec_goods']['store_frozen'] - $v['number'],
                            'store_count' => $v['spec_goods']['store_count'] + $v['number'],
                        ];
                    } else {
                        //恢复商品表的冻结库存
                        $goods[] = [
                            'id' => $v['goods_id'],
                            'frozen_number' => $v['goods']['frozen_number'] - $v['number'],
                            'goods_number' => $v['goods']['goods_number'] + $v['number'],
                        ];
                    }
                }
                //批量修改
                $goods_model = new Goods();
                $goods_model->saveAll($goods);
                $spec_goods_model = new SpecGoods();
                $spec_goods_model->saveAll($spec_goods);
                echo 'success';
            } else {
                sleep(1);
            }
        }
    }
}
