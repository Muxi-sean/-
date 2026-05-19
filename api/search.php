<?php
// Trade search endpoint using cURL to Elasticsearch
$esHost = getenv('ES_HOST') ?: 'http://localhost:9200';
$esUser = getenv('ES_USERNAME') ?: '';
$esPass = getenv('ES_PASSWORD') ?: '';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = [];
}

$page = max(1, intval($input['page'] ?? 1));
$size = min(100, max(1, intval($input['pageSize'] ?? 20)));
$from = ($page - 1) * $size;

$must = [];
$filter = [];

// 1. 地区
if (!empty($input['regions'])) {
    $regionQueries = [];
    foreach ($input['regions'] as $region) {
        $province = $region['province'] ?? '';
        $cities = $region['cities'] ?? [];
        if (empty($province)) continue;
        if (empty($cities)) {
            $regionQueries[] = ['term' => ['prov' => $province]];
        } else {
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

// 2. 项目名称
if (!empty($input['projectKeywords'])) {
    $projectQueries = [];
    foreach ((array)$input['projectKeywords'] as $kw) {
        $kw = trim($kw);
        if ($kw !== '') {
            $projectQueries[] = ['match' => ['xmmc' => $kw]];
        }
    }
    if (!empty($projectQueries)) {
        $must[] = ['bool' => ['must' => $projectQueries]];
    }
}

// 3. 业务类型
if (!empty($input['ywlxIds'])) {
    $filter[] = ['terms' => ['ywlx_id' => array_map('intval', (array)$input['ywlxIds'])]];
}

// 4. 标讯类型
if (!empty($input['xxlxIds'])) {
    $filter[] = ['terms' => ['xxlx_id' => array_map('intval', (array)$input['xxlxIds'])]];
}

// 5. 日期范围
if (!empty($input['dateRange'])) {
    $range = [];
    if (!empty($input['dateRange']['start'])) {
        $range['gte'] = $input['dateRange']['start'];
    }
    if (!empty($input['dateRange']['end'])) {
        $range['lte'] = $input['dateRange']['end'];
    }
    if (!empty($range)) {
        $filter[] = ['range' => ['proj_date' => $range]];
    }
}

// 6. 金额范围
$moneyQuery = [];
if (!empty($input['moneyRange'])) {
    $moneyRange = [];
    if (isset($input['moneyRange']['min']) && $input['moneyRange']['min'] !== '' && $input['moneyRange']['min'] !== null) {
        $moneyRange['gte'] = (float)$input['moneyRange']['min'];
    }
    if (isset($input['moneyRange']['max']) && $input['moneyRange']['max'] !== '' && $input['moneyRange']['max'] !== null) {
        $moneyRange['lte'] = (float)$input['moneyRange']['max'];
    }
    if (!empty($moneyRange)) {
        $moneyQuery[] = ['range' => ['money' => $moneyRange]];
    }
}
if (!empty($input['includeEmptyMoney'])) {
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

// 7. 企业条件组
if (!empty($input['companyGroups'])) {
    $companyGroupQueries = [];
    foreach ($input['companyGroups'] as $group) {
        $compName = trim($group['compName'] ?? '');
        $roleId = isset($group['roleId']) && $group['roleId'] !== '' ? (int)$group['roleId'] : null;
        if ($compName === '' && $roleId === null) continue;

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
                    'query' => ['bool' => ['must' => $nestedMust]]
                ]
            ];
        }
    }
    if (!empty($companyGroupQueries)) {
        $must[] = ['bool' => ['should' => $companyGroupQueries, 'minimum_should_match' => 1]];
    }
}

// 其他过滤
if (!empty($input['cgfsIds'])) {
    $filter[] = ['terms' => ['cgfs_id' => array_map('intval', (array)$input['cgfsIds'])]];
}
if (!empty($input['cgplIds'])) {
    $filter[] = ['terms' => ['cgpl_id' => array_map('intval', (array)$input['cgplIds'])]];
}
if (!empty($input['zblxIds'])) {
    $zblxTerms = [];
    foreach ((array)$input['zblxIds'] as $id) {
        $zblxTerms[] = ['term' => ['zblx_ids.keyword' => (string)$id]];
    }
    if (!empty($zblxTerms)) {
        $must[] = ['bool' => ['should' => $zblxTerms, 'minimum_should_match' => 1]];
    }
}
if (!empty($input['gclxIds'])) {
    $gclxTerms = [];
    foreach ((array)$input['gclxIds'] as $id) {
        $gclxTerms[] = ['term' => ['gclx_ids.keyword' => (string)$id]];
    }
    if (!empty($gclxTerms)) {
        $must[] = ['bool' => ['should' => $gclxTerms, 'minimum_should_match' => 1]];
    }
}

// 构建查询
$query = [
    'bool' => [
        'must' => $must,
        'filter' => $filter,
    ]
];

// 排序
$sort = [];
if (!empty($input['sort'])) {
    foreach ($input['sort'] as $sortItem) {
        $field = $sortItem['field'] ?? 'proj_date';
        $order = $sortItem['order'] ?? 'desc';
        $sort[] = [$field => $order];
    }
} else {
    $sort[] = ['proj_date' => 'desc'];
}

$esBody = [
    'from' => $from,
    'size' => $size,
    'query' => $query,
    'sort' => $sort,
    'track_total_hits' => true,
];

// 调用 ES
$ch = curl_init($esHost . '/trade_data/_search');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($esBody));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
if ($esUser && $esPass) {
    curl_setopt($ch, CURLOPT_USERPWD, $esUser . ':' . $esPass);
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || $response === false) {
    http_response_code(500);
    echo json_encode(['code' => 500, 'msg' => 'ES查询失败: ' . ($response ?: '无响应'), 'data' => null]);
    exit;
}

$esResult = json_decode($response, true);
$hits = $esResult['hits']['hits'] ?? [];
$total = $esResult['hits']['total']['value'] ?? 0;

// 格式化结果
$list = [];
foreach ($hits as $hit) {
    $source = $hit['_source'];
    $list[] = formatItem($source);
}

$result = [
    'list' => $list,
    'total' => $total,
    'page' => $page,
    'pageSize' => $size,
    'totalPage' => (int)ceil($total / $size),
];

echo json_encode(['code' => 0, 'msg' => 'success', 'data' => $result]);

function formatItem($source) {
    $companies = ['owner' => [], 'agent' => [], 'bidder' => []];
    $compData = $source['comp_data'] ?? [];
    if (!empty($compData) && is_array($compData)) {
        foreach ($compData as $comp) {
            $name = $comp['comp_name'] ?? '';
            $roleId = (int)($comp['role_id'] ?? 0);
            if (empty($name)) continue;
            if ($roleId === 1) $companies['owner'][] = $name;
            elseif ($roleId === 2) $companies['agent'][] = $name;
            else $companies['bidder'][] = $name;
        }
    }

    $zblxNames = [];
    $zblxIds = $source['zblx_ids'] ?? [];
    if (is_string($zblxIds)) $zblxIds = explode(',', $zblxIds);
    $zblxDict = [1 => '施工', 2 => '监理', 3 => '设计', 4 => '勘察', 5 => '总承包'];
    foreach ((array)$zblxIds as $id) {
        $id = (int)$id;
        if (isset($zblxDict[$id])) $zblxNames[] = $zblxDict[$id];
    }

    $gclxNames = [];
    $gclxIds = $source['gclx_ids'] ?? [];
    if (is_string($gclxIds)) $gclxIds = explode(',', $gclxIds);
    $gclxDict = [1 => '建筑工程', 2 => '市政工程', 3 => '公路工程', 4 => '水利工程', 5 => '电力工程'];
    foreach ((array)$gclxIds as $id) {
        $id = (int)$id;
        if (isset($gclxDict[$id])) $gclxNames[] = $gclxDict[$id];
    }

    $ywlxDict = [1 => '招标', 2 => '采购', 3 => '工程', 4 => '产权'];
    $cgfsDict = [1 => '公开招标', 2 => '邀请招标', 3 => '竞争性谈判', 4 => '竞争性磋商', 5 => '单一来源', 6 => '询价'];
    $cgplDict = [1 => '货物', 2 => '服务', 3 => '工程'];

    return [
        'tradeBaseId' => $source['trade_base_id'] ?? 0,
        'projectName' => $source['xmmc'] ?? ($source['title'] ?? ''),
        'money' => $source['money'] ?? null,
        'date' => $source['proj_date'] ?? '',
        'region' => ($source['prov'] ?? '') . ($source['city'] ?? ''),
        'province' => $source['prov'] ?? '',
        'city' => $source['city'] ?? '',
        'ywlxName' => $ywlxDict[(int)($source['ywlx_id'] ?? 0)] ?? '',
        'zblxNames' => $zblxNames,
        'gclxNames' => $gclxNames,
        'cgfsName' => $cgfsDict[(int)($source['cgfs_id'] ?? 0)] ?? '',
        'cgplName' => $cgplDict[(int)($source['cgpl_id'] ?? 0)] ?? '',
        'companies' => $companies,
    ];
}
