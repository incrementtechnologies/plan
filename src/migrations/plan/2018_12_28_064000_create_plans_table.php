<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('account_id');
            $table->bigInteger('coupon_id')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('payment_payload')->nullable();
            $table->string('payment_payload_value')->nullable();
            $table->string('order_number')->nullable();
            $table->string('title');
            $table->dateTime('start')->nullable();
            $table->dateTime('end')->nullable();
            $table->float('price');
            $table->float('sub_total')->nullable();
            $table->float('tax')->nullable();
            $table->float('discount')->nullable();
            $table->float('total');
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('plans');
    }
}
