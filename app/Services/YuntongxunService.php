<?php namespace VendorWechat\Services;

use Curl\Curl;
use VendorWechat\Exceptions\SMException;
use VendorWechat\Exceptions\ExceptionConstants;

class YuntongxunService {
	private $instance = null;

	public function __construct()
	{
		require_once(base_path("app/Services/sdk/CCPRestSDK.php"));///root/vendor_wechat_api/
		$accountSid = 'aaf98f894d328b13014d6661f1de2560';
		$accountToken = 'cc3436f2e735428cb1e642ce3cf78b00';
		$appid = '8a216da85cb0540d015cc4f4df9605c4';
		$serverHost = 'app.cloopen.com';
		$serverPort = 8883;
		$softVersion = '2013-12-26';

		$this->instance = new \REST($serverHost, $serverPort, $softVersion);
		$this->instance->setAccount($accountSid, $accountToken);
		$this->instance->setAppId($appid);
	}

	public function sendSmsTemplate($mobile, Array $datas, $tempId) {
		if(is_array($mobile))
		{
			$to = implode(",", $mobile);
		}
		else if(is_string($mobile))
		{
			$to = $mobile;
		}else{
			return;
		}

		$result = $this->instance->sendTemplateSMS($to,$datas,$tempId);
		if($result == NULL ) {
			throw new SMException("System error.", ExceptionConstants::CODE_SYSTEM);
		}
		if($result->statusCode!=0) {
			throw new SMException($result->statusMsg, ExceptionConstants::CODE_SYSTEM);
		}else{

			$smsmessage = $result->TemplateSMS;
			//echo "dateCreated:".$smsmessage->dateCreated."<br/>";
			//echo "smsMessageSid:".$smsmessage->smsMessageSid."<br/>";
			//TODO 添加成功处理逻辑
		}
	}

	public function sendSmsKsf($mobile, $code)
	{
		$cCurl = new Curl();
		$dParam = [
			'username' => 'kangshifu',
			'password' => strtolower(md5('Ksf073#&')),
			'mobile' => $mobile,
			'content' => '专属视角动态兑换码：'.$code.'，请在15分钟内填写【#康师傅绿茶健康活力派#绿动健康走】',
			'productid' => '676767',
			'xh' => ''
		];
//		\Log::error('ksf sm req:'.var_export($dParam,true));
		$cCurl->get('http://www.ztsms.cn/sendSms.do', $dParam);
		if ($cCurl->error)
		{
			\Log::error('RESULT: ' . $cCurl->errorCode . ': ' . $cCurl->errorMessage);
		}
		\Log::error('ksf sm rsp:'.var_export($cCurl->response,true));
	}

}
