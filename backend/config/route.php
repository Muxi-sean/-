<?php
use think\facade\Route;

Route::post('api/trade/search', 'Trade/search');
Route::get('api/trade/dicts', 'Trade/dicts');
Route::get('api/health', function() {
    return json(['status' => 'ok']);
});
