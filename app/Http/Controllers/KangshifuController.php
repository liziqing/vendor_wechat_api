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

	private function delDb()
	{
		$cRedis = \Redis::connection();
		$asHashKey = $cRedis->keys(self::KSF_PREFIX.'*');
		foreach ($asHashKey as $sHashKey)
		{
			$cRedis->del($sHashKey);
		}
	}
	private function getMobile(Request $req)
	{
//		$mobile = \Cookie::get(self::KSF_COOKIE, '');
		$mobile = $req->input('mobile', '');
		return $mobile;
	}

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
		return Util::getSuccessJson("success", []);
//			->withCookie(cookie(self::KSF_COOKIE, $mobile, 0, '/', 'vendor.qnmami.com'));
	}
	public function getUserInfo(Request $req)
	{
		$mobile = $this->getMobile($req);
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
		$mobile = $this->getMobile($req);
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
//		$noCookie = false;
		$cRedis = \Redis::connection();
//		$mobile = \Cookie::get(self::KSF_COOKIE, '');
		$mobile = $this->getMobile($req);
		if (empty($mobile))
			return Util::getErrorJson(ExceptionConstants::CODE_PARAM, "请填写手机号");
		else
		{
//			$firstMobile = $req->input('mobile', '');
//			if (empty($firstMobile))
//				return Util::getErrorJson(ExceptionConstants::CODE_PARAM, "请填写手机号");
			//新建用户
			if (!$cRedis->exists(self::KSF_PREFIX.$mobile))
				$cRedis->hset(self::KSF_PREFIX.$mobile, self::USER_HUOLI, 0);
//			$noCookie = true;
		}

		$type = $req->input('type', 2);//1订单 2活力时刻
		$url = $req->input('url', '');

		$zkey = self::KSF_PREFIX."$type:1:$mobile";//1待审核 不进墙
		$timestamp = (new \DateTime("now"))->getTimestamp();
		$cRedis->zadd($zkey, $timestamp, $url);

		return Util::getSuccessJson("success", []);
		/*if ($noCookie)
		{
			return Util::getSuccessJson("success", []);
//				->withCookie(cookie(self::KSF_COOKIE, $mobile, 0, '/', 'vendor.qnmami.com'));
		}
		else
		{
			return Util::getSuccessJson("success", []);
		}*/
	}
	private function getImageUrl($blurryKey)
	{
		$urlList = [];
		$aPregOut = array();
		$cRedis = \Redis::connection();
		$zsetKeys = $cRedis->keys($blurryKey.'*');
		foreach ($zsetKeys as $zsetKey)
		{
			preg_match("/^$blurryKey(\d*)$/", $zsetKey, $aPregOut);
			if (!empty($aPregOut[1]))
			{
				$mobile = $aPregOut[1];
				$urlList[$mobile] = $cRedis->zrevrange($zsetKey, 0, -1);
			}
//			$urlList = array_merge($urlList, $cRedis->zrevrange($zsetKey, 0, -1));
		}
		return $urlList;
	}
	public function getImageList(Request $req)
	{
		$type = $req->input('type', 0);//1用户的活力时刻 2活力墙 3非墙全活力 订单审核（4未 5已 6不）
		$offset = $req->input('offset', 0);
		$limit = $req->input('limit', 20);

		$urlList = [];
		$cRedis = \Redis::connection();
		switch ($type)
		{
			case 1:
			{
				$mobile = $this->getMobile($req);
				if (!empty($mobile))
				{
					$zkey1 = self::KSF_PREFIX."2:1:$mobile";
					$zkey2 = self::KSF_PREFIX."2:2:$mobile";
					$urlList = array_merge($urlList, $cRedis->zrevrange($zkey2, 0, -1));//byscore '+inf', '-inf'
					$urlList = array_merge($urlList, $cRedis->zrevrange($zkey1, 0, -1));
//					$zkeyu = self::KSF_PREFIX."2:$mobile";
//					$cRedis->zunionstore($zkeyu,[$zkey1, $zkey2]);
//					$cRedis->zrevrangebyscore($zkeyu, '+inf', '-inf');
				}
				break;
			}
			case 2:
			{
				$urlList = $this->getImageUrl(self::KSF_PREFIX."2:2:");//array_merge
//				$zkeyu = self::KSF_PREFIX."2:2";
				break;
			}
			case 3:
			{
				$urlList = $this->getImageUrl(self::KSF_PREFIX."2:1:");
				break;
			}

			case 4:
			{
				$urlList = $this->getImageUrl(self::KSF_PREFIX."1:1:");
				break;
			}
			case 5:
			{
				$urlList = $this->getImageUrl(self::KSF_PREFIX."1:2:");
				break;
			}
			case 6:
			{
				$urlList = $this->getImageUrl(self::KSF_PREFIX."1:3:");
				break;
			}
			default:
			{
				break;
			}
		}
		return Util::getSuccessJson("success", ['list'=>$urlList]);
	}
	public function postChangeStatus(Request $req)
	{
		$url = $req->input('url', 0);
		$type = $req->input('type', 0);//11 12 13 21 22
		$mobile = $req->input('mobile', '');
		$result = $req->input('result', 0); //1通过 2不通过

		$cRedis = \Redis::connection();
		$map = [
			11=>[1=>[1,2], 2=>[1,3]],
			12=>[1=>[2,2], 2=>[2,3]],
			13=>[1=>[3,2], 2=>[3,3]],
			21=>[1=>[1,2], 2=>[1,1]],
			22=>[1=>[2,2], 2=>[2,1]],
		];
		$fromStatus = $map[$type][$result][0];
		$toStatus = $map[$type][$result][1];
		$DorH = $type/10;//订单 or 活力
		$fromKey = self::KSF_PREFIX."$DorH:$fromStatus:$mobile";
		$toKey = self::KSF_PREFIX."$DorH:$toStatus:$mobile";

		$timestamp = $cRedis->zscore($fromKey, $url);
		$cRedis->zrem($fromKey, $url);
		$cRedis->zadd($toKey, $timestamp, $url);
	}
	public function getManage(Request $req)
	{
		return view('kangshifu', []);
	}
}