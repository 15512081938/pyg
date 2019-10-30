<?php

namespace app\home\controller;

use think\Controller;
use app\common\model\Order;
use app\common\model\PayLog;

class Notify extends Controller
{
    //ping++异步通知 (webhooks)
    public function pingpp()
    {
        try {
            //接收参数
            $params = file_get_contents("php://input");
            //获取请求头信息，用于获取签名
            $headers = \Pingpp\Util\Util::getRequestHeaders();
            // 签名在头部信息的 x-pingplusplus-signature 字段
            $signature = $headers['X-Pingplusplus-Signature'] ?? null;
            //获取ping++公钥用于签名
            $pub_key_path = config('pingpp.public_key_path');
            $pub_key_contents = file_get_contents($pub_key_path);
            //验证签名
            $result = openssl_verify($params, base64_decode($signature), $pub_key_contents, 'sha256');
            //生成签名
//            function openssl_sign($data, &$signature, $priv_key_id, $signature_alg = OPENSSL_ALGO_SHA1) { }
            //验证签名
//            function openssl_verify($data, $signature, $pub_key_id, $signature_alg = OPENSSL_ALGO_SHA1) { }
            if ($result === 1) {
                // 验证通过 ，加true转换成数组
                $event = json_decode($params, true);
                // 对异步通知做处理
                if (!isset($event['type'])) {
                    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
                    exit("fail");
                }
                switch ($event['type']) {
                    case "charge.succeeded":
                        // 开发者在此处加入对支付异步通知的处理代码
                        //修改订单状态
                        $order_sn = $event['data']['object']['order_no'];
                        $order = Order::where('order_sn', $order_sn)->find();
                        if (empty($order)) {
                            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
                            exit("fail");
                        }
                        $order->order_status = 1;//已付款、待发货
                        $order->pay_code = $event['data']['object']['channel'];  //支付渠道
                        $order->pay_name = $event['data']['object']['channel'] == 'wx_pub_qr' ? '微信支付' : '支付宝';
                        $order->save();
                        PayLog::create(['order_sn' => $order_sn, 'json' => $params]);
                        header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
                        break;
                    case "refund.succeeded":
                        // 开发者在此处加入对退款异步通知的处理代码
                        header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
                        break;
                    default:
                        header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
                        break;
                }
            }
            elseif ($result === 0) {
                http_response_code(400);
                echo 'verification failed';
                exit;
            }
            else {
                http_response_code(400);
                echo 'verification error';
                exit;
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo $e->getMessage();
            exit;
        }
    }
}
