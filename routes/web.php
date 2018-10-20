<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::redirect('/', '/products')->name('root');
Route::get('products', 'ProductsController@index')->name('products.index');

Auth::routes();

Route::group(['middleware' => 'auth'], function () {
    //邮箱验证
    Route::get('/email_verify_notice', 'PagesController@emailVerifyNotice')->name('email_verify_notice');
    Route::get('/email_verification/verify', 'EmailVerificationController@verify')->name('email_verification.verify');
    Route::get('/email_verification/send', 'EmailVerificationController@send')->name('email_verification.send');

    Route::group(['middleware' => 'email_verified'], function () {

        //收货地址
        Route::get('user_address', 'UserAddressesController@index')->name('user_addresses.index');
        Route::get('user_addresses/create', 'UserAddressesController@create')->name('user_addresses.create');
        Route::post('user_addresses', 'UserAddressesController@store')->name('user_addresses.store');
        Route::get('user_addresses/{user_address}', 'UserAddressesController@edit')->name('user_addresses.edit');
        Route::put('user_addresses/{user_address}', 'UserAddressesController@update')->name('user_addresses.update');
        Route::delete('user_addresses/{user_address}', 'UserAddressesController@destroy')->name('user_addresses.destroy');

        //商品
        Route::post('products/{product}/favorite', 'ProductsController@favor')->name('products.favor');
        Route::delete('products/{product}/favorite', 'ProductsController@disfavor')->name('products.disfavor');
        Route::get('products/favorites', 'ProductsController@favorites')->name('products.favorites');

        //购物车
        Route::post('cart', 'CartController@add')->name('cart.add');
        Route::get('cart', 'CartController@index')->name('cart.index');
        Route::delete('cart/{sku}', 'CartController@remove')->name('cart.remove');

        //订单
        Route::post('orders', 'OrderController@store')->name('orders.store');
        Route::get('orders', 'OrderController@index')->name('orders.index');
        Route::get('orders/{order}', 'OrderController@show')->name('orders.show');
        Route::post('orders/{order}/received', 'OrderController@received')->name('orders.received');
        Route::get('orders/{order}/review', 'OrderController@review')->name('orders.review.show');
        Route::post('orders/{order}/review', 'OrderController@sendReview')->name('orders.review.store');
        Route::post('orders/{order}/apply_refund', 'OrderController@applyRefund')->name('orders.apply_refund');

        // 众筹
        Route::post('crowdfunding_orders', 'OrderController@crowdfunding')->name('crowdfunding_orders.store');

        //支付
        Route::get('payment/{order}/alipay', 'PaymentController@payByAlipay')->name('payment.alipay');
        Route::get('payment/alipay/return', 'PaymentController@alipayReturn')->name('payment.alipay.return');
        Route::get('payment/{order}/wechat', 'PaymentController@payByWechat')->name('payment.wechat');
        Route::post('payment/wechat/refund_notify', 'PaymentController@wechatRefundNotify')->name('payment.wechat.refund_notify');

        // 分期付款
        Route::post('payment/{order}/installment', 'PaymentController@payByInstallment')->name('payment.installment');
        Route::get('installments', 'InstallmentsController@index')->name('installments.index');

        //优惠券
        Route::get('coupon_codes/{code}', 'CouponCodesController@show')->name('coupon_codes.show');
    });
});

Route::get('products/{product}', 'ProductsController@show')->name('products.show');
Route::post('payment/alipay/notify', 'PaymentController@alipayNotify')->name('payment.alipay.notify');
Route::post('payment/wechat/notify', 'PaymentController@wechatNotify')->name('payment.wechat.notify');
