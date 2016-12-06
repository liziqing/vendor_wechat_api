<?php namespace VendorWechat\Exceptions;

class ExceptionConstants
{
	const CODE_AUTH = 100;
	const CODE_NOT_LOGIN = 101;
	const CODE_PARAM = 200;
	const CODE_FREQUENCY = 300;
	const CODE_SYSTEM = 400;
	const CODE_WX_ERROR = 401;
	const CODE_YUNTONXUN_ERROR = 402;

	const MSG_SYSTEM = "系统繁忙，请稍后";
	const MSG_PARAM = "参数错误";
	const MSG_VERIFY_CODE_SENDED = "验证码已经发送，60s内请勿重试";
	const MSG_VERIFY_CODE_ERROR = "验证码错误";
	const MSG_USER_EMPTY = "没有该用户";
	const MSG_NOT_LOGIN = "未登陆";
	const MSG_USER_EXSIST = "用户已存在";
	const MSG_INVITE_CODE_NOT_EXSIST = "邀请码不存在";
	const MSG_EMPTY_CID = "cid不能为空";
}