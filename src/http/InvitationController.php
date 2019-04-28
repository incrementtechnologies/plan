<?php

namespace Increment\Plan\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Plan\Models\Invitation;
use Increment\Account\Models\Account;
use App\Mail\Referral;
use Mail;
use Carbon\Carbon;
class InvitationController extends APIController
{
	function __construct(){
		$this->model = new Invitation();
	}

	public function create(Request $request){
		$data = $request->all();
		$exist = $this->checkIfExist($data['to_email']);

		if($exist == false){
			$user = $this->retrieveAccountDetails($data['account_id']);
   		$insertData = array(
   			'code' => $this->generateCode(),
   			'account_id'	=> $data['account_id'],
   			'address'	=> $data['to_email'],
   			'status'	=> 'sent'
   		);
   		$this->model = new Invitation();
   		$this->insertDB($insertData);
   		if($this->response['data'] > 0 && $user != null){
   			Mail::to($data['to_email'])->send(new Referral($user, $data['content'], $data['to_email'], $this->getDetails($this->response['data'])));
   		}
   		return $this->response();
		}else{
			$this->response['data'] = null;
			$this->response['error'] = $exist;
			return $this->response();
		}
	}

	public function checkIfExist($email){
		$account = Account::where('email', '=', $email)->get();
		if(sizeof($account) > 0){
			return 'Email address already exist';
		}else{
			$invites = Invitation::where('address', '=', $email)->get();
			return (sizeof($invites) > 0) ? 'Email Address was already invited.' : false;
		}
	}

   public function getDetails($id){
      $result = Invitation::where('id', '=', $id)->get();
      return (sizeof($result) > 0) ? $result[0] : null;
   }

	public function generateCode(){
      $code = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 64);
      $codeExist = Invitation::where('id', '=', $code)->get();
      if(sizeof($codeExist) > 0){
        $this->generateCode();
      }else{
        return $code;
      }
   }

   public function confirmReferral($code){
      $result = Invitation::where('code', '=', $code)->update(array(
         'status' => 'confirmed',
         'updated_at' => Carbon::now()
      ));

      $referrral = Invitation::where('code', '=', $code)->get();
      
      if(sizeof($referrral) > 0){
         app('App\Http\Controllers\EmailController')->notifyReferrer($referrral[0]['account_id']);
      }
   }
}
