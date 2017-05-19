<?php namespace VendorWechat\Util;

use VendorWechat\Services\StoreService;
use Illuminate\Support\Facades\Request;

class Util
{
    const QINIU_ACCESS_KEY = "nR4zZ4XaxesEAhPW59BZNtWOMu4aD-pnHcg28iF6";
    const QINIU_SECRET_KEY = "uaykXj8wnZwWT3n02XG-keUat-UOUGfbGxHKEgUr";

    public static $jsonOptions = 0;

    public static function getSuccessJson($success, $data, $jsonOptions=null)
    {
        $data = ["code" => 0, "message" => $success, "data" => $data];
        if (null == $jsonOptions) {$jsonOptions = self::$jsonOptions;}
        return self::getJson($data,Request::input("format","json"),Request::input("callback","callback"),$jsonOptions);
    }

    public static function getJson($data,$format="json",$callback="callback", $jsonOptions=0)
    {
        if($format=="jsonp")
        {
            $respone =response()->jsonp($callback, $data, 200, [], $jsonOptions);
            self::$jsonOptions = 0;
        }
        else if($format=="file")
        {
            $respone = $data;//json_encode($data);
        }
        else
        {
            $respone =response()->json($data, 200, [], $jsonOptions);
            self::$jsonOptions = 0;
        }
        return $respone;
    }

    public static function getErrorJson($code, $error)
    {
        $data = ["code" => $code, "message" => $error];
        return self::getJson($data,Request::input("format","json"),Request::input("callback","callback"));
    }

    public static function redisLock($key, $secs=5)
    {
        $cRedis = \Redis::connection();
        $result = $cRedis->setnx($key, time() + $secs + 1);
        $cRedis->expire($key, $secs);
        return $result;
    }

    public static function redisUnlock($key)
    {
        $cRedis = \Redis::connection();
        $cRedis->del($key);
    }
}