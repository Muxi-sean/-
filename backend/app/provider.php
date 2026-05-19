<?php
use app\service\EsTradeService;

return [
    'think\Request'          => 'app\\Request',
    'think\exception\Handle' => 'app\\ExceptionHandle',
    EsTradeService::class    => EsTradeService::class,
];
