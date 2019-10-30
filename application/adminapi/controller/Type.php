<?php

namespace app\adminapi\controller;

use think\Db;
use think\Request;
use app\common\model\Attribute;
use app\common\model\Goods;
use app\common\model\Spec;
use app\common\model\SpecValue;
use app\common\model\Type as TypeModel;

class Type extends BaseApi
{
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        $res = TypeModel::select();
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
        /*$params结构参考：$params = [
            'type_name' => '手机123',
            'spec' => [
                ['name' => '颜色', 'sort'=>'50', 'value' => ['金色', '白色', '黑色']],
                ['name' => '内存', 'sort'=>'60', 'value' => ['64G', '128G', '256G']],
            ],
            'attr' => [
                ['name' => '产地', 'sort'=>'50', 'value' => ['国产', '进口']],
                ['name' => '重量', 'sort'=>'60', 'value' => []],
            ]
        ];*/
        //参数检测
        $validate = $this->validate($params, [
            'type_name|模型名称' => 'require',
            'spec|规格' => 'require|array',
            'attr|属性' => 'require|array',
        ]);
        if ($validate !== true) {
            $this->fail($validate);
        }
        //处理数据 4个添加操作
        //开启事务
        Db::startTrans();
        try {
            //添加商品模型信息
            $type = TypeModel::create(['type_name' => $params['type_name']], true);

            //添加模型下的商品规格信息
            //先对$params['spec'] 数据进行处理
            foreach ($params['spec'] as $k => $v) {
                //$k是下标，$v是值
                if (empty($v['name']) || !is_array($v['value']) || empty($v['value'])) {
                    //如果规格名称为空 或者 规格名称的规格值不是数组 或者 规格值为空，将当前整个规格名称信息删除，跳出本次遍历。
                    unset($params['spec'][$k]);
                    continue;
                }
                foreach ($v['value'] as $key => $value) {
                    //将规格值中的空值删除
                    if (empty($value)) {
                        unset($params['spec'][$k]['value'][$key]);
                    }
                }
            }
            //添加商品规格名称数据
            $spec_data = [];
            foreach ($params['spec'] as $k => $v) {
                //$type['id']  $v['name']  $v['sort']
                $spec_data[] = [
                    'type_id' => $type['id'],  //获取type_id 通过刚才添加的type模型
                    'spec_name' => $v['name'],
                    'sort' => $v['sort']
                ];
            }
            //批量添加规格名称
            $spec_model = new Spec();
            $spec_res = $spec_model->saveAll($spec_data);

            /*$spec_res结构参考：$spec_res = [
                0=>['id'=>100, 'spec_name' => '颜色'],
                1=>['id'=>101, 'spec_name' => '内存']
            ];*/
            /*$params['spec']结构参考：'spec' => [
                0=>['name' => '颜色', 'sort'=>'50', 'value' => ['金色', '白色', '黑色']],
                1=>['name' => '内存', 'sort'=>'60', 'value' => ['64G', '128G', '256G']],
            ],*/
            //添加规格值数据
            $spec_value_data = [];
            foreach ($params['spec'] as $k => $v) {
                //$v是一个规格名称数组
                foreach ($v['value'] as $value) {
                    $spec_value_data[] = [
                        'spec_id' => $spec_res[$k]['id'],
                        'type_id' => $type['id'],
                        'spec_value' => $value
                    ];
                }
            }
            $spec_value_model = new SpecValue();
            $spec_value_model->saveAll($spec_value_data);

            //处理属性参数
            foreach ($params['attr'] as $k => $v) {
                if (empty($v['name'])) {
                    //如果属性名称为空，则删除整条属性信息
                    unset($params['attr'][$k]);
                    continue;
                }
                if (!is_array($v['value'])) {
                    //如果属性可选值 不是数组，则设置为空数组
                    $v['value'] = [];
                }
                foreach ($v['value'] as $key => $value) {
                    //去除空的可选值
                    if (empty($value)) {
                        unset($params['attr'][$k]['value'][$key]);
                    }
                }
            }

            //添加属性信息到属性表
            $attr_data = [];
            foreach ($params['attr'] as $k => $v) {
                $attr_data[] = [
                    'type_id' => $type['id'],
                    'attr_name' => $v['name'],
                    'sort' => $v['sort'],
                    'attr_values' => implode(',', $v['value']),
                ];
            }
            $attr_model = new Attribute();
            $attr_model->saveAll($attr_data);

            //提交事务
            Db::commit();
            $this->ok($type);
        } catch (\Exception $e) {
            //回滚事务
            Db::rollback();
            $this->fail($e->getMessage());
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
        //联表查询
        $res = TypeModel::with('specs,attrs,specs.spec_values')->find($id);
        //处理结果
        $res = $res ? $res->toArray() : [];
        $this->ok($res);

//        foreach ($res['attrs']->toArray() as $k =>&$v) {
//            $res['attrs'][$k]['attr_values'] = explode(',',$v['attr_values']);
//        }
//        unset($v);
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
        $params = input();
        $validate = $this->validate($params, [
            'type_name|模型名称' => 'require',
            'spec|规格' => 'require|array',
            'attr|属性' => 'require|array'
        ]);
        if ($validate !== true) {
            $this->fail($validate);
        }

        Db::startTrans();
        try {
            //类型名称的修改
            $type = TypeModel::update(['type_name' => $params['type_name'], 'id' => $id], ['id' => $id], true);

            //对规格数据进行处理（去除无效的数据，比如空的值）
            foreach ($params['spec'] as $k => $v) {
                if (empty($v['name']) || !is_array($v['value']) || empty($params['spec'][$k]['value'])) {
                    //如果规格名称为空，规格值不是数组,规格值数组是空数组,删除整条数据
                    unset($params['spec'][$k]);
                    continue;
                }
                foreach ($v['value'] as $key => $value) {
                    if (empty($value)) {
                        //去除 规格值数组中的空值
                        unset($params['spec'][$k]['value'][$key]);
                    }
                }
            }
            //修改规格名称信息：先删除原来的数据，再新增新的数据
            Spec::destroy(['type_id' => $id]);
            //组装数据
            $spec_data = [];
            foreach ($params['spec'] as $v) {
                $spec_data[] = [
                    'spec_name' => $v['name'],
                    'sort' => $v['sort'],
                    'type_id' => $id
                ];
            }
            $spec_model = new Spec();
            $spec_res = $spec_model->saveAll($spec_data);

            /*$spec_res结构参考：$spec_res = [
                ['id' => 101, 'spec_name' => '颜色'],
                ['id' => 102, 'spec_name' => '内存'],
            ];*/
            //修改规格值信息：先删除原来的数据，再新增新的数据
            SpecValue::destroy(['type_id' => $id]);
            //组装数据
            $spec_value_data = [];
            foreach ($params['spec'] as $k => $v) {
                //内层遍历 规格值数组
                foreach ($v['value'] as $value) {
                    $spec_value_data[] = [
                        'type_id' => $id,
                        'spec_id' => $spec_res[$k]['id'],
                        'spec_value' => $value
                    ];
                }
            }
            $spec_value_model = new SpecValue();
            $spec_value_model->saveAll($spec_value_data);

            //处理属性信息（去除空的属性值）
            foreach ($params['attr'] as $k => $v) {
                //如果属性名称为空，去除整条数据
                if (empty($v['name'])) {
                    unset($params['attr'][$k]);
                    continue;
                }
                //如果属性值数组不是数组，设置为空数组
                if (!is_array($v['value'])) {
                    $params['attr'][$k]['value'] = [];
                    continue;
                }
                //如果属性值为空的值，去除空的值
                foreach ($v['value'] as $key => $value) {
                    if (empty($value)) {
                        unset($params['attr'][$k]['value'][$key]);
                        continue;
                    }
                }
            }

            //属性的修改：先删除原来的数据，再添加新的数据
            Attribute::destroy(['type_id' => $id]);
            $attr_data = [];
            foreach ($params['attr'] as $k => $v) {
                $attr_data[] = [
                    'type_id' => $id,   //$type['id']
                    'attr_name' => $v['name'],
                    'sort' => $v['sort'],
                    'attr_values' => implode(',', $v['value']),
                ];
            }
            $attr_model = new Attribute();
            $attr_model->saveAll($attr_data);

            Db::commit();
            $this->ok($type);
        } catch (\Exception $e) {
            Db::rollback();
            $this->fail($e->getMessage());
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
        //开启事务
        Db::startTrans();
        try {
            $total = Goods::where('type_id', $id)->count('id');
            if ($total) {
                //$this->fail('商品模型下有商品，不能删除');
                throw new \Exception('商品模型下有商品，不能删除');
            }

            Attribute::destroy(['type_id' => $id]);
            Spec::destroy(['type_id' => $id]);
            SpecValue::destroy(['type_id' => $id]);
            TypeModel::destroy($id);
            //\app\common\model\Spec::where('type_id',$id)->delete();

            Db::commit();    //成功
            $this->ok();
        } catch (\Exception $e) {
            Db::rollback();  //失败
            $this->fail($e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine());
        }
    }
}
