<?php
/**
 * User: leon
 * Date: 2017/12/4 0004  上午 10:20
 */
namespace VendorWechat\Http\Controllers;

use Illuminate\Http\Request;
use VendorWechat\Util\Util;
use VendorWechat\Exceptions\ExceptionConstants;

class PpgZSController extends Controller
{
	/* 数据结构，使用reids的hash */
	const PPG_CERTIFICATE_PREFIX = 'ppg_cert_';
	const PPG_CERTIFICATE_IMAGEURL = 'image_url';
	const PPG_CERTIFICATE_DESC = 'desc';

	public function anyAllCertificate(Request $req)
	{
		$iCursor = 0;
		$cRedis = \Redis::connection();
		$list = [];
		do
		{
			list($iCursor, $asKey) = $cRedis->scan($iCursor, ['MATCH'=>self::PPG_CERTIFICATE_PREFIX.'*']);
			foreach ($asKey as $sKey)
			{
				preg_match('/^'.self::PPG_CERTIFICATE_PREFIX.'(.*)$/', $sKey, $aPregOut);
				if (empty($aPregOut[1]) || 'id' == $aPregOut[1])
					continue;
				$id = $aPregOut[1];
				$cert = $cRedis->hgetall($sKey);
				$cert['id'] = $id;
				$list[] = $cert;
			}
		}while(0 != $iCursor);
		return Util::getSuccessJson("success", ['list'=>$list]);
	}
	public function postUpdateCert(Request $req)
	{
		$imageUrl = $req->input('image_url', '');
		$desc = $req->input('desc', '');
		$id = $req->input('id', 0);

		$cRedis = \Redis::connection();
		if (0 == $id) {
//			return Util::getErrorJson(ExceptionConstants::CODE_PARAM, "错误原因");
			//创建新纪录, id管理
			$newId = $cRedis->incr(self::PPG_CERTIFICATE_PREFIX.'id');
			$cRedis->hmset(self::PPG_CERTIFICATE_PREFIX.$newId, [
				self::PPG_CERTIFICATE_IMAGEURL=>$imageUrl,
				self::PPG_CERTIFICATE_DESC=>$desc
			]);
		} else {
			if ($cRedis->exists(self::PPG_CERTIFICATE_PREFIX.$id)) {
				$cRedis->hmset(self::PPG_CERTIFICATE_PREFIX.$id, [
					self::PPG_CERTIFICATE_IMAGEURL=>$imageUrl,
					self::PPG_CERTIFICATE_DESC=>$desc
				]);
			}
		}
		return Util::getSuccessJson("success", []);
	}
	public function postDelCert(Request $req)
	{
		$id = $req->input('id', 0);

		$cRedis = \Redis::connection();
		if ($cRedis->exists(self::PPG_CERTIFICATE_PREFIX.$id)) {
//			$cert = $cRedis->hmget(self::PPG_CERTIFICATE_PREFIX.$id, [self::PPG_CERTIFICATE_IMAGEURL,self::PPG_CERTIFICATE_DESC]);
//			$cRedis->hmset('del_'.self::PPG_CERTIFICATE_PREFIX.$id, $cert);
			$cRedis->del(self::PPG_CERTIFICATE_PREFIX.$id);
		}
		return Util::getSuccessJson("success", []);
	}
	public function getManage(Request $req)
	{
		return view('ppg', []);
	}


}