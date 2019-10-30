<?php

namespace app\home\controller;

use app\common\model\PayLog;
use app\common\model\SpecGoods;
use app\common\model\Goods;
use app\common\model\Address;
use app\common\model\Order as OrderModel;
use app\common\model\OrderGoods;
use app\home\logic\OrderLogic;
use think\Db;
use think\Exception;
use think\Request;
use app\common\model\Cart;

class Order extends Base
{
    public function create()
    {
        //登录判断
        if (!session('?user_info')) {
            session('back_url', 'home/cart/index');
            $this->redirect('home/login/login');
        }
        //获取地址
        $user_id = session('user_info.id');
        $address = Address::where('user_id', $user_id)->select();
//        dump($address);die;
        //查询结算购物
        $res = OrderLogic::getCartWithGoods();
        /* $res =  [
            'cart_data' => $cart_data,
            'total_number' => $total_number,
            'total_price' => $total_price,
        ];*/
        $res['address'] = $address;
        return view('create', $res);
    }

    public function save(Request $request)
    {
        $params = input();
        $validate = $this->validate($params, [
            'address_id|收货地址id' => 'require|integer|gt:0',
        ]);
        if ($validate !== true) {
            $this->error($validate);
        }
        //数据处理
        Db::startTrans();
        try {
            //向订单表添加一条数据
            $user_id = session('user_info.id');
            //生成订单编号
            $order_sn = time() . mt_rand(100000, 999999) . $user_id;
            //查询收货地址信息
            $address = Address::where('user_id', $user_id)->find($params['address_id']);
            if (!$address) {
                throw new Exception('收货地址信息错误');
            }
            //查询结算购物,  购物记录和商品信息   计算商品总价  $res['total_price']
            $res = OrderLogic::getCartWithGoods();
            //检测库存
            foreach ($res['cart_data'] as $v) {
                //$v['number']我要买的数量  $v['goods']['goods_number']商品总量
                if ($v['number'] > $v['goods']['goods_number']) {
                    throw new Exception('订单中包含库存不足的商品');
                }
            }
            //组装要添加的订单数据
            $order_data = [
                'order_sn' => $order_sn,
                'user_id' => $user_id,
                'consignee' => $address['consignee'],
                'address' => $address['area'] . $address['address'],
                'phone' => $address['phone'],
                'goods_price' => $res['total_price'], //商品总价
                'shipping_price' => '0.00', //邮费
                'coupon_price' => '0.00', //优惠抵扣金额
                'order_amount' => $res['total_price'], //应付金额 = 商品总价 + 邮费 - 优惠抵扣金额
                'total_amount' => $res['total_price'], //订单总金额 = 商品总价 + 邮费
            ];
            $order = OrderModel::create($order_data, true);

            //向订单商品表添加多条数据
            $order_goods_data = [];
            foreach ($res['cart_data'] as $v) {
                $order_goods_data[] = [
                    'order_id' => $order['id'],
                    'goods_id' => $v['goods_id'],
                    'spec_goods_id' => $v['spec_goods_id'],
                    'number' => $v['number'],
                    'goods_name' => $v['goods']['goods_name'],
                    'goods_logo' => $v['goods']['goods_logo'],
                    'goods_price' => $v['goods']['goods_price'],
                    'spec_value_names' => $v['spec_goods']['value_names']
                ];
            }
            //批量添加数据
            $order_goods_model = new OrderGoods();
            $order_goods_model->saveAll($order_goods_data);

            //从购物车表删除多条数据
            Cart::destroy(['user_id' => $user_id, 'is_selected' => 1]);

            //预扣减库存（冻结库存） ,单独定义，为了修改两个表
            $goods = [];
            $spec_goods = [];

            foreach ($res['cart_data'] as $v) {
                if ($v['spec_goods_id']) {   //如果存在sku表说明购买的是sku的商品，修改sku表
                    $spec_goods[] = [
                        'id' => $v['spec_goods_id'],
                        'store_count' => $v['goods']['goods_number'] - $v['number'],
                        'store_frozen' => $v['goods']['frozen_number'] + $v['number'],
                    ];
                } else {     //否则修改商品表
                    $goods[] = [
                        'id' => $v['goods_id'],
                        'goods_number' => $v['goods']['goods_number'] - $v['number'],
                        'frozen_number' => $v['goods']['frozen_number'] + $v['number'],
                    ];
                }
            }
            //修改商品表，如果是sku表，则goods表修改无效
            $spec_goods_model = new SpecGoods();
            $spec_goods_model->saveAll($spec_goods);
            $goods_model = new Goods();
            $goods_model->saveAll($goods);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage() . ';File:' . $e->getFile() . ':Line:' . $e->getLine());
        }
        //接下来是显示 选择支付方式 页面
        $this->redirect('home/order/pay', ['id' => $order['id']]);
//        $this->redirect('home/order/pay?id=' . $order['id']);
    }

    public function pay($id)
    {
        //查询订单信息 支付方式
        $order = OrderModel::find($id);
        $pay_type = config('pay_type');

        //二维码图片中的支付链接（本地项目自定义链接，传递订单id参数）
        //$url = url('/home/order/qrpay', ['id'=>$order->order_sn], true, true);
        //用于测试的线上项目域名 http://pyg.tbyue.com

        $url = url('/home/order/qrpay', ['id' => $order->order_sn, 'debug' => 'true'], true, "http://pyg.tbyue.com");
        //生成支付二维码
        $qrCode = new \Endroid\QrCode\QrCode($url);
        //二维码图片保存路径（请先将对应目录结构创建出来，需要具有写权限）
        $qr_path = '/uploads/qrcode/' . uniqid(mt_rand(100000, 999999), true) . '.png';
        //将二维码图片信息保存到文件中
        $qrCode->writeFile('.' . $qr_path);
        $this->assign('qr_path', $qr_path);

        return view('pay', ['order' => $order, 'pay_type' => $pay_type]);
    }

    public function topay()
    {
        $params = input();
        $validate = $this->validate($params, [
            'pay_type' => 'require',
            'id' => 'require|integer|gt:0',
        ]);
        if ($validate !== true) {
            $this->error($validate);
        }
        //查询订单信息
        $order = OrderModel::find($params['id']);
//        dump($order);
//        dump($params);
        //判断支付方式
        switch ($params['pay_type']) {
            case 'wechat':
                echo '微信支付正在开发中';
                break;
            case 'union':
                echo '银联支付正在开发中';
                break;
            case 'alipay':
            default:
                echo "<form id='alipayment' action='/plugins/alipay/pagepay/pagepay.php' method='post' style='display: none'>
    <input id='WIDout_trade_no' name='WIDout_trade_no' value='{$order['order_sn']}'/>
    <input id='WIDsubject' name='WIDsubject' value='iphone 11 pro手机订单'/>
    <input id='WIDtotal_amount' name='WIDtotal_amount' value='{$order['order_amount']}'/>
    <input id='WIDbody' name='WIDbody' value='沙箱环境的描述'/>
</form><script>document.getElementById('alipayment').submit();</script>";
        }
    }

    //支付宝同步通知（浏览器跳转）
    public function callback()
    {
        $params = input();
        //验证证书
        require_once './plugins/alipay/config.php';
        require_once './plugins/alipay/pagepay/service/AlipayTradeService.php';
        $alipaySevice = new \AlipayTradeService($config);
        $res = $alipaySevice->check($params);

        if (!$res) {
            return view('payfail', [
                'msg' => '订单编号错误'
            ]);
        } else {
            return view('paysuccess', [
                'pay_type' => '支付宝',
                'total_amount' => $params['total_amount']
            ]);
        }
    }

    //支付宝异步通知
    public function notify()
    {
        $params = input();
        //记录日志，用于后续追踪解决问题
        trace('order-notify 开始，参数：' . json_encode($params, JSON_UNESCAPED_UNICODE), 'info');
        //验证签名
        require_once("./plugins/alipay/config.php");
        require_once './plugins/alipay/pagepay/service/AlipayTradeService.php';
        $alipaySevice = new \AlipayTradeService($config);
        $res = $alipaySevice->check($params);

        if ($res) {
            //验签成功
            if ($params['trade_status'] == 'TRADE_SUCCESS') {
                //支付成功的处理
                $order = OrderModel::where('order_sn', $params['out_trade_no'])->find();
                //查询订单信息并判断
                if (!$order) {
                    trace('order-notify 支付失败：订单不存在；订单编号：' . $params['out_trade_no'], 'error');
                    echo 'fail';
                    die;
                }
                //判断交易金额是否正确
                if ($order['order_amount'] != $params['total_amount']) {
                    trace('order-notify 支付失败：交易金额不正确；订单应付款金额：' . $order['order_amount'] . ';实际支付金额：' . $params['total_amount'], 'error');
                    echo 'fail';
                    die;
                }
                //判断订单状态是否为未付款
                if ($order['order_status'] != 0) {
                    trace('order-notify 支付失败：订单状态不是未付款；状态值为：' . $order['order_status'], 'error');
                    echo 'fail';
                    die;
                }
                //修改订单状态
                $order->order_status = 1;
                $order->pay_time = time();
                $order->save();
                //记录支付信息
                PayLog::create([
                    'order_sn' => $params['out_trade_no'],
                    'json' => json_encode($params, JSON_UNESCAPED_UNICODE)
                ], true);

                //扣减冻结库存
                $order_goods = OrderGoods::with('goods,spec_goods')->where('order_id', $order['id'])->select();
                $goods = [];
                $spec_goods = [];
                foreach ($order_goods as $k => $v) {
                    if ($v['spec_goods_id']) {
                        //修改sku表的冻结库存
                        $spec_goods[] = [
                            'id' => $v['spec_goods_id'],
                            'store_frozen' => $v['spec_goods']['store_frozen'] - $v['number']
                        ];
                    } else {
                        //修改商品表的冻结库存
                        $goods[] = [
                            'id' => $v['goods_id'],
                            'frozen_number' => $v['goods']['frozen_number'] - $v['number']
                        ];
                    }
                }
                //批量修改
                $goods_model = new Goods();
                $goods_model->saveAll($goods);
                $spec_goods_model = new SpecGoods();
                $spec_goods_model->saveAll($spec_goods);
                echo 'success';
                die;
            } else if ($params['trade_status'] == 'TRADE_FINISHED') {
                //退款的处理  略
                echo 'success';
                die;
            }
            echo 'success';
            die;
        } else {
            //验签失败
            echo 'fail';
            die;
        }
    }

    //扫码支付
    public function qrpay()
    {
        $agent = request()->server('HTTP_USER_AGENT'); //用户浏览器的信息
        //判断扫码支付方式
        if (strpos($agent, 'MicroMessenger') !== false) {
            //微信扫码
            $pay_code = 'wx_pub_qr';
        } else if (strpos($agent, 'AlipayClient') !== false) {
            //支付宝扫码
            $pay_code = 'alipay_qr';
        } else {
            //默认为支付宝扫码支付
            $pay_code = 'alipay_qr';
        }
        //接收订单id参数
        $order_sn = input('id');
        //创建支付请求
        $this->pingpp($order_sn, $pay_code);
    }

    //发起ping++支付请求
    public function pingpp($order_sn, $pay_code)
    {
        //查询订单信息
        $order = OrderModel::where('order_sn', $order_sn)->find();
        //ping++聚合支付
        \Pingpp\Pingpp::setApiKey(config('pingpp.api_key'));// 设置 API Key
        \Pingpp\Pingpp::setPrivateKeyPath(config('pingpp.private_key_path'));// 设置私钥
        \Pingpp\Pingpp::setAppId(config('pingpp.app_id'));
        $params = [
            'order_no' => $order['order_sn'],
            'app' => ['id' => config('pingpp.app_id')],
            'channel' => $pay_code,
            'amount' => $order['order_amount'] * 100,
            'client_ip' => '127.0.0.1',
            'currency' => 'cny',
            'subject' => 'Your Subject',//自定义标题
            'body' => 'Your Body',//自定义内容
            'extra' => [],
        ];
        if ($pay_code == 'wx_pub_qr') {
            $params['extra']['product_id'] = $order['id'];
        }
        //创建Charge对象
        $ch = \Pingpp\Charge::create($params);
        //跳转到对应第三方支付链接
        $this->redirect($ch->credential->$pay_code);
        die;
    }

    //查询订单状态
    public function status()
    {
        //接收订单编号
        $order_sn = input('order_sn');
        //查询订单状态
        /*$order_status = \app\common\model\Order::where('order_sn', $order_sn)->value('order_status');
        return json(['code' => 200, 'msg' => 'success', 'data'=>$order_status]);*/
        //通过线上测试
        echo curl_request("http://pyg.tbyue.com/home/order/status/order_sn/{$order_sn}");
        die;
    }

    //ping++支付结果页面
    public function payresult()
    {
        $order_sn = input('order_sn');
        $order = OrderModel::where('order_sn', $order_sn)->find();
        if (empty($order)) {
            return view('payfail', [
                'msg' => '订单编号错误'
            ]);
        } else {
            return view('paysuccess', [
                'pay_type' => $order->pay_name,
                'total_amount' => $order['total_amount']
            ]);
        }
    }
}
