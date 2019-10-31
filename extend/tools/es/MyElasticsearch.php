<?php
namespace tools\es;

use Elasticsearch\ClientBuilder;

class MyElasticsearch
{
    //ES客户端链接
    private $client;

    /**
     * 构造函数
     * MyElasticsearch constructor.
     */
    public function __construct()
    {
        $params = array(
            '127.0.0.1:9200'
        );
        $this->client = ClientBuilder::create()->setHosts($params)->build();
    }

    /**
     * 判断索引是否存在
     * @param string $index_name
     * @return bool|mixed|string
     */
    public function exists_index($index_name = 'test_ik')
    {
        $params = [
            'index' => $index_name
        ];

        try {
            return $this->client->indices()->exists($params);
        } catch (\Elasticsearch\Common\Exceptions\BadRequest400Exception $e) {
            $msg = $e->getMessage();
            $msg = json_decode($msg,true);
            return $msg;
        }
    }

    /**
     * 创建索引
     * @param string $index_name
     * @return array|mixed|string
     */
    public function create_index($index_name = 'test_ik') { // 只能创建一次
        $params = [
            'index' => $index_name,
            'body' => [
                'settings' => [
                    'number_of_shards' => 5,
                    'number_of_replicas' => 0
                ]
            ]
        ];

        try {
            return $this->client->indices()->create($params);
        } catch (\Elasticsearch\Common\Exceptions\BadRequest400Exception $e) {
            $msg = $e->getMessage();
            $msg = json_decode($msg,true);
            return $msg;
        }
    }

    /**
     * 删除索引
     * @param string $index_name
     * @return array
     */
    public function delete_index($index_name = 'test_ik') {
        $params = ['index' => $index_name];
        $response = $this->client->indices()->delete($params);
        return $response;
    }

    /**
     * 添加文档
     * @param $id
     * @param $doc ['id'=>100, 'title'=>'phone']
     * @param string $index_name
     * @param string $type_name
     * @return array
     */
    public function add_doc($id,$doc,$index_name = 'test_ik',$type_name = 'goods') {
        $params = [
            'index' => $index_name,
            'type' => $type_name,
            'id' => $id,
            'body' => $doc
        ];

        $response = $this->client->index($params);
        return $response;
    }

    /**
     * 判断文档存在
     * @param int $id
     * @param string $index_name
     * @param string $type_name
     * @return array|bool
     */
    public function exists_doc($id = 1,$index_name = 'test_ik',$type_name = 'goods') {
        $params = [
            'index' => $index_name,
            'type' => $type_name,
            'id' => $id
        ];

        $response = $this->client->exists($params);
        return $response;
    }

    /**
     * 获取文档
     * @param int $id
     * @param string $index_name
     * @param string $type_name
     * @return array
     */
    public function get_doc($id = 1,$index_name = 'test_ik',$type_name = 'goods') {
        $params = [
            'index' => $index_name,
            'type' => $type_name,
            'id' => $id
        ];

        $response = $this->client->get($params);
        return $response;
    }

    /**
     * 更新文档
     * @param int $id
     * @param string $index_name
     * @param string $type_name
     * @param array $body ['doc' => ['title' => '苹果手机iPhoneX']]
     * @return array
     */
    public function update_doc($id = 1,$index_name = 'test_ik',$type_name = 'goods', $body=[]) {
        // 可以灵活添加新字段,最好不要乱添加
        $params = [
            'index' => $index_name,
            'type' => $type_name,
            'id' => $id,
            'body' => $body
        ];

        $response = $this->client->update($params);
        return $response;
    }

    /**
     * 删除文档
     * @param int $id
     * @param string $index_name
     * @param string $type_name
     * @return array
     */
    public function delete_doc($id = 1,$index_name = 'test_ik',$type_name = 'goods') {
        $params = [
            'index' => $index_name,
            'type' => $type_name,
            'id' => $id
        ];

        $response = $this->client->delete($params);
        return $response;
    }

    /**
     * 搜索文档 (分页，排序，权重，过滤)
     * @param string $index_name
     * @param string $type_name
     * @param array $body
     * $body = [
    'query' => [
    'bool' => [
    'should' => [
    [
    'match' => [
    'cate_name' => [
    'query' => $keywords,
    'boost' => 4, // 权重大
    ]
    ]
    ],
    [
    'match' => [
    'goods_name' => [
    'query' => $keywords,
    'boost' => 3,
    ]
    ]
    ],
    [
    'match' => [
    'goods_desc' => [
    'query' => $keywords,
    'boost' => 2,
    ]
    ]
    ]
    ],
    ],
    ],
    'sort' => ['id'=>['order'=>'desc']],
    'from' => $from,
    'size' => $size
    ];
     * @return array
     */
    public function search_doc($index_name = "test_ik",$type_name = "goods",$body=[]) {
        $params = [
            'index' => $index_name,
            'type' => $type_name,
            'body' => $body
        ];

        $results = $this->client->search($params);
        return $results;
    }

}