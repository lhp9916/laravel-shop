<?php

namespace App\Admin\Controllers;

use App\Exceptions\InvalidRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HandleRefundRequest;
use App\Models\Order;
use Encore\Admin\Controllers\ModelForm;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ModelForm;

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index()
    {
        return Admin::content(function (Content $content) {

            $content->header('订单列表');
            $content->body($this->grid());
        });
    }


    protected function grid()
    {
        return Admin::grid(Order::class, function (Grid $grid) {
            $grid->model()->whereNotNull('paid_at')->orderBy('paid_at', 'desc');

            $grid->no('订单流水号');
            $grid->column('user.name', '买家');
            $grid->total_amount('总金额')->sortable();
            $grid->paid_at('支付时间')->sortable();
            $grid->ship_status('物流')->display(function ($value) {
                return Order::$shipStatusMap[$value];
            });
            $grid->refund_status('退款状态')->display(function ($value) {
                return Order::$refundStatusMap[$value];
            });
            $grid->disableCreateButton();
            $grid->actions(function ($actions) {
                $actions->disableDelete();
                $actions->disableEdit();
                $actions->append('<a href="' . route('admin.orders.show', ['order' => $actions->getKey()]) . '"><i class="fa fa-eye"></i></a>');
            });
            $grid->tools(function ($tools) {
                $tools->batch(function ($batch) {
                    $batch->disableDelete();
                });
            });
        });
    }

    public function show(Order $order)
    {
        //使用自定义页面展示订单详情
        return Admin::content(function (Content $content) use ($order) {
            $content->header('查看详情');
            // body 方法可以接受视图作为参数
            $content->body(view('admin.orders.show', ['order' => $order]));
        });
    }

    public function ship(Order $order, Request $request)
    {
        // 判断当前订单是否已支付
        if (!$order->paid_at) {
            throw new InvalidRequestException('该订单未付款');
        }

        // 判断当前订单发货状态是否为未发货
        if ($order->ship_status !== Order::SHIP_STATUS_PENDING) {
            throw new InvalidRequestException('该订单已发货');
        }

        // Laravel 5.5 之后 validate 方法可以返回校验过的值
        $data = $this->validate($request, [
            'express_company' => ['required'],
            'express_no'      => ['required'],
        ], [], [
            'express_company' => '物流公司',
            'express_no'      => '物流单号',
        ]);
        // 将订单发货状态改为已发货，并存入物流信息
        $order->update([
            'ship_status' => Order::SHIP_STATUS_DELIVERED,
            // 我们在 Order 模型的 $casts 属性里指明了 ship_data 是一个数组
            // 因此这里可以直接把数组传过去
            'ship_data'   => $data,
        ]);

        // 返回上一页
        return redirect()->back();
    }

    //处理退款
    public function handleRefund(Order $order, HandleRefundRequest $request)
    {
        if ($order->refund_status !== Order::REFUND_STATUS_APPLIED) {
            throw new InvalidRequestException('订单状态不对');
        }
        if ($request->input('agree')) {
            $this->_refundOrder($order);
        } else {
            $extra = $order->extra ?: [];
            $extra['refund_disagree_reason'] = $request->input('reason');
            $order->update([
                'refund_status' => Order::REFUND_STATUS_PENDING,
                'extra'         => $extra,
            ]);
        }
        return $order;
    }

    protected function _refundOrder(Order $order)
    {
        switch ($order->payment_method) {
            case 'wechat':
                // 生成退款订单号
                $refundNo = Order::getAvailableRefundNo();
                app('wechat_pay')->refund([
                    'out_trade_no'  => $order->no, // 之前的订单流水号
                    'total_fee'     => $order->total_amount * 100, //原订单金额，单位分
                    'refund_fee'    => $order->total_amount * 100, // 要退款的订单金额，单位分
                    'out_refund_no' => $refundNo, // 退款订单号
                    // 微信支付的退款结果并不是实时返回的，而是通过退款回调来通知，因此这里需要配上退款回调接口地址
                    'notify_url'    => route('payment.wechat.refund_notify'),
                ]);
                // 将订单状态改成退款中
                $order->update([
                    'refund_no'     => $refundNo,
                    'refund_status' => Order::REFUND_STATUS_PROCESSING,
                ]);
                break;
            case 'alipay':
                $refundNO = Order::getAvailableRefundNo();
                $ret = app('alipay')->refund([
                    'out_trade_no'   => $order->no,
                    'refund_amount'  => $order->total_amount,
                    'out_request_no' => $refundNO,
                ]);
                \Log::info('支付宝退款', [$ret]);
                //根据支付宝的文档，如果返回值里有 sub_code 字段说明退款失败
                if ($ret->sub_code) {
                    $extra = $order->extra;
                    $extra['refund_failed_code'] = $ret->sub_code;
                    //将订单改为退款失败
                    $order->update([
                        'refund_no'     => $refundNO,
                        'refund_status' => Order::REFUND_STATUS_FAILED,
                        'extra'         => $extra,
                    ]);
                } else {
                    $order->update([
                        'refund_no'     => $refundNO,
                        'refund_status' => Order::REFUND_STATUS_SUCCESS,
                    ]);
                }
                break;
            default:
                throw new InvalidRequestException('未知订单方式：' . $order->payment_method);
                break;
        }
    }

}
