<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Monolog\Logger;
use Yansongda\Pay\Pay;
use Carbon\Carbon;
use Elasticsearch\ClientBuilder as ESClientBuilder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        \View::composer(['products.index','products.show'], \App\Http\ViewComposers\CategoryTreeComposer::class);
        
        Carbon::setLocale('zh');

        // 只在本地开发环境启用 SQL 日志
        if (app()->environment('local')) {
            \DB::listen(function ($query) {
                \Log::info(Str::replaceArray('?', $query->bindings, $query->sql));
            });
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('alipay', function () {
            $config = config('pay.alipay');
            $config['notify_url'] = route('payment.alipay.notify');
            $config['return_url'] = route('payment.alipay.return');
            if (app()->environment() !== 'production') {
                $config['mode'] = 'dev';
                $config['log']['level'] = Logger::DEBUG;
            } else {
                $config['log']['level'] = Logger::WARNING;
            }
            return Pay::alipay($config);
        });

        $this->app->singleton('wechat_pay', function () {
            $config = config('pay.wechat');
            $config['notify_url'] = route('payment.wechat.notify');
            if (app()->environment() !== 'production') {
                $config['mode'] = 'dev';
                $config['log']['level'] = Logger::DEBUG;
            } else {
                $config['log']['level'] = Logger::WARNING;
            }
            return Pay::wechat($config);
        });

        $this->app->singleton('es', function () {
            // 从配置文件读取 Elasticsearch 服务器列表
            $builder = ESClientBuilder::create()->setHosts(config('database.elasticsearch.hosts'));
            // 如果是开发环境
            if (app()->environment() === 'local') {
                // 配置日志，Elasticsearch 的请求和返回数据将打印到日志文件中，方便我们调试
                $builder->setLogger(app('log')->getMonolog());
            }

            return $builder->build();
        });
    }
}
