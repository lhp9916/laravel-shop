<?php

use Moontoast\Math\BigNumber;

function route_class()
{
    return str_replace('.', '-', Route::currentRouteName());
}

if (!function_exists('big_number')) {
    // 默认的精度为小数点后两位
    function big_number($number, $scale = 2)
    {
        return new BigNumber($number, $scale);
    }
}
