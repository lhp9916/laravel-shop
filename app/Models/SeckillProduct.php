<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeckillProduct extends Model
{
    public $timestamps = false;
    protected $fillable = ['start_at', 'end_at'];
    protected $dates = ['start_at', 'end_at'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getIsBeforeStartAttribute()
    {
        return now()->lt($this->start_at);
    }

    public function getIsAfterEndAttribute()
    {
        return now()->gt($this->end_at);
    }
}
