<?php

// Plan
$route = env('PACKAGE_ROUTE', '').'/plans/';
$controller = 'Increment\Plan\Http\PlanController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::post($route.'retrieve_summary', $controller."retrieveSummary");
Route::post($route.'apply_rewards', $controller."applyRewards");
Route::get($route.'test', $controller."test");

