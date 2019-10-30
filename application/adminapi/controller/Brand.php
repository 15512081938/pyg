<?php

namespace app\adminapi\controller;

use think\Request;
use think\Image;
use app\common\model\Brand as BrandModel;
use app\common\model\Goods as GoodsModel;

class Brand extends BaseApi
{
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        $params = input();
        if(isset($params['cate_id']) && !empty($params['cate_id'])){
            $list = BrandModel::where('cate_id',$params['cate_id'])->select();
        }
        else{
            $where = [];
            if(isset($params['keyword']) && !empty($params['keyword'])){
                $where['t1.name']=['like',"%{$params['keyword']}%"];
            }
            $list = BrandModel::alias('t1')
                ->join('pyg_category t2','t1.cate_id=t2.id','left')
                ->field('t1.id,t1.name,t1.logo,t1.desc,t1.sort,t1.is_hot, t2.cate_name')
                ->where($where)
                ->paginate(10);
        }
        $this->ok($list);
    }

    /**
     * 保存新建的资源
     *
     * @param  \think\Request  $request
     * @return \think\Response
     */
    public function save(Request $request)
    {
        $params = input();
        $validate = $this->validate($params, [
            'name|品牌名称' => 'require',
            'cate_id|所属分类' => 'require|integer|gt:0',
            'is_hot|是否热门' => 'require|in:0,1',
            'sort|排序' => 'require|integer|between:0,9999',
        ]);
        if($validate !== true){
            $this->fail($validate);
        }
        if(isset($params['logo']) && file_exists('.'.$params['logo'])){
            \think\Image::open('.'.$params['logo'])->thumb(100,50)->save('.'.$params['logo']);
        }
        $res = BrandModel::create($params,true);
        $list = BrandModel::find($res['id']);
        $this->ok($list);
    }

    /**
     * 显示指定的资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function read($id)
    {
        $list = BrandModel::find($id);
        $this->ok($list);
    }

    /**
     * 保存更新的资源
     *
     * @param  \think\Request  $request
     * @param  int  $id
     * @return \think\Response
     */
    public function update(Request $request, $id)
    {
        $params = input();
        $validate = $this->validate($params, [
            'name|品牌名称' => 'require',
            'cate_id|所属分类' => 'require|integer|gt:0',
            'is_hot|是否热门' => 'require|in:0,1',
            'sort|排序' => 'require|integer|between:0,9999',
        ]);
        if($validate !== true){
            $this->fail($validate);
        }
        if(isset($params['logo']) && file_exists('.'.$params['logo'])){
            Image::open('.'.$params['logo'])->thumb(100,50)->save('.'.$params['logo']);
        }else{
            unset($params['logo']);
        }
        BrandModel::update($params,['id'=>$id],true);
        $list = BrandModel::find($id);
        $this->ok($list);
    }

    /**
     * 删除指定资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function delete($id)
    {
        $total = GoodsModel::where('brand_id', $id)->count('id');
        if($total){
            $this->fail('品牌下有商品，不能删除');
        }
        BrandModel::destroy($id);
        $this->ok();
    }
}
