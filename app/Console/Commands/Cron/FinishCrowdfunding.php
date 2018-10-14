<?php

namespace App\Console\Commands\Cron;

use Illuminate\Console\Command;
use App\Models\CrowdfundingProduct;
use App\Models\Order;
use App\Services\OrderService;
use App\Jobs\RefundCrowdfundingOrders;

class FinishCrowdfunding extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:finish-crowdfunding';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '结束众筹';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        CrowdfundingProduct::query()
        ->with(['product'])
        ->where('end_at', '<=', now())
        ->where('status', CrowdfundingProduct::STATUS_FUNDING)
        ->get()
        ->each(function (CrowdfundingProduct $crowdfunding) {
            // 如果众筹目标金额大于实际众筹金额
            if ($crowdfunding->target_amount > $crowdfunding->total_amount) {
                // 调用众筹失败逻辑
                $this->crowdfundingFailed($crowdfunding);
            } else {
                // 否则调用众筹成功逻辑
                $this->crowdfundingSucceed($crowdfunding);
            }
        });
    }

    protected function crowdfundingSucceed(CrowdfundingProduct $crowdfunding)
    {
        // 只需将众筹状态改为众筹成功即可
        $crowdfunding->update([
            'status' => CrowdfundingProduct::STATUS_SUCCESS,
        ]);
    }

    protected function crowdfundingFailed(CrowdfundingProduct $crowdfunding)
    {
        // 将众筹状态改为众筹失败
        $crowdfunding->update([
            'status' => CrowdfundingProduct::STATUS_FAIL,
        ]);
        dispatch(new RefundCrowdfundingOrders($crowdfunding));
    }
}
