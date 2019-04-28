<?php

namespace Increment\Plan\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Plan\Models\Plan;
use Increment\Plan\Models\Invitation;
use Increment\Payment\Models\PaypalTransaction;
use Increment\Payment\Models\StripeWebhook;
use Carbon\Carbon;
class PlanController extends APIController
{
		protected $subTotal = 0;
    protected $total = 0;
    protected $tax = 0;

    function __construct(){
    	$this->model = new Plan();

    	$this->notRequired = array(
    		'coupon_id', 'start', 'end', 'payment_type', 'payment_payload', 'payment_payload_value'
    	);
    }

    public function create(Request $request){
    	$data = $request->all();
      $accountId = $data['account_id'];
      $months = intval($data['sub_total']) / intval($data['price']);
    	$data['order_number'] = $this->getOrderNumber();
      $accountDetails = $this->retrieveAccountDetails($accountId);

      if($accountDetails != null){
        $accountDate = Carbon::createFromFormat('Y-m-d H:i:s', $accountDetails['created_at']);
        $current = Carbon::now();
        $diff = $accountDate->diffInDays($current, false);

        if($diff >= 30){
          $currentPlan = Plan::where('account_id', '=', $accountId)->where('status', '=', 'completed')->orderBy('end', 'desc')->first();
          if($currentPlan){
            $data['start'] =  Carbon::createFromFormat('Y-m-d H:i:s', $currentPlan->end)->addDay(1);
            $data['end'] = Carbon::createFromFormat('Y-m-d H:i:s', $currentPlan->end)->addDay(1)->addMonth($months);
          }else{
            $data['start'] =  Carbon::now();
            $data['end'] = Carbon::now()->addMonth($months);
          }
          $this->model = new Plan();
          $this->insertDB($data);
          return $this->response();
        }else{
          $currentPlan = Plan::where('account_id', '=', $accountId)->where('status', '=', 'completed')->orderBy('end', 'desc')->first();
          if($currentPlan){
            $data['start'] =  Carbon::createFromFormat('Y-m-d H:i:s', $currentPlan->end)->addDay(1);
            $data['end'] = Carbon::createFromFormat('Y-m-d H:i:s', $currentPlan->end)->addDay(1)->addMonth($months);
          }else{
            $data['start'] = Carbon::createFromFormat('Y-m-d H:i:s', $accountDetails['created_at'])->addDay(1)->addMonth(1);
            $data['end'] = Carbon::createFromFormat('Y-m-d H:i:s', $accountDetails['created_at'])->addDay(1)->addMonth($months + 1);
          }

          $this->model = new Plan();
          $this->insertDB($data);
          return $this->response();
        }

      }else{
        return $this->response();     
      }
    }

    public function retrieve(Request $request){
    	$data = $request->all();
    	$cards =app('Increment\Payment\Http\PaymentMethodController')->getPaymentMethod('account_id', $data['account_id']);
    	$this->retrieveDB($data);
      $result = $this->response['data'];

      if(sizeof($result) > 0){
        $i = 0;
        foreach ($result as $key) {
          $this->subTotal += $result[$i]['sub_total'];
          $this->response['data'][$i]['months'] = intval($result[$i]['sub_total']) / intval($result[$i]['price']);
          $this->response['data'][$i]['start_human'] = Carbon::createFromFormat('Y-m-d H:i:s', $result[$i]['start'])->copy()->tz('Asia/Manila')->format('F j, Y');
          $this->response['data'][$i]['end_human'] = Carbon::createFromFormat('Y-m-d H:i:s', $result[$i]['end'])->copy()->tz('Asia/Manila')->format('F j, Y');
          $i++;
        }
      }
      
      $this->response['method'] = $cards;
      $this->response['sub_total'] = $this->subTotal;
      $this->response['tax'] = $this->tax;
      $this->response['discount'] = 0;
      $this->response['total'] = $this->subTotal - $this->tax;
      return $this->response();
    }


    public function retrieveSummary(Request $request){
      $data = $request->all();
      $this->retrieveDB($data);
      $result = $this->response['data'];
      $cards = app('Increment\Payment\Http\PaymentMethodController')->getPaymentMethod('account_id', $data['account_id']);
      $coupon = null;
      if(sizeof($result) > 0){
        $i = 0;
        foreach ($result as $key) {
          $coupon = ($result[$i]['coupon_id'] != null) ? $this->getCoupon($result[$i]['coupon_id']) : null;
        	$this->response['data'][$i]['months'] = intval($result[$i]['sub_total']) / intval($result[$i]['price']);
          if(($result[$i]['payment_type'] == 'authorized' || $result[$i]['payment_type'] == 'express') && $result[$i]['payment_payload'] == 'credit_card'){
            $this->response['data'][$i]['method'] = app('Increment\Payment\Http\PaymentMethodController')->getPaymentMethod('id', $result[$i]['payment_payload_value']);
          }else if($result[$i]['payment_type'] == 'express' && $result[$i]['payment_payload'] == 'paypal'){
            $this->response['data'][$i]['method'] = app('Increment\Payment\Http\PaypalTransactionController')->getPaypalTransaction($result[$i]['payment_payload_value']);
          }else{
            $this->response['data'][$i]['method'] = null;
          }
          

          $this->response['data'][$i]['coupon'] = $coupon;
          $i++;
        }
      }
      return $this->response();
    }


    public function update(Request $request){
    	$data = $request->all();
      $accountId = $data['account_id'];
      $id = $data['id'];
      $tax = $data['tax'];
      $couponId = $data['coupon_id'];
      $subTotal = $data['sub_total'];
      $discount = $data['discount'];
      $total = round(floatval($data['total']), 2);
      $paymentType = $data['payment_type'];
      $paymentPayload = $data['payment_payload'];
      $paymentPayloadValue = $data['payment_payload_value'];
      $email = $data['email'];
      $title = 'Charge for order number'.$data['order_number'];
      $updateData = array(
        'id'  => $id,
        'tax' => $tax,
        'sub_total' => $subTotal,
        'discount' => $discount,
        'total_amount' => $total,
        'payment_type' => $paymentType,
        'payment_payload' => $paymentPayload,
        'status'  => 'completed',
        'updated_at'  => Carbon::now()
      );

      if($couponId != '' && $couponId != null){
        $updateData['coupon_id'] = $couponId;
      }

      if(($paymentType == 'authorized' || $paymentType == 'express') && $paymentPayload == 'credit_card'){
        $paymentMethod = app('Increment\Payment\Http\PaymentMethodController')->getPaymentMethod('id', $paymentPayloadValue);
        $updateData['payment_payload_value'] = $paymentPayloadValue;
        $charge = null;
        if($paymentMethod->payload == 'credit_card'){
          $stripe = new StripeWebhook();
          $charge = $stripe->chargeCustomer($email, $paymentMethod->stripe->source, $paymentMethod->stripe->customer, $total, $title);
        }

        if($charge && $charge->status == 'succeeded'){
        	$this->model = new Plan();
          $this->updateDB($updateData);
          return $this->response();
        }else{
          return response()->json(array(
            'data'  => false,
            'error' => 'Unable to charge',
            'timestamps'  => Carbon::now()
          ));
        }

      }else if($paymentType == 'express' && $paymentPayload == 'paypal'){
        $payerInfo = $paymentPayloadValue['payer']['payer_info'];
        $paypal = new PaypalTransaction();
        $paypal->account_id = $accountId;
        $paypal->paypal = $paymentPayloadValue['id'];
        $paypal->cart = $paymentPayloadValue['cart'];
        $paypal->email = $payerInfo['email'];
        $paypal->first_name = $payerInfo['first_name'];
        $paypal->middle_name = $payerInfo['middle_name'];
        $paypal->last_name = $payerInfo['last_name'];
        $paypal->payer = $payerInfo['payer_id'];
        $paypal->total = $total;
        $paypal->currency = 'PHP';
        $paypal->save();

        if($paypal->id){
        	$updateData['payment_payload_value'] = $paypal->id;
        	$this->model = new Plan();
          $this->updateDB($updateData);
          return $this->response();
        }else{
          return response()->json(array(
            'data'  => false,
            'error' => 'Unable to charge',
            'timestamps'  => Carbon::now()
          ));
        }
      }else{
          return response()->json(array(
            'data'  => false,
            'error' => 'Unable to charge',
            'timestamps'  => Carbon::now()
          ));
      }
    }

    public function getOrderNumber(){
    	$result = Plan::select('*')->count();
    	if($result){
        if($result >= 1000){
          return 'IDFO-'.($result + 1);
        }else if($result >= 100){
          return 'IDFO-0'.($result + 1);
        }else if($result >= 10){
          return 'IDFO-00'.($result + 1);
        }else if($result >= 0){
          return 'IDFO-000'.($result + 1);
        }
      }else{
        return 'IDFO-0001';
      }
    }

    public function applyRewards(Request $request){
      $data = $request->all();
      $accountId = $data['account_id'];
      $account = $this->retrieveAccountDetails($accountId);
      $result = Plan::where('account_id', '=', $accountId)->where('status', '=', 'completed')->orderBy('end')->first();
      $insertData = array(
        'account_id' => $accountId,
        'coupon'     => null,
        'payment_type' => 'reward',
        'payment_payload' => null,
        'payment_payload_value' => null,
        'order_number' => $this->getOrderNumber(),
        'price' => 0,
        'tax' => 0,
        'total_amount'  => 0,
        'discount' => 0,
        'status'  => 'completed',
        'created_at'  => Carbon::now()
      );
      if($result){
        $insertData['start'] = Carbon::createFromFormat('Y-m-d H:i:s', $result->end)->addDay(1);
        $insertData['end'] = Carbon::createFromFormat('Y-m-d H:i:s', $result->end)->addDay(1)->addMonth(1);
        $insertData['title'] = $result->title;
        $insertData['sub_total'] = $result->price;
        $insertData['price'] = $result->price;
      }else{
        $current = Carbon::now();
        $dayInMonth = Carbon::createFromFormat('Y-m-d H:i:s', $account['created_at'])->daysInMonth;
        $accountDate = Carbon::createFromFormat('Y-m-d H:i:s', $account['created_at']);
        $diff = $accountDate->diffInDays($current, false);
        if($diff <= $dayInMonth){
          $insertData['start'] = Carbon::createFromFormat('Y-m-d H:i:s', $account['created_at'])->addDay(1);
          $insertData['end'] = Carbon::createFromFormat('Y-m-d H:i:s', $account['created_at'])->addDay(1)->addMonth(1);
        }else{
          $insertData['start'] = Carbon::now();
          $insertData['end'] = Carbon::now()->addMonth(1);
        }
        
        $insertData['title'] = 'basic';
        $insertData['sub_total'] = 399;
        $insertData['price'] = 399;
      }

      $this->model = new Plan();
      $this->insertDB($insertData);

      if($this->response['data'] > 0){
        Invitation::where('id', '=', $data['id'])->update(array(
          'status'  => $data['status'],
          'updated_at' => Carbon::now()
        ));
      }
      return $this->response();
    }

    public function getCurrentPlan($accountId, $createdAt){
      $current = Carbon::now();
      $dayInMonth = Carbon::parse($createdAt)->daysInMonth;
      $accountDate = Carbon::createFromFormat('Y-m-d H:i:s', $createdAt);
      $diff = $accountDate->diffInDays($current, false);
      if($diff >= $dayInMonth){
        $result = Plan::where('account_id', '=', $accountId)->whereDate('end', '>=', Carbon::now())->where('status', '=', 'completed')->orderBy('created_at', 'asc')->first();
        if($result){
          return array(
            'title' => $result->title,
            'end_human' => Carbon::createFromFormat('Y-m-d H:i:s', $result->end)->copy()->tz('Asia/Manila')->format('F j, Y')
          );
        }else{
          return array(
            'title' => 'Expired',
            'end_human' => null
          );
        }
      }else{
        return array(
          'title' => 'Trial',
          'end_human' => Carbon::createFromFormat('Y-m-d H:i:s', $createdAt)->addMonth()->copy()->tz('Asia/Manila')->format('F j, Y')
        );
      }
    }

    public function checkPlan($accountId){
      $result = Plan::where('account_id', '=', $accountId)->whereDate('end', '>=', Carbon::now())->get();
      return (sizeof($result) > 0) ? true : false;
    }
}
