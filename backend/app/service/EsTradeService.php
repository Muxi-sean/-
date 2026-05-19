<?php
declare(strict_types=1);

namespace app\service;

use Elastic\Elasticsearch\ClientBuilder;
use think\facade\Env;

class EsTradeService
{
    protected $client;
    protected $index = 'trade_data';

    public function __construct()
    {
        $host = Env::get('ES_HOST', 'http://localhost:9200');
        $username = Env::get('ES_USERNAME', '');
        $password = Env::get('ES_PASSWORD', '');

        $builder = ClientBuilder::create()->setHosts([$host]);

        if ($username && $password) {
            $builder->setBasicAuthentication($username, $password);
        }

        $this->client = $builder->build();
    }

    /**
     * 构建并执行查询
     */
    public function search(array $params): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $size = min(100, max(1, (int)($params['pageSize'] ?? 20)));
        $from = ($page - 1) * $size;

        $must = [];
        $filter = [];

        // 1. 地区（省市，可多选）
        if (!empty($params['regions'])) {
            $regionQueries = [];
            foreach ($params['regions'] as $region) {
                $province = $region['province'] ?? '';
                $cities = $region['cities'] ?? [];

                if (empty($province)) {
                    continue;
                }

                if (empty($cities)) {
                    // 只选省份，不限制城市
                    $regionQueries[] = ['term' => ['prov' => $province]];
                } else {
                    // 选了具体城市
                    $regionQueries[] = [
                        'bool' => [
                            'must' => [
                                ['term' => ['prov' => $province]],
                                ['terms' => ['city' => $cities]],
                            ]
                        ]
                    ];
                }
            }

            if (!empty($regionQueries)) {
                $must[] = ['bool' => ['should' => $regionQueries, 'minimum_should_match' => 1]];
            }
        }

        // 2. 项目名称（可输入多个关键词）
        if (!empty($params['projectKeywords'])) {
            $keywords = is_array($params['projectKeywords']) ? $params['projectKeywords'] : [$params['projectKeywords']];
            $projectQueries = [];
            foreach ($keywords as $kw) {
                $kw = trim($kw);
                if ($kw !== '') {
                    $projectQueries[] = ['match' => ['xmmc' => $kw]];
                }
            }
            if (!empty($projectQueries)) {
                $must[] = ['bool' => ['must' => $projectQueries]];
            }
        }

        // 3. 业务类型（可多选）
        if (!empty($params['ywlxIds'])) {
            $filter[] = ['terms' => ['ywlx_id' => array_map('intval', (array)$params['ywlxIds'])];
        }

        // 4. 标讯类型（可多选）
        if (!empty($params['xxlxIds'])) {
            $filter[] = ['terms' => ['xxlx_id' => array_map('intval', (array)$params['xxlxIds'])];
        }

        // 5. 日期范围
        if (!empty($params['dateRange'])) {
            $range = [];
            if (!empty($params['dateRange']['start'])) {
                $range['gte'] = $params['dateRange']['start'];
            }
            if (!empty($params['dateRange']['end'])) {
                $range['lte'] = $params['dateRange']['end'];
            }
            if (!empty($range)) {
                $filter[] = ['range' => ['proj_date' => $range]];
            }
        }

        // 6. 金额范围（可选择包含金额为空的项目）
        $moneyQuery = [];
        if (!empty($params['moneyRange'])) {
            $moneyRange = [];
            if (isset($params['moneyRange']['min']) && $params['moneyRange']['min'] !== '' && $params['moneyRange']['min'] !== null) {
                $moneyRange['gte'] = (float)$params['moneyRange']['min'];
            }
            if (isset($params['moneyRange']['max']) && $params['moneyRange']['max'] !== '' && $params['moneyRange']['max'] !== null) {
                $moneyRange['lte'] = (float)$params['moneyRange']['max'];
            }
            if (!empty($moneyRange)) {
                $moneyQuery[] = ['range' => ['money' => $moneyRange]];
            }
        }

        // 是否包含金额为空的
        if (!empty($params['includeEmptyMoney'])) {
            $moneyQuery[] = ['bool' => ['must_not' => ['exists' => ['field' => 'money']]]];
        }

        if (count($moneyQuery) > 1) {
            $must[] = ['bool' => ['should' => $moneyQuery, 'minimum_should_match' => 1]];
        } elseif (count($moneyQuery) === 1) {
            if (isset($moneyQuery[0]['range'])) {
                $filter[] = $moneyQuery[0];
            } else {
                $must[] = $moneyQuery[0];
            }
        }

        // 7. 企业名称+企业角色（多组OR关系，组内AND关系）
        if (!empty($params['companyGroups'])) {
            $companyGroupQueries = [];
            foreach ($params['companyGroups'] as $group) {
                $compName = trim($group['compName'] ?? '');
                $roleId = isset($group['roleId']) && $group['roleId'] !== '' ? (int)$group['roleId'] : null;

                if ($compName === '' && $roleId === null) {
                    continue;
                }

                $nestedMust = [];
                if ($compName !== '') {
                    $nestedMust[] = ['match' => ['comp_data.comp_name' => $compName]];
                }
                if ($roleId !== null) {
                    $nestedMust[] = ['term' => ['comp_data.role_id' => $roleId]];
                }

                if (!empty($nestedMust)) {
                    $companyGroupQueries[] = [
                        'nested' => [
                            'path' => 'comp_data',
                            'query' => [
                                'bool' => [
                                    'must' => $nestedMust
                                ]
                            ]
                        ]
                    ];
                }
            }

            if (!empty($companyGroupQueries)) {
                $must[] = ['bool' => ['should' => $companyGroupQueries, 'minimum_should_match' => 1]];
            }
        }

        // 其他字段过滤
        if (!empty($params['cgfsIds'])) {
            $filter[] = ['terms' => ['cgfs_id' => array_map('intval', (array)$params['cgfsIds'])];
        }
        if (!empty($params['cgplIds'])) {
            $filter[] = ['terms' => ['cgpl_id' => array_map('intval', (array)$params['cgplIds'])];
        }
        if (!empty($params['zblxIds'])) {
            $zblxIds = is_array($params['zblxIds']) ? $params['zblxIds'] : [$params['zblxIds']];
            $zblxTerms = [];
            foreach ($zblxIds as $id) {
                $zblxTerms[] = ['term' => ['zblx_ids.keyword' => (string)$id]];
            }
            if (!empty($zblxTerms)) {
                $must[] = ['bool' => ['should' => $zblxTerms, 'minimum_should_match' => 1]];
            }
        }
        if (!empty($params['gclxIds'])) {
            $gclxIds = is_array($params['gclxIds']) ? $params['gclxIds'] : [$params['gclxIds']];
            $gclxTerms = [];
            foreach ($gclxIds as $id) {
                $gclxTerms[] = ['term' => ['gclx_ids.keyword' => (string)$id]];
            }
            if (!empty($gclxTerms)) {
                $must[] = ['bool' => ['should' => $gclxTerms, 'minimum_should_match' => 1]];
            }
        }

        // 构建最终查询
        $query = [
            'bool' => [
                'must' => $must,
                'filter' => $filter,
            ]
        ];

        // 排序
        $sort = [];
        if (!empty($params['sort'])) {
            foreach ($params['sort'] as $sortItem) {
                $field = $sortItem['field'] ?? 'proj_date';
                $order = $sortItem['order'] ?? 'desc';
                $sort[] = [$field => $order];
            }
        } else {
            $sort[] = ['proj_date' => 'desc'];
        }

        $esParams = [
            'index' => $this->index,
            'body' => [
                'from' => $from,
                'size' => $size,
                'query' => $query,
                'sort' => $sort,
                'track_total_hits' => true,
            ]
        ];

        $response = $this->client->search($esParams);

        $hits = $response['hits']['hits'] ?? [];
        $total = $response['hits']['total']['value'] ?? 0;

        // 格式化返回数据
        $list = [];
        foreach ($hits as $hit) {
            $source = $hit['_source'];
            $list[] = $this->formatItem($source);
        }

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'pageSize' => $size,
            'totalPage' => (int)ceil($total / $size),
        ];
    }

    /**
     * 格式化单条数据
     */
    protected function formatItem(array $source): array
    {
        // 企业分组：业主(role_id=1)、代理(role_id=2)、投标企业(role_id=3)
        $companies = [
            'owner' => [],      // 业主
            'agent' => [],      // 代理
            'bidder' => [],     // 投标企业
        ];

        $compData = $source['comp_data'] ?? [];
        if (!empty($compData)) {
            foreach ($compData as $comp) {
                $name = $comp['comp_name'] ?? '';
                $roleId = (int)($comp['role_id'] ?? 0);

                if (empty($name)) continue;

                if ($roleId === 1) {
                    $companies['owner'][] = $name;
                } elseif ($roleId === 2) {
                    $companies['agent'][] = $name;
                } else {
                    $companies['bidder'][] = $name;
                }
            }
        }

        // 招标类型
        $zblxNames = [];
        $zblxIds = $source['zblx_ids'] ?? [];
        if (is_string($zblxIds)) {
            $zblxIds = explode(',', $zblxIds);
        }
        $zblxDict = [1 => '施工', 2 => '监理', 3 => '设计', 4 => '勘察', 5 => '总承包'];
        foreach ((array)$zblxIds as $id) {
            $id = (int)$id;
            if (isset($zblxDict[$id])) {
                $zblxNames[] = $zblxDict[$id];
            }
        }

        // 工程类型
        $gclxNames = [];
        $gclxIds = $source['gclx_ids'] ?? [];
        if (is_string($gclxIds)) {
            $gclxIds = explode(',', $gclxIds);
        }
        $gclxDict = [1 => '建筑工程', 2 => '市政工程', 3 => '公路工程', 4 => '水利工程', 5 => '电力工程'];
        foreach ((array)$gclxIds as $id) {
            $id = (int)$id;
            if (isset($gclxDict[$id])) {
                $gclxNames[] = $gclxDict[$id];
            }
        }

        // 业务类型
        $ywlxDict = [1 => '招标', 2 => '采购', 3 => '工程', 4 => '产权'];
        $ywlxName = $ywlxDict[(int)($source['ywlx_id'] ?? 0)] ?? '';

        // 采购方式
        $cgfsDict = [1 => '公开招标', 2 => '邀请招标', 3 => '竞争性谈判', 4 => '竞争性磋商', 5 => '单一来源', 6 => '询价'];
        $cgfsName = $cgfsDict[(int)($source['cgfs_id'] ?? 0)] ?? '';

        // 采购品类
        $cgplDict = [1 => '货物', 2 => '服务', 3 => '工程'];
        $cgplName = $cgplDict[(int)($source['cgpl_id'] ?? 0)] ?? '';

        return [
            'tradeBaseId' => $source['trade_base_id'] ?? 0,
            'projectName' => $source['xmmc'] ?? ($source['title'] ?? ''),
            'money' => $source['money'] ?? null,
            'date' => $source['proj_date'] ?? '',
            'region' => ($source['prov'] ?? '') . ($source['city'] ?? ''),
            'province' => $source['prov'] ?? '',
            'city' => $source['city'] ?? '',
            'ywlxName' => $ywlxName,
            'zblxNames' => $zblxNames,
            'gclxNames' => $gclxNames,
            'cgfsName' => $cgfsName,
            'cgplName' => $cgplName,
            'companies' => $companies,
        ];
    }
}
