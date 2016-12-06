<?php

namespace VendorWechat\Http\Controllers;

use Illuminate\Http\Request;
use VendorWechat\Http\Controllers\Controller;
use VendorWechat\Util\Util;
use VendorWechat\Services\WxService;

class WeixinController extends Controller
{

    /**
     * Create a new password controller instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    public function getOauthTokenNoSession(Request $request)
    {
        $alias = $request->get('alias');
        $code = $request->input('code');

        $ws = new WxService($alias);

        $resultArray = $ws->getOAuthAccessToken($code);

        $accessToken = $resultArray['access_token'];
        $refreshToken = $resultArray['refresh_token'];
        $openid = $resultArray['openid'];

        $expiresIn = $resultArray["expires_in"];
        $expiresIn = $expiresIn / 60 - 10;

        $cookieData = array(
            "access_token" => $accessToken,
            "refresh_token" => $refreshToken,
            "openid" => $openid,
            "code" => $code
        );

//         $appId = $ws->getAppId();

//         $cookieStr = json_encode($cookieData);

        return Util::getSuccessJson("success", $cookieData);

    }

    public function getUserinfo(Request $request)
    {
        $accessToken = $request->input('token');
        $alias = $request->get('alias');

        $openId = $request->input('openid');

        $ws = new WxService($alias);

        $ret = $ws->getOAuthUserInfo($accessToken, $openId);

        return Util::getSuccessJson('success', $ret);
    }

    public function getSig(Request $request)
    {
        $alias = $request->get('alias');
        $ws = new WxService($alias);

        $nonceStr = $ws->generateNonceStr();//$request->getParameter('nonceStr');
        $url = urldecode($request->input('url'));
        $timestamp = strval(time());

        $signature = $ws->getSignature($timestamp, $nonceStr, $url);

        $data = array(
            'nonceStr' => $nonceStr,
            //'url' => $url,
            'timestamp' => $timestamp,
            'signature' => $signature
        );

        return Util::getSuccessJson('success', $data);
    }
}
