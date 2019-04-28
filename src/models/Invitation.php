<?php

namespace Increment\Plan\Models;
use Illuminate\Database\Eloquent\Model;
use App\APIModel;
class Invitation extends APIModel
{
    protected $table = 'invitations';
    protected $fillable = ['code', 'account_id', 'address', 'status'];
}
