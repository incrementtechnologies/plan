<?php

// Plan
$route = env('PACKAGE_ROUTE', '').'/invitations/';
$controller = 'Increment\Plan\Http\InvitationController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::post($route.'apply', $controller."apply");
Route::get($route.'test', $controller."test");
