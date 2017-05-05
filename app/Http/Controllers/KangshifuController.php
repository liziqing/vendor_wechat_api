<?php
/**
 * User: leon
 * Date: 2017/5/4 0004  下午 3:22
 */

namespace VendorWechat\Http\Controllers;

use Illuminate\Http\Request;
use VendorWechat\Util\Util;
use VendorWechat\Exceptions\ExceptionConstants;
//use Qiniu\Auth;

class KangshifuController extends Controller
{
	const KSF_PREFIX = 'ksf:';
	const KSF_COOKIE = 'ksf_mobile';
	//1 订单 2 活力时刻  1待审核 2通过 3不通过 1不进墙 2进墙

	const USER_HUOLI = 'huo_li';
	const USER_NAME  = 'name';

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
				$cRedis->hset(self::KSF_PREFIX.$mobile, self::USER_HUOLI, 0);
			if (!empty($name))
				$cRedis->hset(self::KSF_PREFIX.$mobile, self::USER_NAME, $name);
		}
		return Util::getSuccessJson("success", [])
			->withCookie(cookie(self::KSF_COOKIE, $mobile, 0, '/', 'vendor.qnmami.com', true));
	}
	public function getUserInfo(Request $req)
	{
		$mobile = \Cookie::get(self::KSF_COOKIE, '');
		if (!empty($mobile))
		{
			$cRedis = \Redis::connection();
			$name = $cRedis->hget(self::KSF_PREFIX . $mobile, self::USER_NAME);
		}
		if (empty($name)) $name = '';
		return Util::getSuccessJson("success", ['mobile'=>$mobile,'name'=>$name]);
	}
	public function getHuoLi(Request $req)
	{
		$mobile = \Cookie::get(self::KSF_COOKIE, '');
		if (!empty($mobile))
		{
			$cRedis = \Redis::connection();
			$huoli = $cRedis->hget(self::KSF_PREFIX . $mobile, self::USER_HUOLI);
		}
		if (empty($huoli)) $huoli = 0;
		return Util::getSuccessJson("success", ['value'=>$huoli]);
	}
	public function postImageUp(Request $req)
	{
		$noCookie = false;
		$cRedis = \Redis::connection();
		$mobile = \Cookie::get(self::KSF_COOKIE, '');
		if (empty($mobile))
		{
			$firstMobile = $req->input('mobile', '');
			if (empty($firstMobile))
				return Util::getErrorJson(ExceptionConstants::CODE_PARAM, "请填写手机号");
			//新建用户
			if (!$cRedis->exists(self::KSF_PREFIX.$mobile))
				$cRedis->hset(self::KSF_PREFIX.$mobile, self::USER_HUOLI, 0);
			$noCookie = true;
		}

		$type = $req->input('type', 0);//1订单 2活力时刻
		$url = $req->input('url', '');

		$zkey = self::KSF_PREFIX."$mobile:$type:1";//1待审核 不进墙
		$timestamp = (new \DateTime("now"))->getTimestamp();
		$cRedis->zadd($zkey, $timestamp, $url);

		if ($noCookie)
		{
			return Util::getSuccessJson("success", [])
				->withCookie(cookie(self::KSF_COOKIE, $mobile, 0, '/', 'vendor.qnmami.com', true));
		}
		else
		{
			return Util::getSuccessJson("success", []);
		}
	}
	public function getImageList(Request $req)
	{
		$type = $req->input('type', 0);//1用户的活力时刻 2活力墙 3非墙全活力 订单审核（4未 5已 6不）
		$offset = $req->input('offset', 0);
		$limit = $req->input('limit', 20);
	}
}