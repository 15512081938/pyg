<?php

namespace app\adminapi\controller;

use think\Request;
use think\Image;
use app\common\model\Category as CategoryModel;
use app\common\model\Brand;
use app\common\model\Goods;

class Category extends BaseApi
{
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        $params = input();
//        $params['cate_name'] = input('cate_name', '', 'remove_xss');
        //检测参数 pid
        $validate = $this->validate($params, [
            'pid' => 'number|egt:0'
        ]);
        if ($validate !== true) {
            $this->fail($validate);
        }
        //设置条件 pid有值，付给条件的pid方便查询
        $where = [];
        if (isset($params['pid'])) {
            $where['pid'] = $params['pid'];
        }
        //查询数据,为了获取数据
        $list = CategoryModel::field('id,cate_name,pid,pid_path_name,level,image_url,is_hot')->where($where)->select();
        //将数据转换为数组，toArray是Collection的方法
        $list = (new \think\Collection($list))->toArray();
        //转换成无限极分类，前提 type类型有值 并且 值为list ，为了前台展示
        if (!(isset($params['type']) && $params['type'] == 'list')) {
            $list = get_cate_list($list);
        }
        $this->ok($list);
    }

    /**
     * 保存新建的资源
     *
     * @param  \think\Request $request
     * @return \think\Response
     */
    public function save(Request $request)
    {
        $params = input();
        //检测必要参数
        $validate = $this->validate($params, [
            'cate_name' => 'require',
            'pid' => 'require|number|egt:0',
            'sort' => 'require|integer',
            'is_show' => 'require|in:1,0',
            'is_hot' => 'require|in:1,0'
        ]);
        if ($validate !== true) {
            $this->fail($validate);
        }
        //设置pid参数值，level,pid_path,pid_path_name
        if ($params['pid'] == 0) {
            $params['level'] = 0;
            $params['pid_path'] = 0;
            $params['pid_path_name'] = '';
        } else {
            //查询父级信息
            $parent = CategoryModel::find($params['pid']); //注意categorymodel 是common里面的
//            if(!$parent){
//                $this->fail('数据异常');
//            }   //防止恶意篡改
            $params['level'] = $parent['level'] + 1;
            $params['pid_path'] = $parent['pid_path'] . '_' . $parent['id'];
            $params['pid_path_name'] = $parent['pid_path_name'] ?
                $parent['pid_path_name'] . '_' . $parent['cate_name'] :
                $parent['cate_name'];
        }
        //logo图片生成缩略图
        if (isset($params['logo']) && file_exists('.' + $params['logo'])) {
            Image::open('.' . $params['logo'])->thumb(30, 30)->save('.' . $params['logo']);
            $params['image_url'] = $params['logo'];
        }
        //添加数据
        $res = CategoryModel::create($params, true);
        //返回添加的数据
        $list = CategoryModel::field('id,cate_name,pid,pid_path_name,level,is_show,is_hot,image_url')->find($res['id']);
        $this->ok($list);
    }

    /**
     * 显示指定的资源
     *
     * @param  int $id
     * @return \think\Response
     */
    public function read($id)
    {
        //查询一条数据
        $info = CategoryModel::field('id,cate_name,pid,pid_path_name,level,image_url,is_hot,is_show')->find($id);
        //返回数据
        $this->ok($info);
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
        //检测必要参数
        $validate = $this->validate($params, [
            'cate_name' => 'require',
            'pid' => 'require|number|egt:0',
            'sort' => 'require|between:0,999',
            'is_show' => 'require|in:1,0',
            'is_hot' => 'require|in:1,0'
        ]);
        if ($validate !== true) {
            $this->fail($validate);
        }
        //设置pid参数值，level,pid_path,pid_path_name
        if ($params['pid'] == 0) {
            $params['level'] = 0;
            $params['pid_path'] = 0;
            $params['pid_path_name'] = '';
        } else {
            //查询父级信息
            $parent = CategoryModel::find($params['pid']); //注意categorymodel 是common里面的
//            if(!$parent){
//                $this->fail('数据异常');
//            }   防止恶意篡改
            $params['level'] = $parent['level'] + 1;
            $params['pid_path'] = $parent['pid_path'] . '_' . $parent['id'];
            $params['pid_path_name'] = $parent['pid_path_name'] ?
                $parent['pid_path_name'] . '_' . $parent['cate_name'] :
                $parent['cate_name'];
        }
        $info = CategoryModel::find($id);
        if ($info['level'] < $params['level']) {
            $this->fail('不能降级');
        }
        //logo图片生成缩略图
        if (isset($params['logo']) && file_exists('.' + $params['logo'])) {
            Image::open('.' . $params['logo'])->thumb(30, 30)->save('.' . $params['logo']);
            $params['image_url'] = $params['logo'];
        }
        //添加数据
        $res = CategoryModel::update($params, ['id' => $id], true);
        //返回添加的数据
        $list = CategoryModel::field('id,cate_name,pid,pid_path_name,level,is_show,is_hot,image_url')->find($res['id']);
        $this->ok($list);
    }

    /**
     * 删除指定资源
     *
     * @param  int $id
     * @return \think\Response
     */
    public function delete($id)
    {
        //分类下有子分类，不能删除，[当前id是别人的父id]
        $total = CategoryModel::where('pid', $id)->count('id');
        if($total){
            $this->fail('分类下有子分类，不能删除');
        }
        //分类下有品牌，不能删除
        $total = Brand::where('cate_id', $id)->count('id');
        if($total){
            $this->fail('分类下有品牌，不能删除');
        }
        //分类下有商品，不能删除
        $total = Goods::where('cate_id', $id)->count('id');
        if($total){
            $this->fail('分类下有商品，不能删除');
        }
        //删除操作
        $res = CategoryModel::destroy($id);
        $this->ok();
    }
}
