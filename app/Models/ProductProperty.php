<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductProperty extends Model
{
    protected $fillable = ['name', 'value'];
    protected $timestamps = false;

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
