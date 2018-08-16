<?php

namespace App\Http\Controllers;

use App\Events\OrderPaid;
use App\Exceptions\InvalidRequestException;
use App\Models\Order;
use Carbon\Carbon;
use Endroid\QrCode\QrCode;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function payByAlipay(Order $order, Request $request)
    {
        $this->authorize('own', $order);
        if ($order->paid_at || $order->closed) {
            throw new InvalidRequestException('订单状态不正确');
        }

        return app('alipay')->web([
            'out_trade_no' => $order->no,
            'total_amount' => $order->total_amount,
            'subject'      => '支付 Laravel Shop 的订单：' . $order->no,
        ]);

    }

    public function alipayReturn()
    {
        try {
            app('alipay')->verify();
        } catch (\Exception $e) {
            return view('pages.error', ['msg' => '数据不正确']);
        }

        return view('pages.success', ['msg' => '付款成功']);
    }

    public function alipayNotify()
    {
        $data = app('alipay')->verify();
        \Log::debug('Alipay notify', $data->all());
        $order = Order::query()->where('no', $data->out_trade_no)->first();
        if (!$order) {
            return 'fail';
        }
        if ($order->paid_at) {
            // 返回数据给支付宝
            return app('alipay')->success();
        }

        $order->update([
            'paid_at'        => Carbon::now(), // 支付时间
            'payment_method' => 'alipay', // 支付方式
            'payment_no'     => $data->trade_no, // 支付宝订单号
        ]);

        $this->afterPaid($order);

        return app('alipay')->success();
    }

    public function payByWechat(Order $order, Request $request)
    {
        $this->authorize('own', $order);
        if ($order->paid_at || $order->closed) {
            throw new InvalidRequestException('订单状态不正确');
        }

        $wechatOrder = app('wechat_pay')->scan([
            'out_trade_no' => $order->no,  // 商户订单流水号，与支付宝 out_trade_no 一样
            'total_fee'    => $order->total_amount * 100, // 与支付宝不同，微信支付的金额单位是分。
            'body'         => '支付 Laravel Shop 的订单：' . $order->no, // 订单描述
        ]);

        $qrCode = new QrCode($wechatOrder->code_url);

        return response($qrCode->writeString(), 200, ['Content-Type' => $qrCode->getContentType()]);
    }

    //微信的扫码支付没有前端回调只有服务器端回调
    public function wechatNotify()
    {
        $data = app('wechat_pay')->verify();
        $order = Order::query()->where('no', $data->out_trade_no)->first();
        if (!$order) {
            return 'fail';
        }
        if ($order->paid_at) {
            return app('wechat_pay')->success();
        }

        $order->update([
            'paid_at'        => Carbon::now(), // 支付时间
            'payment_method' => 'wechat', // 支付方式
            'payment_no'     => $data->transaction_id,
        ]);

        $this->afterPaid($order);

        return app('wechat_pay')->success();
    }

    public function wechatRefundNotify(Request $request)
    {
        // 给微信的失败响应
        $failXml = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[FAIL]]></return_msg></xml>';
        $data = app('wechat_pay')->verify(null, true);

        // 没有找到对应的订单，原则上不可能发生，保证代码健壮性
        if (!$order = Order::where('no', $data['out_trade_no'])->first()) {
            return $failXml;
        }

        if ($data['refund_status'] === 'SUCCESS') {
            // 退款成功，将订单退款状态改成退款成功
            $order->update([
                'refund_status' => Order::REFUND_STATUS_SUCCESS,
            ]);
        } else {
            // 退款失败，将具体状态存入 extra 字段，并表退款状态改成失败
            $extra = $order->extra;
            $extra['refund_failed_code'] = $data['refund_status'];
            $order->update([
                'refund_status' => Order::REFUND_STATUS_FAILED,
            ]);
        }

        return app('wechat_pay')->success();
    }

    protected function afterPaid(Order $order)
    {
        event(new OrderPaid($order));
    }
}
