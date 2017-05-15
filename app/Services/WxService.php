<?php namespace VendorWechat\Services;

use Curl\Curl;
use VendorWechat\Exceptions\SMException;
use VendorWechat\Exceptions\ExceptionConstants;

class WxService{

    const WECHAT_ACCESS_TOKEN_MEMCACHE_TIME = 5400;
    const GRANT_TYPE = 'client_credential';
    const GET_TOKEN_URL = 'https://api.weixin.qq.com/cgi-bin/token';
    const API_BASE_URL = 'https://api.weixin.qq.com/';
    const UPLOAD_PIC_URL = 'http://file.api.weixin.qq.com/cgi-bin/media/upload';

    protected $alias = '';
    protected $appId = 'wxe5613adadd2e9d16';
    protected $appSecret = '8553bf7429487a1aacd32df0a688c3aa';

//     const SERVICE_APP_ID = 'wxe5613adadd2e9d16';
//     const SERVICE_APP_SECRET = '8553bf7429487a1aacd32df0a688c3aa';

//     const BETA_SERVICE_APP_ID = 'wx0bc5cdae6cb5c06c';
//     const BETA_SERVICE_APP_SECRET = '9c22276f8e8c4e22200c92585e197784';

//     const APP_APP_ID = 'wx64b3e2ac8464e920';
//     const APP_APP_SECRET = '4e45844fcd1cc2ae32987bc93dffbee3';

//     const PAY_KEY = 'b1bdf5a339ac6338ff40b1c9bc4bacb0';

//     const XIAOSHIJI_PAY_KEY = '0c99b9a7da798ea718dccafa0a8b3cef';
//     const XIAOSHIJI_APP_ID = 'wxa66b66bdc5105725';
//     const XIAOSHIJI_APP_SECRET = 'a67331bfab93fcb2fc262ddd06545003';

    const TOKEN_CACHE_KEY = 'wx_api_token';
    const TICKET_CACHE_KEY = 'wx_jsapi_ticket';
    const TICKET_CODE_TYPE = "CODE_TYPE_QRCODE";

    static $refreshErrCodes = array(40001, 42001);

    static $apiUris = array(
        'get_base_userinfo' => 'cgi-bin/user/info',
        'get_ticket' => 'cgi-bin/ticket/getticket',
        'oauth_access_token' => 'sns/oauth2/access_token',
        'set_whitelist' => 'card/testwhitelist/set',
        'create_card' => 'card/create',
        'update_card' => 'card/update',
        'get_qrcode_ticket' => 'card/qrcode/create',
        'update_ticket_info' => 'card/meetingticket/updateuser',
        'get_card_info' => 'card/get',
        'consume_code' => 'card/code/consume'
    );

    static $aliasMap = [
        'pt_christmas' => ['appid' => 'wx9a8bd58d910b0460', 'secret' => '220c4abbdda01e60f9ecf50156ccfaf2']
    ];

    public function __construct($alias)
    {
        $this->alias = $alias;
        if(isset(self::$aliasMap[$alias]))
        {
            $this->appId = self::$aliasMap[$alias]['appid'];
            $this->appSecret = self::$aliasMap[$alias]['secret'];
        }
    }

    public function getOAuthAccessToken($code)
    {
        $params = [
            "grant_type" => "authorization_code",
            "code" => $code,
            "secret" => $this->appSecret,
            "appid" => $this->appId
        ];

        $curl = new Curl();

        $curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $curl->setOpt(CURLOPT_HTTPHEADER, array('Content-Type:application/json;charset=utf-8'));

        $curl->setJsonDecoder(function($response) {
            $json_array = json_decode($response, true);
            if (!($json_array === null)) {
                $response = $json_array;
            }
            return $response;
        });

        $curl->get(self::API_BASE_URL.self::$apiUris['oauth_access_token'], $params);

        if ($curl->error) {
            throw new SMException('request oauth2 token error', ExceptionConstants::CODE_SYSTEM);
        }
        else {
            $ret = $curl->response;

            if(!is_array($ret))
            {
                $ret = json_decode($ret, true);
            }

            if (isset($ret['errcode']) && $ret['errcode']) {

                throw new SMException('request oauth error '.$ret['errmsg']." ".intval($ret['errcode']), ExceptionConstants::CODE_SYSTEM);
            }
            else {
                return $ret;
            }
        }
    }

    public function generateNonceStr()
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $maxPos = strlen($chars);
        $noceStr = "";
        for ($i = 0; $i < 32; $i++) {
            $pos = mt_rand(0, ($maxPos - 1));

            $noceStr .= $chars[$pos];
        }

        return $noceStr;
    }

    public function getAccessToken($forceRequest = false) {

        $cacheKey = self::TOKEN_CACHE_KEY . '_' . $this->appId;
        if (!$forceRequest)
        {
            $token = \Cache::get($cacheKey);
            if ($token !== false && !empty($token))
            {
                return $token;
            }
        }
        $ret = $this->requestToken();
        $token = $ret['access_token'];
        $expirs = floor((intval($ret['expires_in']) - 200)/60);
        \Cache::put($cacheKey, $token, $expirs);

        return $token;
    }

    private function requestToken() {
        $params = array(
            'grant_type' => 'client_credential',
            'appid' => $this->appId,
            'secret' => $this->appSecret,
        );

        $curl = new Curl();
        $curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);

        $curl->setJsonDecoder(function($response) {
            $json_array = json_decode($response, true);
            if (!($json_array === null)) {
                $response = $json_array;
            }
            return $response;
        });

        $curl->get(self::GET_TOKEN_URL, $params);

        if ($curl->error) {
            throw new SMException('request token error', ExceptionConstants::CODE_SYSTEM);
        }
        else {
            $ret = $curl->response;

            if (isset($ret['errcode'])) {
                throw new SMException('request token error '.$ret['errmsg']." ".intval($ret['errcode']), ExceptionConstants::CODE_SYSTEM);
            }
            else {
                return $ret;
            }
        }
    }

    public function generateAddrSign($url, $accessToken, $nonceStr, $timestamp)
    {
        $data = array(
            "appid" => $this->appId,
            "url" => $url,
            "timestamp" => $timestamp,
            "noncestr" => $nonceStr,
            "accesstoken" => $accessToken
        );

        ksort($data, SORT_STRING);

        $signString = "";
        foreach($data as $key => $value)
        {
            $signString .= $key."=".$value."&";
        }
        $signString = rtrim($signString, "&");
        $signString = sha1($signString);

        return $signString;
    }

    public function getSignature($timestamp, $nonstr, $url) {
        if ('ksf' == $this->alias)
        {
            $ticket = $this->getgetKsfTicket();
        }
        else
        {
            $ticketType = 'jsapi';
            $ticket = $this->getApiTicket($ticketType);
        }

        $data = array(
            "noncestr" => $nonstr,
            "url" => $url,
            "timestamp" => $timestamp,
            "jsapi_ticket" => $ticket//$this->getApiTicket($ticketType)
        );

        ksort($data, SORT_STRING);

        $signString = "";
        foreach($data as $key => $value)
        {
            $signString .= $key."=".$value."&";
        }
        $signString = rtrim($signString, "&");

        $signature = sha1($signString);

        return $signature;
    }

    private function getApiTicket($ticketType, $forceRequest = false) {

        $cacheKey = self::TICKET_CACHE_KEY . '_' . $ticketType . '_' . $this->appId;

        if (!$forceRequest)
        {
            $token = \Cache::get($cacheKey);
            if ($token !== false && !empty($token))
            {
                return $token;
            }
        }

        $ret = $this->requestTicket($ticketType);
        $token = $ret['ticket'];
        $expirs = floor((intval($ret['expires_in']) - 200)/60);
        \Cache::put($cacheKey, $token, $expirs);

        return $token;
    }

    private function getgetKsfTicket()
    {
        $curl = new Curl();
        $curl->get("http://ptsc.net-show.cn/CheckAll.ashx", ["actiontype"=>"GetJT"]);
        return $curl->response;
    }

    public function getBaseUserinfo($openId)
    {
        $uri = self::$apiUris['get_base_userinfo'];
        $params = array('openid' => $openId);
        $ret = $this->requestApi($uri, $params);

        return $ret;
    }

    private function requestTicket($type) {
        $uri = self::$apiUris['get_ticket'];
        $params = array('type' => $type);
        $ret = $this->requestApi($uri, $params);

        return $ret;
    }

    private function requestApi($uri, $params, $isPost = FALSE, $retry = FALSE) {

        $url = self::API_BASE_URL . $uri . '?access_token=' . $this->getAccessToken($retry);

        $curl = new Curl();
        $curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $curl->setOpt(CURLOPT_HTTPHEADER, array('Content-Type:application/json;charset=utf-8'));

        $curl->setJsonDecoder(function($response) {
            $json_array = json_decode($response, true);
            if (!($json_array === null)) {
                $response = $json_array;
            }
            return $response;
        });

        if ($isPost) {
            $curl->post($url, $params);
        }else{
            $url = $url. "&".http_build_query($params);

            $curl->get($url, []);
        }

        if ($curl->error) {
            throw new SMException('request token error', ExceptionConstants::CODE_SYSTEM);
        }
        else {
            $ret = $curl->response;

            if (isset($ret['errcode']) && $ret['errcode']) {

                if (in_array($ret['errcode'], self::$refreshErrCodes) && !$retry) {//如果access token无效或超时，重新拉取并再请求一次
                    return $this->requestApi($uri, $params, $isPost, TRUE);
                }
                else {
                    throw new SMException('request token error '.$ret['errmsg']." ".intval($ret['errcode']), ExceptionConstants::CODE_SYSTEM);
                }

                throw new SMException('request token error '.$ret['errmsg']." ".intval($ret['errcode']), ExceptionConstants::CODE_SYSTEM);
            }
            else {
                return $ret;
            }
        }

    }

    public function getAppId()
    {
        return $this->appId;
    }

    public function getOAuthUserInfo($accessToken, $openId)
    {
        $url = self::API_BASE_URL . 'sns/userinfo?access_token=' . $accessToken."&openid=" . $openId . "&lang=zh_CN";

        $curl = new Curl();
        $curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $curl->setOpt(CURLOPT_HTTPHEADER, array('Content-Type:application/json;charset=utf-8'));

        $curl->setJsonDecoder(function($response) {
            $json_array = json_decode($response, true);
            if (!($json_array === null)) {
                $response = $json_array;
            }
            return $response;
        });


        $curl->get($url, []);

        if ($curl->error) {
            throw new SMException('request token error', ExceptionConstants::CODE_SYSTEM);
        }
        else {
            $ret = $curl->response;

            if(!is_array($ret))
            {
                $ret = json_decode($ret, true);
            }

            if (isset($ret['errcode']) && $ret['errcode']) {

                throw new SMException('request token error '.$ret['errmsg']." ".intval($ret['errcode']), ExceptionConstants::CODE_SYSTEM);
            }
            else {
                return $ret;
            }
        }
    }

    public function generatePaySign($params, $payKey)
    {
        ksort($params, SORT_STRING);

        $signString = "";
        foreach($params as $key => $value)
        {
            if($value != "" && $value != null)
            {
                $signString .= $key."=".$value."&";
            }
        }
        $signString .= "key=$payKey";

        $signString = strtoupper(md5($signString));

        return $signString;
    }
}
