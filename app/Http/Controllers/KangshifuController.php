<?php
/**
 * User: leon
 * Date: 2017/5/4 0004  下午 3:22
 */

namespace VendorWechat\Http\Controllers;

use Illuminate\Http\Request;
use VendorWechat\Services\YuntongxunService;
use VendorWechat\Util\Util;
use VendorWechat\Exceptions\ExceptionConstants;
//use Qiniu\Auth;

class KangshifuController extends Controller
{
	const KSF_PREFIX = 'ksf:';
	const KSF_NOTICE_PREFIX = 'notice:';
	const KSF_LOTTERY_PREFIX = 'lottery:';
	const KSF_COOKIE = 'ksf_mobile';
	//1 订单 2 活力时刻  1待审核 2通过 3不通过 1不进墙 2进墙

	const USER_HUOLI = 'huo_li';
	const USER_NAME  = 'name';
	const USER_CODE  = 'code';
	const USER_HAVE_PHOTO = 'have_photo';
	const USER_HAVE_SHARE = 'have_share';
	const USER_HAVE_WATCH = 'have_watch';

	const HAVE_FIRST_PRIZE = 'have_first_prize';
	const HAVE_SECOND_PRIZE = 'have_second_prize';
	const HAVE_THIRD_PRIZE = 'have_third_prize';

	public static function delDb()
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
	private function takeHuoLi($mobile, $type)
	{//todo 加锁 全民活力值
		//1活力照片 2分享 3观看TVC 4订单审核通过 5抽奖扣除
		$code = 2; //1成功获得活力值 2未能获得
		$lockKey = "".__METHOD__.$mobile."||".$type;
		try
		{
			Util::redisLock($lockKey);
			$cRedis = \Redis::connection();
			if ($cRedis->exists(self::KSF_PREFIX.$mobile))
			{
				switch ($type)
				{
					case 1:
					{
						if (!$cRedis->sismember(self::KSF_PREFIX.self::USER_HAVE_PHOTO, $mobile))
						{
							$cRedis->sadd(self::KSF_PREFIX.self::USER_HAVE_PHOTO, $mobile);
							$cRedis->hincrby(self::KSF_PREFIX.$mobile, self::USER_HUOLI, 20);
							$code = 1;
						}
						break;
					}
					case 2:
					{
						if (!$cRedis->sismember(self::KSF_PREFIX.self::USER_HAVE_SHARE, $mobile))
						{
							$cRedis->sadd(self::KSF_PREFIX.self::USER_HAVE_SHARE, $mobile);
							$cRedis->hincrby(self::KSF_PREFIX.$mobile, self::USER_HUOLI, 4);
							$code = 1;
						}
						break;
					}
					case 3:
					{
						if (!$cRedis->sismember(self::KSF_PREFIX.self::USER_HAVE_WATCH, $mobile))
						{
							$cRedis->sadd(self::KSF_PREFIX.self::USER_HAVE_WATCH, $mobile);
							$cRedis->hincrby(self::KSF_PREFIX.$mobile, self::USER_HUOLI, 12);
							$code = 1;
						}
						break;
					}
					case 4:
					{
						$this->takePrize($mobile, 3);
						$cRedis->hincrby(self::KSF_PREFIX.$mobile, self::USER_HUOLI, 72);
						$code = 1;
						break;
					}
					default:
					{
						break;
					}
				}
			}
		}
		catch (\Exception $e)
		{
			\Log::error('kangshifu taken huoli err:'.$e->getMessage());
		}
		Util::redisUnlock($lockKey);
		return $code;
	}
	public static function clearLockDaily()
	{
		$cRedis = \Redis::connection();
		$cRedis->del(self::KSF_PREFIX.self::USER_HAVE_PHOTO);
		$cRedis->del(self::KSF_PREFIX.self::USER_HAVE_SHARE);
		$cRedis->del(self::KSF_PREFIX.self::USER_HAVE_WATCH);
		$cRedis->del(self::KSF_PREFIX.self::HAVE_FIRST_PRIZE);
		$cRedis->del(self::KSF_PREFIX.self::HAVE_SECOND_PRIZE);
	}
	public static function setAllHuoli()
	{
		$cRedis = \Redis::connection();
		$allKeys = $cRedis->keys(self::KSF_PREFIX.'*');
		foreach ($allKeys as $oneKey)
		{

			if (preg_match("/^".self::KSF_PREFIX."(\d*)$/", $oneKey, $aPregOut))
			{
				if (!empty($aPregOut[1]))
				{
					$mobile = $aPregOut[1];
					$cRedis->hset(self::KSF_PREFIX.$mobile, self::USER_HUOLI, 9999);
				}
			}
		}
	}

	public function getHaveShare(Request $req)
	{
		$mobile = $this->getMobile($req);
		if (empty($mobile))
			;//return Util::getErrorJson(ExceptionConstants::CODE_PARAM, "请填写手机号");
		else
		{
			$cRedis = \Redis::connection();
			if (!$cRedis->exists(self::KSF_PREFIX.$mobile))
				$cRedis->hset(self::KSF_PREFIX.$mobile, self::USER_HUOLI, 0);
		}
		$code = $this->takeHuoLi($mobile, 2);
		return Util::getSuccessJson("success", ['code' => $code]);
	}
	public function getHaveWatch(Request $req)
	{
		$mobile = $this->getMobile($req);
		if (empty($mobile))
			return Util::getErrorJson(ExceptionConstants::CODE_PARAM, "请填写手机号");
		else
		{
			$cRedis = \Redis::connection();
			if (!$cRedis->exists(self::KSF_PREFIX.$mobile))
				$cRedis->hset(self::KSF_PREFIX.$mobile, self::USER_HUOLI, 0);
		}
		$this->takeHuoLi($mobile, 3);
		return Util::getSuccessJson("success", []);
	}

	public function getQnToken(Request $request)
	{
		return Util::getSuccessJson("success", ['token'=>'']);

		$accessKey = Util::QINIU_ACCESS_KEY;
		$secretKey = Util::QINIU_SECRET_KEY;
		$auth = new \Qiniu\Auth($accessKey, $secretKey);// 构建鉴权对象
		$token = $auth->uploadToken('vendor-ads');

		return Util::getSuccessJson("success", ['token'=>$token]);
	}
	public function anyUpUserInfo(Request $req)
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
		return Util::getSuccessJson("success", ['value'=>intval($huoli)]);
	}
	public function anyImageUp(Request $req)
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
		if (empty($url))
			return Util::getErrorJson(ExceptionConstants::CODE_PARAM, "未上传照片");

		$zkey = self::KSF_PREFIX."$type:1:$mobile";//1待审核 不进墙
		$timestamp = (new \DateTime("now"))->getTimestamp();
		$cRedis->zadd($zkey, $timestamp, $url);

		if (2 == $type)
			$code = $this->takeHuoLi($mobile, 1);

		return Util::getSuccessJson("success", ['code' => isset($code)? $code: 2]);
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
	private function getImageUrl($blurryKey, $start='+inf', $end='-inf')
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
//				$urlList[$mobile] = $cRedis->zrevrange($zsetKey, 0, -1);
				$adUrlTime = $cRedis->zrevrangebyscore($zsetKey, $start, $end, 'WITHSCORES');
				if (!empty($adUrlTime))
				{
					$urlList[$mobile] = [];
					foreach ($adUrlTime as $url => $time)
					{
						$urlList[$mobile][] = [
							'url' => str_replace(['%',"\\"], ['%25','%5c'], $url),//$url,
							'time' => date('Y-m-d H:i:s', $time)//(new \DateTime($time))->format('Y-m-d H:i:s')
						];
					}
				}
			}
//			$urlList = array_merge($urlList, $cRedis->zrevrange($zsetKey, 0, -1));
		}
		return $urlList;
	}
	public function getImageList(Request $req)
	{//todo 分页
		$type = $req->input('type', 0);//1用户的活力时刻 2活力墙 3非墙全活力 订单审核（4未 5已 6不）
		$offset = $req->input('offset', 0);
		$limit = $req->input('limit', 30);
		$start = $req->input('start', 0);
		$admin = $req->input('admin', 0);

		if ('all' == $start)
		{
			$startTS = '+inf';
			$endTS = '-inf';
		}
		else
		{
			$startDT = new \DateTime("now");
			$startDT->sub(new \DateInterval('P'.$start.'D'));

			$endDT = new \DateTime($startDT->format('Y-m-d'));
			$endDT->sub(new \DateInterval('P1D'));

			$startTS = (new \DateTime($startDT->format('Y-m-d').' 23:59:59'))->getTimestamp();
			$endTS = (new \DateTime($endDT->format('Y-m-d').' 00:00:01'))->getTimestamp();
		}

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
				if ($admin)
				{
					$urlListM = $this->getImageUrl(self::KSF_PREFIX."2:2:");//, $startTS, $endTS);
					$urlList = $urlListM;
				}
				else
				{
					$urlListM = $this->getImageUrl(self::KSF_PREFIX."2:2:");//array_merge
					foreach ($urlListM as $value)
					{
						foreach ($value as $one)
							$urlList[] = $one['url'];//array_merge($urlList, $value);
					}
					$urlList = array_slice($urlList, $offset ,$limit);
				}
//				$zkeyu = self::KSF_PREFIX."2:2";
				break;
			}
			case 3:
			{
				$urlList = $this->getImageUrl(self::KSF_PREFIX."2:1:", $startTS, $endTS);
				break;
			}

			case 4:
			{
				$urlList = $this->getImageUrl(self::KSF_PREFIX."1:1:", $startTS, $endTS);
				break;
			}
			case 5:
			{
				$urlList = $this->getImageUrl(self::KSF_PREFIX."1:2:", $startTS, $endTS);
				break;
			}
			case 6:
			{
				$urlList = $this->getImageUrl(self::KSF_PREFIX."1:3:", $startTS, $endTS);
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
		$url = str_replace(['%5c','%25'], ["\\",'%'], $url);
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
		$DorH = floor($type/10);//订单 or 活力
		$fromKey = self::KSF_PREFIX."$DorH:$fromStatus:$mobile";
		$toKey = self::KSF_PREFIX."$DorH:$toStatus:$mobile";

		$timestamp = $cRedis->zscore($fromKey, $url);
		$cRedis->zrem($fromKey, $url);
		$num = $cRedis->zadd($toKey, $timestamp, $url);

		if ((11 == $type || 13 == $type) &&
			1 == $result && $num > 0)
		{
			$this->takeHuoLi($mobile, 4);
			//通知加入通知列表
			$cRedis->sadd(self::KSF_PREFIX.self::KSF_NOTICE_PREFIX.$mobile, $url);
		}
		return Util::getSuccessJson("success", []);
	}
	public function getVerifyStatus(Request $req)
	{
		$mobile = $this->getMobile($req);
		$cRedis = \Redis::connection();
		$imageUrl = $cRedis->spop(self::KSF_PREFIX.self::KSF_NOTICE_PREFIX.$mobile);
		return Util::getSuccessJson("success", ['image'=>empty($imageUrl)? '': $imageUrl]);
	}
	public function getManage(Request $req)
	{
		return view('kangshifu', []);
	}

	public function getLottery(Request $req)
	{//todo 加锁
		$lotteryId = 0;
		$mobile = $this->getMobile($req);
		if (!empty($mobile))
		{
			$lockKey = "".__METHOD__.$mobile;
			try
			{
				Util::redisLock($lockKey);
				$cRedis = \Redis::connection();
				$huoli = $cRedis->hget(self::KSF_PREFIX.$mobile, self::USER_HUOLI);
				if ($huoli >= 72)
				{
					$cRedis->hincrby(self::KSF_PREFIX.$mobile, self::USER_HUOLI, -72);

					$turnTable = [30, 0, 70, 0]; //1、24号门票 2、21号门票 3、观看卷 4、未中奖
					$havenFirst = $cRedis->get(self::KSF_PREFIX.self::HAVE_FIRST_PRIZE);
					if ((!empty($havenFirst) && intval($havenFirst) >= 4) ||
						$cRedis->zscore(self::KSF_PREFIX.self::KSF_LOTTERY_PREFIX.$mobile, 1))
					{
						$turnTable[3] += $turnTable[0];
						$turnTable[0] = 0;
					}
					$havenSecond = $cRedis->get(self::KSF_PREFIX.self::HAVE_SECOND_PRIZE);
					if ((!empty($havenSecond) && intval($havenSecond) >= 2) ||
						$cRedis->zscore(self::KSF_PREFIX.self::KSF_LOTTERY_PREFIX.$mobile, 2))
					{
						$turnTable[3] += $turnTable[1];
						$turnTable[1] = 0;
					}
					if ($cRedis->zscore(self::KSF_PREFIX.self::KSF_LOTTERY_PREFIX.$mobile, 3))
					{
						$turnTable[3] += $turnTable[2];
						$turnTable[2] = 0;
					}
	//				$turnTable = [1, 1, 1, 0];
					$rateSum = array_reduce($turnTable, function($out,$v){return $out+$v;}, 0);
					$random = rand(1, $rateSum);
					$tmp = 0;
					foreach ($turnTable as $key=>$rate)
					{
						if ($random <= $rate+$tmp)
						{
							//中奖
							$lotteryId = $key + 1;
							$this->takePrize($mobile, $lotteryId);
							break;
						}
						else
						{
							$tmp += $rate;
						}
					}
				}
			}
			catch (\Exception $e)
			{
				\Log::error('kangshifu lottery prize err:'.$e->getMessage());
			}
			Util::redisUnlock($lockKey);
		}
		else
		{
			return Util::getErrorJson(ExceptionConstants::CODE_PARAM, "请填写手机号");
		}
		return Util::getSuccessJson("success", ['result'=>$lotteryId]);
	}
	private function takePrize($mobile, $lotteryId)
	{
		//1、24号门票 2、21号门票 3、观看卷 4、未中奖
		$cRedis = \Redis::connection();

		if (1 == $lotteryId)
			$cRedis->incr(self::KSF_PREFIX.self::HAVE_FIRST_PRIZE);
		elseif (2 == $lotteryId)
			$cRedis->incr(self::KSF_PREFIX.self::HAVE_SECOND_PRIZE);

		$timestamp = (new \DateTime("now"))->getTimestamp();
		$cRedis->zadd(self::KSF_PREFIX.self::KSF_LOTTERY_PREFIX.$mobile, $timestamp, $lotteryId);
	}
	public function getLotteryResult(Request $req)
	{
		$list = [];
		$mobile = $this->getMobile($req);
		if (!empty($mobile))
		{
			$cRedis = \Redis::connection();
			$list = $cRedis->zrevrange(self::KSF_PREFIX.self::KSF_LOTTERY_PREFIX.$mobile, 0, -1);
			return Util::getSuccessJson("success", ['list'=>$list]);
		}
		else
		{
			$blurryKey = self::KSF_PREFIX.self::KSF_LOTTERY_PREFIX;
			$list = [];
			$aPregOut = array();
			$cRedis = \Redis::connection();
			$zsetKeys = $cRedis->keys(self::KSF_PREFIX.self::KSF_LOTTERY_PREFIX.'*');
			foreach ($zsetKeys as $zsetKey)
			{
				preg_match("/^$blurryKey(\d*)$/", $zsetKey, $aPregOut);
				if (!empty($aPregOut[1]))
				{
					$mobile = $aPregOut[1];
					$name = $cRedis->hget(self::KSF_PREFIX.$mobile, self::USER_NAME);
					$list[] = [
						'mobile' => $mobile,
						'name' => $name,
						'prize' => $cRedis->zrevrange($zsetKey, 0, -1)
					];
				}
			}
			return Util::getSuccessJson("success", ['list'=>$list]);
		}
	}
	public function getLotteryExcel(Request $req)
	{
		\Excel::create("Lottery", function($excel){
			$excel->sheet('24',function($sheet){

				$sheet->appendRow(['手机号', '姓名']);

				$blurryKey = self::KSF_PREFIX.self::KSF_LOTTERY_PREFIX;
				$aPregOut = array();
				$cRedis = \Redis::connection();
				$zsetKeys = $cRedis->keys(self::KSF_PREFIX.self::KSF_LOTTERY_PREFIX.'*');
				foreach ($zsetKeys as $zsetKey)
				{
					preg_match("/^$blurryKey(\d*)$/", $zsetKey, $aPregOut);
					if (!empty($aPregOut[1]))
					{
						$aPrize = $cRedis->zrevrange($zsetKey, 0, -1);
						foreach ($aPrize as $prize)
						{
							if (1 == $prize)
							{
								$mobile = $aPregOut[1];
								$name = $cRedis->hget(self::KSF_PREFIX.$mobile, self::USER_NAME);
								$sheet->appendRow([$mobile, $name]);
							}
						}
					}
				}
			});
			$excel->sheet('10',function($sheet){

				$sheet->appendRow(['手机号', '姓名']);

				$blurryKey = self::KSF_PREFIX.self::KSF_LOTTERY_PREFIX;
				$aPregOut = array();
				$cRedis = \Redis::connection();
				$zsetKeys = $cRedis->keys(self::KSF_PREFIX.self::KSF_LOTTERY_PREFIX.'*');
				foreach ($zsetKeys as $zsetKey)
				{
					preg_match("/^$blurryKey(\d*)$/", $zsetKey, $aPregOut);
					if (!empty($aPregOut[1]))
					{
						$aPrize = $cRedis->zrevrange($zsetKey, 0, -1);
						foreach ($aPrize as $prize)
						{
							if (2 == $prize)
							{
								$mobile = $aPregOut[1];
								$name = $cRedis->hget(self::KSF_PREFIX.$mobile, self::USER_NAME);
								$sheet->appendRow([$mobile, $name]);
							}
						}
					}
				}
			});
			$excel->sheet('watch',function($sheet){

				$sheet->appendRow(['手机号', '姓名']);

				$blurryKey = self::KSF_PREFIX.self::KSF_LOTTERY_PREFIX;
				$aPregOut = array();
				$cRedis = \Redis::connection();
				$zsetKeys = $cRedis->keys(self::KSF_PREFIX.self::KSF_LOTTERY_PREFIX.'*');
				foreach ($zsetKeys as $zsetKey)
				{
					preg_match("/^$blurryKey(\d*)$/", $zsetKey, $aPregOut);
					if (!empty($aPregOut[1]))
					{
						$aPrize = $cRedis->zrevrange($zsetKey, 0, -1);
						foreach ($aPrize as $prize)
						{
							if (3 == $prize)
							{
								$mobile = $aPregOut[1];
								$name = $cRedis->hget(self::KSF_PREFIX.$mobile, self::USER_NAME);
								$sheet->appendRow([$mobile, $name]);
							}
						}
					}
				}
			});
		})->export('xls');
	}

	public function getMobileCode(Request $req)
	{
		$mobile = $req->input('mobile', '');
		if (false == self::canWatch($mobile))
			return Util::getErrorJson(ExceptionConstants::CODE_PARAM, '未中奖号码');

		$code = self::getCode($mobile);
		if (empty($code))
			$code = self::generateCode($mobile);

		if (!empty($code))
			(new YuntongxunService())->sendSmsKsf($mobile, $code);
//			(new YuntongxunService())->sendSmsTemplate($mobile, [$code], 8888);

		return Util::getSuccessJson("success", []);
	}
	public function getVerifyCode(Request $req)
	{
		$mobile = $req->input('mobile', '');
		if (false == self::canWatch($mobile))
			return Util::getErrorJson(ExceptionConstants::CODE_PARAM, '未中奖号码');

		$code = $req->input('code', '');
		if (self::getCode($mobile) === $code)
		{
			return Util::getSuccessJson("success", ['url'=>'http://xxx']);
		}
		else
		{
			return Util::getErrorJson(ExceptionConstants::CODE_PARAM, ExceptionConstants::MSG_VERIFY_CODE_ERROR);
		}
	}
	private static function generateCode($mobile)
	{
		$code = mt_rand(100000, 999999);

		$cRedis = \Redis::connection();
		$codeKey = self::KSF_PREFIX.$mobile.':'.self::USER_CODE;
		$result = $cRedis->setnx($codeKey, $code);
		if (1 == $result)
			$cRedis->expire($codeKey, 60*15);
		else
			$code = 0;
		return $code;
	}
	private static function getCode($mobile)
	{
		$cRedis = \Redis::connection();
		return $cRedis->get(self::KSF_PREFIX.$mobile.':'.self::USER_CODE);
	}
	private static function canWatch($mobile)
	{
		$cRedis = \Redis::connection();
		$time = $cRedis->zscore(self::KSF_PREFIX.self::KSF_LOTTERY_PREFIX.$mobile, 3);
		return empty($time)? false: true;
	}
}