<?php

namespace app\home\logic;

use think\Controller;
use app\common\model\Goods;

class GoodsLogic extends Controller
{
    public static function getGoodsWithSpecGoods($goods_id, $spec_goods_id = 0)
    {
        //设置一个缓冲，为了加入购物车成功的数据，goods表
        $key = 'getGoodsWithSpecGoods_' . $goods_id . '_' . $spec_goods_id;
        $goods = cache($key);
        if (!$goods) {
            if ($spec_goods_id) {
                //如果有sku的id  spec_goods_id,以spec_goods_id为条件
                $where['t2.id'] = $spec_goods_id;
            } else {
                //没有spec_goods_id，则以goods表主键id作为条件
                $where['t1.id'] = $goods_id;
            }
            $goods = Goods::alias('t1')
                ->join('pyg_spec_goods t2', 't1.id=t2.goods_id', 'left')
                ->field('t1.id, t1.goods_name, t1.goods_price, t1.cost_price, t1.goods_number, t1.frozen_number, t1.goods_logo, t2.id as spec_goods_id, t2.value_names, t2.price, t2.cost_price as cost_price2, t2.store_count, t2.store_frozen')
                ->where($where)
                ->find();
            if (empty($goods)) return [];

            //如果sku表有价格、库存等信息，则覆盖到商品表相同含义的字段上
            if ($goods['price'] > 0) {
                $goods['goods_price'] = $goods['price'];
            }
            if ($goods['cost_price2'] > 0) {
                $goods['cost_price'] = $goods['cost_price2'];
            }
            if ($goods['store_count'] > 0) {
                $goods['goods_number'] = $goods['store_count'];
            }
            if ($goods['store_frozen'] > 0) {
                $goods['frozen_number'] = $goods['store_frozen'];
            }
            $goods = $goods->toArray();
            cache($key, $goods, 86400);
        }
        return $goods;
    }

    public static function search()
    {
        //实例化ES工具类
        $es = new \tools\es\MyElasticsearch();
        //计算分页条件
        $keywords = input('keywords');
        $page = input('page', 1);
        $page = $page < 1 ? 1 : $page;
        $size = 10;
        $from = ($page - 1) * $size;
        //组装搜索参数体
        $body = [
            'query' => [
                'bool' => [
                    'should' => [
                        ['match' => ['cate_name' => [
                            'query' => $keywords,
                            'boost' => 4, // 权重大
                        ]]],
                        ['match' => ['goods_name' => [
                            'query' => $keywords,
                            'boost' => 3,
                        ]]],
                        ['match' => ['goods_desc' => [
                            'query' => $keywords,
                            'boost' => 2,
                        ]]],
                    ],
                ],
            ],
            'sort' => ['id' => ['order' => 'desc']],
            'from' => $from,
            'size' => $size
        ];
        //进行搜索
        $results = $es->search_doc('goods_index', 'goods_type', $body);
//        dump($results);die;
        //获取数据
        $data = array_column($results['hits']['hits'], '_source');
        $total = $results['hits']['total']['value'];
        //分页处理
        $list = \tools\es\EsPage::paginate($data, $size, $total, ['query' => ['keywords' => $keywords]]);
        return $list;
    }
}