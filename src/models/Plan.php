<?php

namespace Increment\Plan\Models;
use Illuminate\Database\Eloquent\Model;
use App\APIModel;

class Plan extends APIModel
{
   	protected $table = 'plans';
    protected $fillable = ['account_id', 'coupon_id', 'order_number', 'title', 'start', 'end', 'price', 'sub_total', 'tax', 'discount', 'total_amount', 'status'];
}
