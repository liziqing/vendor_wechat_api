<?php namespace VendorWechat\Services;

use VendorWechat\Exceptions\SMException;
use VendorWechat\Exceptions\ExceptionConstants;

class YuntongxunService {
	private $instance = null;

	public function __construct()
	{
		require_once("/root/vendor_wechat_api/app/Service/sdk/CCPRestSDK.php");
		$accountSid = 'aaf98f894d328b13014d6661f1de2560';
		$accountToken = '3377fa13da7ab7b6946a55f6a71f32fd';
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

}
