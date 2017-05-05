<?php
/**
 * User: leon
 * Date: 2017/5/4 0004  下午 3:22
 */

namespace VendorWechat\Http\Controllers;

use Illuminate\Http\Request;
use VendorWechat\Util\Util;
//use Qiniu\Auth;

class KangshifuController extends Controller
{
	const KSF_PREFIX = 'ksf:';

	public function getQnToken(Request $request)
	{
		$accessKey = Util::QINIU_ACCESS_KEY;
		$secretKey = Util::QINIU_SECRET_KEY;
		$auth = new \Qiniu\Auth($accessKey, $secretKey);// 构建鉴权对象
		$token = $auth->uploadToken('vendor-ads');

		return Util::getSuccessJson("success", ['token'=>$token]);
	}
	public function postUpUserInfo(Request $req)
	{
		$name = $req->input('name', '');
		$mobile = $req->input('mobile', '');

		if (!empty($mobile))
		{
			$cRedis = \Redis::connection();
			if (!$cRedis->exists(self::KSF_PREFIX.$mobile))
				$cRedis->hset(self::KSF_PREFIX.$mobile, 'huo_li', 0);
			if (!empty($name))
				$cRedis->hset(self::KSF_PREFIX.$mobile, 'name', $name);
		}
		return Util::getSuccessJson("success", [])
			->withCookie(cookie('ksf_mobile', $mobile, 0, '/', 'vendor.qnmami.com', true));
	}
	public function getUserInfo(Request $req)
	{
		$mobile = \Cookie::get('ksf_mobile', '');
		$cRedis = \Redis::connection();
		$name = $cRedis->hget(self::KSF_PREFIX.$mobile, 'name');
		if (empty($name)) $name = '';
		return Util::getSuccessJson("success", ['mobile'=>$mobile,'name'=>$name]);
	}
}