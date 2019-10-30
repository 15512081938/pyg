<?php

namespace app\home\controller;

use app\common\model\Category;
use app\common\model\SpecValue;
use app\common\model\Goods as GoodsModel;
use app\home\logic\GoodsLogic;
use think\Collection;

class Goods extends Base
{
    public function index($id = 0)
    {
        $keywords = input('keywords');
        if (empty($keywords)) {
            //获取指定分类下商品列表
            if (!preg_match('/^\d+$/', $id)) {
                $this->error('参数错误');
            }
            //查询分类下的商品
            $list = GoodsModel::where('cate_id', $id)->order('id desc')->paginate(10);
            //查询分类名称
            $category_info = Category::find($id);
            $cate_name = $category_info['cate_name'];
        } else {
            try {
                //从ES中搜索
                $list = GoodsLogic::search();
                $cate_name = $keywords;
            } catch (\Exception $e) {
                $this->error('服务器异常');
            }
        }
        return view('index', ['list' => $list, 'cate_name' => $cate_name]);
    }

    public function detail($id)
    {
        $goods = GoodsModel::with('goods_images,spec_goods')->find($id);
        if (!$goods) {
            $this->error('商品不存在');
        }
        $goods = $goods->toArray();
        //dump($goods);die;
        if (!empty($goods['spec_goods'])) {
            $goods['goods_price'] = $goods['spec_goods'][0]['price'];
        }
        /*$goods['spec_goods'] = [
            ['id'=> 836, 'goods_id'=>71, 'value_ids'=>'28_32'],
            ['id'=> 837, 'goods_id'=>71, 'value_ids'=>'28_33'],
            ['id'=> 838, 'goods_id'=>71, 'value_ids'=>'29_32'],
            ['id'=> 839, 'goods_id'=>71, 'value_ids'=>'29_33'],
        ];*/
        //预期得到  [28,29,32,33], 用于查询规格值表pyg_spec_value表
        $value_ids = array_column($goods['spec_goods'], 'value_ids'); //['28_32', '28_33', '29_32', '29_33'] 适用于二位数组
        $value_ids = implode('_', $value_ids); //'28_32_28_33_29_32_29_33'
        $value_ids = explode('_', $value_ids); // [28,32,28,33,29,32,29,33]
        $value_ids = array_unique($value_ids); // [28,32,33,29]
//        排序不需要再赋值
//        asort/asort($value_ids);
//        dump($value_ids);die;
//        attr自定义属性 prop自己属性

        $spec_values = SpecValue::with('spec_bind')->where('id', 'in', $value_ids)->select();
        $spec_values = (new Collection($spec_values))->toArray();
        /*$spec_values = [
            ['id'=> 28, 'spec_id'=>23, 'spec_value'=>'黑色', 'spec_name' => '颜色'],
            ['id'=> 29, 'spec_id'=>23, 'spec_value'=>'金色', 'spec_name' => '颜色'],
            ['id'=> 32, 'spec_id'=>24, 'spec_value'=>'128G', 'spec_name' => '内存'],
            ['id'=> 33, 'spec_id'=>24, 'spec_value'=>'金色', 'spec_name' => '内存'],
        ];*/
        //预期转化的目标结构
        /*$specs = [
            '23' => ['id'=>23, 'spec_name' => '颜色', 'spec_values' => [
                ['id'=> 28, 'spec_id'=>23, 'spec_value'=>'黑色', 'spec_name' => '颜色'],
                ['id'=> 29, 'spec_id'=>23, 'spec_value'=>'金色', 'spec_name' => '颜色'],
                 ]
              ],
            '24' => ['id'=>24, 'spec_name' => '内存', 'spec_values' => [
                ['id'=> 32, 'spec_id'=>24, 'spec_value'=>'128G', 'spec_name' => '内存'],
                ['id'=> 33, 'spec_id'=>24, 'spec_value'=>'金色', 'spec_name' => '内存'],
                ]
              ],
        ];*/
        $specs = [];
        foreach ($spec_values as $v) {
            $specs[$v['spec_id']] = [
                'id' => $v['spec_id'],
                'spec_name' => $v['spec_name'],
                'spec_values' => [],
            ];
        }
        //得到结构
        /*$specs = [
           '23' => ['id'=>23, 'spec_name' => '颜色', 'spec_values' => [] ],
           '24' => ['id'=>24, 'spec_name' => '内存', 'spec_values' => [] ],
       ];*/
        foreach ($spec_values as $v) {
            $specs[$v['spec_id']]['spec_values'][] = $v;
        }
        //得到结构
        /*$specs = [
            '23' => ['id'=>23, 'spec_name' => '颜色', 'spec_values' => [
                ['id'=> 28, 'spec_id'=>23, 'spec_value'=>'黑色', 'spec_name' => '颜色'],
                ['id'=> 29, 'spec_id'=>23, 'spec_value'=>'金色', 'spec_name' => '颜色'],
                 ]
              ],
            '24' => ['id'=>24, 'spec_name' => '内存', 'spec_values' => [
                ['id'=> 32, 'spec_id'=>24, 'spec_value'=>'128G', 'spec_name' => '内存'],
                ['id'=> 33, 'spec_id'=>24, 'spec_value'=>'金色', 'spec_name' => '内存'],
                ]
              ],
        ];*/
        //规格值选中切换价格
        /*$value_ids_map = [
            '28_32' => ['id' => '836','price' => '10000'],
            '29_32' => ['id' => '837','price' => '12000'],
            '28_33' => ['id' => '838','price' => '11000'],
            '29_33' => ['id' => '839','price' => '13000'],
        ];*/
        $value_ids_map = [];
        foreach ($goods['spec_goods'] as $v) {
            $value_ids_map[$v['value_ids']] = [
                'id' => $v['id'],
                'price' => $v['price']
            ];
        }
        //得到结构
        /*$value_ids_map = [
            '28_32' => ['id' => '836','price' => '10000'],
            '29_32' => ['id' => '837','price' => '12000'],
            '28_33' => ['id' => '838','price' => '11000'],
            '29_33' => ['id' => '839','price' => '13000'],
        ];*/
        $value_ids_map = json_encode($value_ids_map, JSON_UNESCAPED_UNICODE);

        return view('detail', ['goods' => $goods, 'specs' => $specs, 'value_ids_map' => $value_ids_map]);
    }
}
