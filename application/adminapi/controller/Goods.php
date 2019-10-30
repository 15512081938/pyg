<?php

namespace app\adminapi\controller;

use app\common\model\Type;
use app\common\model\GoodsImages;
use app\common\model\SpecGoods;
use app\common\model\Category;
use think\Db;
use think\Image;
use think\Request;
use app\common\model\Goods as GoodsModel;

class Goods extends BaseApi
{
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        $params = input();
        $where = [];
        if (!empty($params['keyword'])) {
            $where['keyword'] = ['like', "%{$params['keyword']}%"];
        }
        $size = isset($params['size']) ? (int)$params['size'] : 10;
        $res = GoodsModel::with('category_bind,brand_bind,type_bind')->where($where)->order('id desc')->paginate($size);
        $this->ok($res);
    }

    /**
     * 保存新建的资源
     *
     * @param  \think\Request $request
     * @return \think\Response
     */
    public function save(Request $request)
    {
        //接收参数
        $params = input();
//        $params['goods_desc'] = input('goods_desc', '', 'remove_xss');
        //参考数组结构
        //参数数组参考：(部分省略)
        /*$params = [
            'goods_name' => 'iphone X',
            'goods_price' => '8900',
            'goods_introduce' => 'iphone iphonex',
            'goods_logo' => '/uploads/goods/20190101/afdngrijskfsfa.jpg',
            'goods_images' => [
                '/uploads/goods/20190101/dfsssadsadsada.jpg',
                '/uploads/goods/20190101/adsafasdadsads.jpg',
                '/uploads/goods/20190101/dsafadsadsaasd.jpg',
            ],
            'cate_id' => '72',
            'brand_id' => '3',
            'type_id' => '16',
            'item' => [
                '18_21' => [
                    'value_ids'=>'18_21',
                    'value_names'=>'颜色：黑色；内存：64G',
                    'price'=>'8900.00',
                    'cost_price'=>'5000.00',
                    'store_count'=>100
                ],
                '18_22' => [
                    'value_ids'=>'18_22',
                    'value_names'=>'颜色：黑色；内存：128G',
                    'price'=>'9000.00',
                    'cost_price'=>'5000.00',
                    'store_count'=>50
                ]
            ],
            'attr' => [
                '7' => ['id'=>'7', 'attr_name'=>'毛重', 'attr_value'=>'150g'],
                '8' => ['id'=>'8', 'attr_name'=>'产地', 'attr_value'=>'国产'],
            ]
        ]*/
        //参数检测
        $validate = $this->validate($params, [
            'goods_name|商品名称' => 'require',
            'goods_price|商品价格' => 'require|float|gt:0',
            'goods_logo|logo图片' => 'require',
            'goods_images|相册图片' => 'require|array',
            'cate_id|商品分类' => 'require|number|gt:0',
            'brand_id|商品品牌' => 'require|number|gt:0',
            'type_id|商品模型' => 'require|number|gt:0',
            'item|规格商品' => 'require|array',
            'attr|商品属性值' => 'require|array'
        ], ['goods_price.float' => '商品价格必须是小数或者整数！']);
        if ($validate !== true) {
            $this->fail($validate);
        }
        //检测logo图片错误
        if (!file_exists('.' . $params['goods_logo'])) {
            $this->fail('logo图片不存在');
        }
        //开启事务
        Db::startTrans();
        try {
            //添加商品数据 (处理logo图片缩略图，属性值转化为json)
            Image::open('.' . $params['goods_logo'])->thumb(200, 240)->save('.' . $params['goods_logo']);
            $params['goods_attr'] = json_encode(array_values($params['attr']), JSON_UNESCAPED_UNICODE);  //array_values获取关联数组 值的
            $goods = GoodsModel::create($params, true);

            //添加商品相册
            $goods_images = [];
            foreach ($params['goods_images'] as $v) {
                //对每一张相册图片，生成两种不同尺寸的缩略图
                if (!file_exists('.' . $v)) continue;
                //生成缩略图 400*400 800*800 dirname文件夹路径，basename文件名 ，中间隔了个DS /
                $pics_big = dirname($v) . DS . 'thumb_800_' . basename($v);
                $pics_sma = dirname($v) . DS . 'thumb_400_' . basename($v);
                $image = Image::open('.' . $v);
                $image->thumb(800, 800)->save('.' . $pics_big);
                $image->thumb(400, 400)->save('.' . $pics_sma);
                $goods_images[] = [
                    'goods_id' => $goods['id'],
                    'pics_big' => $pics_big,
                    'pics_sma' => $pics_sma,
                ];
            }
            $goods_images_model = new GoodsImages();
            $goods_images_model->saveAll($goods_images);

            //添加sku
            //遍历$params['item'] 对每条数据增加goods_id字段，可以用于批量添加
            $spec_goods = [];
            foreach ($params['item'] as $v) {
                $v['goods_id'] = $goods['id']; //增加一个goods_id值 重新赋值 通过goods添加 获取的id
                $spec_goods[] = $v;
            }
            //批量添加数据
            $spec_goods_model = new SpecGoods();
            $spec_goods_model->allowField(true)->saveAll($spec_goods);

            //提交事务
            Db::commit();
            $res = GoodsModel::with('category_bind,brand_bind,type_bind')->find($goods['id']);
            $this->ok($res);
        } catch (\Exception $e) {
            Db::rollback();
            $this->fail($e->getMessage() . ';file:' . $e->getFile() . ';line:' . $e->getLine());
        }
    }

    /**
     * 显示指定的资源
     *
     * @param  int $id
     * @return \think\Response
     */
    public function read($id)
    {
        //with最多只能有一个嵌套。例如：specs.spec_values
        //查询商品信息 关联查询 分类、品牌、sku、相册
        $data = GoodsModel::with('category,brand,spec_goods,goods_images')->find($id);
        //查询商品所属模型Type， 关联查询 规格名称、规格值、属性
        $type = Type::with('specs,attrs,specs.spec_values')->find(['type_id' => $id]);
        //将查到的type数据，放到商品信息中
        $data['type'] = $type;
        //返回数据
        $this->ok($data);
    }

    /**
     * 显示编辑资源表单页.
     *
     * @param  int $id
     * @return \think\Response
     */
    public function edit($id)
    {
        //查询商品相关信息 分类、相册、sku
        $goods = GoodsModel::with('category,category.brands,goods_images,spec_goods')->find($id);
        //查询商品所属的type模型相关信息
        $goods['type'] = Type::with('attrs,specs,specs.spec_values')->find($goods['type_id']);

        //查询所有的type信息 给下拉列表展示的
        $type = Type::select();

        //查询分类信息  用于三级联动效果 ；；查询所有关联的的一级分类 二级分类 三级分类
        //$goods['category']['pid_path']  0_2_71   所属一级id $temp[1]   所属二级id $temp[2]
        $temp = explode('_', $goods['category']['pid_path']);
        $cate_one = Category::where('pid', $temp[0])->select();
        $cate_two = Category::where('pid', $temp[1])->select();
        $cate_three = Category::where('pid', $temp[2])->select();
        //返回数据
        $data = [
            'goods' => $goods,
            'type' => $type,
            'category' => [
                'cate_one' => $cate_one,
                'cate_two' => $cate_two,
                'cate_three' => $cate_three,
            ]
        ];
        $this->ok($data);

    }

    /**
     * 保存更新的资源
     *
     * @param  \think\Request $request
     * @param  int $id
     * @return \think\Response
     */
    public function update(Request $request, $id)
    {
        //接收数据
        $params = input();
//        $params['goods_desc'] = input('goods_desc', '', 'remove_xss');
        /*//参数数组参考：(部分省略)
        $params = [
            'goods_name' => 'iphone X',
            'goods_price' => '8900',
            'goods_desc' => 'iphone iphonex',
            'goods_logo' => '/uploads/goods/20190101/afdngrijskfsfa.jpg',
            'goods_images' => [
                '/uploads/goods/20190101/dfsssadsadsada.jpg',
                '/uploads/goods/20190101/adsafasdadsads.jpg',
                '/uploads/goods/20190101/dsafadsadsaasd.jpg',
            ],
            'cate_id' => '72',
            'brand_id' => '3',
            'type_id' => '16',
            'item' => [
                '18_21' => [
                    'value_ids'=>'18_21',
                    'value_names'=>'颜色：黑色；内存：64G',
                    'price'=>'8900.00',
                    'cost_price'=>'5000.00',
                    'store_count'=>100
                ],
                '18_22' => [
                    'value_ids'=>'18_22',
                    'value_names'=>'颜色：黑色；内存：128G',
                    'price'=>'9000.00',
                    'cost_price'=>'5000.00',
                    'store_count'=>50
                ]
            ],
            'attr' => [
                '7' => ['id'=>'7', 'attr_name'=>'毛重', 'attr_value'=>'150g'],
                '8' => ['id'=>'8', 'attr_name'=>'产地', 'attr_value'=>'国产'],
            ]
        ]*/
        //参数检测
        $validate = $this->validate($params, [
            'goods_name|商品名称' => 'require',
            'goods_price|商品价格' => 'require',
            'goods_logo|logo图片' => '',
            'goods_images|商品相册' => 'array',
            'item|规格' => 'array',
            'attr|属性' => 'array'
        ]);
        if ($validate !== true) {
            $this->fail($validate);
        }
        //开启事务
        Db::startTrans();
        try {
            //修改数据：
            //商品logo及缩略图
            if (!empty($params['goods_logo']) && file_exists('.' . $params['goods_logo'])) {
                $goods_logo = dirname($params['goods_logo']) . DS . 'thumb_' . basename($params['goods_logo']);
                Image::open('.' . $params['goods_logo'])->thumb(200, 240)->save('.' . $goods_logo);
                $params['goods_logo'] = $goods_logo;
            }
            //处理商品属性值字段
            if (isset($params['attr'])) {
                $params['goods_attr'] = json_encode($params['attr'], JSON_UNESCAPED_UNICODE);
            }
            GoodsModel::update($params, ['id' => $id], true);
            //商品相册图片及缩略图  继续上传新图片
            if (isset($params['goods_images'])) {
                $goods_images = [];
                foreach ($params['goods_images'] as $v) {
                    //判断图片是否存在
                    if (!file_exists('.' . $v)) continue;
                    //生成两种尺寸缩略图
                    $pics_big = dirname($v) . DS . 'thumb_800_' . basename($v);
                    $pics_sma = dirname($v) . DS . 'thumb_400_' . basename($v);
                    $image = Image::open('.' . $v);
                    $image->thumb(800, 800)->save('.' . $pics_big);
                    $image->thumb(400, 400)->save('.' . $pics_sma);
                    //组装一条数据
                    $goods_images[] = [
                        'goods_id' => $id,
                        'pics_big' => $pics_big,
                        'pics_sma' => $pics_sma
                    ];
                }
                //批量添加
                $goods_images_model = new GoodsImages();
                $goods_images_model->saveAll($goods_images);
            }

            if (isset($params['item']) && !empty($params['item'])) {
                //删除原规格商品SKU  goods_id 为条件
                SpecGoods::destroy(['goods_id' => $id]);
                //添加新规格商品SKU
                $spec_goods = [];
                foreach ($params['item'] as $v) {
                    //$v中的字段， 对应于 pyg_spec_goods表，缺少goods_id字段
                    $v['goods_id'] = $id;
                    $spec_goods[] = $v;
                }
                //批量添加
                $spec_goods_model = new SpecGoods();
                $spec_goods_model->allowField(true)->saveAll($spec_goods);
            }

            //提交事务
            Db::commit();
            //返回数据
            $info = GoodsModel::with('category_bind,brand_bind,type_bind')->find($id);
            $this->ok($info);

        } catch (\Exception $e) {
            //回滚事务
            Db::rollback();
            //错误提示
            $this->fail('错误信息:' . $e->getMessage() . ';文件：' . $e->getFile() . ';行数：' . $e->getLine());
        }
    }

    /**
     * 删除指定资源
     *
     * @param  int $id
     * @return \think\Response
     */
    public function delete($id)
    {
        //删除商品信息
        if(GoodsModel::where('id', $id)->value('is_on_sale') == 1){
            $this->fail('商品已上架，请先下架再删除');
        }
        GoodsModel::destroy($id);

        //清理图片以及本地清理
        GoodsImages::destroy(['goods_id' => $id]);
        $goods_images = GoodsImages::where('goods_id', $id)->select();
        $temp = [];
        foreach ($goods_images as $v) {
            $temp[] = $v['pics_big'];
            $temp[] = $v['pics_sma'];
        }
        foreach ($temp as $v) {
            if (file_exists('.' . $v)) {
                unlink('.' . $v);
            }
        }
        //返回结果
        $this->ok();
    }

    /**
     * 删除相册图片
     */
    public function delpics($id)
    {
        $info = GoodsImages::find($id);
        if(!$info){
            $this->fail('数据异常');
        }
        //删除相册图片
        GoodsImages::destroy($id);
        //从磁盘删除图片
        if(file_exists('.' . $info['pics_big'])){
            unlink('.' . $info['pics_big']);
        }
        if(file_exists('.' . $info['pics_sma'])){
            unlink('.' . $info['pics_sma']);
        }
        //返回数据
        $this->ok();
    }
}
