<?php
// +------------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +------------------------------------------------------------------------
// | Copyright (c) 2006-2020 http://thinkphp.cn All rights reserved.
// +------------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +------------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +------------------------------------------------------------------------

require __DIR__ . '/../vendor/autoload.php';

// 定义应用目录
$appPath = __DIR__ . '/../app/';

// 加载框架基础文件
require __DIR__ . '/../vendor/topthink/framework/src/App.php';

// 执行HTTP应用并响应
$http = (new \think\App())->http;

$response = $http->run();

$response->send();

$http->end($response);
