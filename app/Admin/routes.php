<?php

use Illuminate\Routing\Router;

Admin::registerAuthRoutes();

Route::group([
    'prefix'     => config('admin.route.prefix'),
    'namespace'  => config('admin.route.namespace'),
    'middleware' => config('admin.route.middleware'),
], function (Router $router) {

    $router->get('/', 'HomeController@index');
    $router->get('users', 'UsersController@index');

    //商品
    $router->get('products', 'ProductsController@index');
    $router->get('products/create', 'ProductsController@create');
    $router->post('products', 'ProductsController@store');
    $router->get('products/{id}/edit', 'ProductsController@edit');
    $router->put('products/{id}', 'ProductsController@update');

    //订单
    $router->get('orders', 'OrderController@index')->name('admin.orders.index');
    $router->get('orders/{order}', 'OrderController@show')->name('admin.orders.show');
    $router->post('orders/{order}/ship', 'OrderController@ship')->name('admin.orders.ship');
    $router->post('orders/{order}/refund', 'OrderController@handleRefund')->name('admin.orders.handle_refund');

    //优惠券
    $router->get('coupon_codes', 'CouponCodesController@index');
});
