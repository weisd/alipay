<?php namespace Weisd\Alipay;

use Config;

class Alipay {
	use Functions;

	/**
	 *支付宝网关地址（新）
	 */
	public static $alipay_gateway_new = 'https://mapi.alipay.com/gateway.do?';
	/**
	 * HTTPS形式消息验证地址
	 */
	public static $https_verify_url = 'https://mapi.alipay.com/gateway.do?service=notify_verify&';
	/**
	 * HTTP形式消息验证地址
	 */
	public static $http_verify_url = 'http://notify.alipay.com/trade/notify_query.do?';
	public static $alipay_config;

	public static $_instance;

	public static function instance() {
		if (self::$_instance === null) {
			self::$alipay_config = Config::get('alipay::config');
			self::$_instance = new Alipay();
		}

		return self::$_instance;
	}

	// function __construct($alipay_config) {
	// 	$this->alipay_config = $alipay_config;
	// }
	// function AlipayNotify($alipay_config) {

	// 	$this->__construct($alipay_config);
	// }
	/**
	 * 针对notify_url验证消息是否是支付宝发出的合法消息
	 * @return 验证结果
	 */
	public static function verifyNotify() {
		if (empty($_POST)) {
			//判断POST来的数组是否为空
			return false;
		} else {
			//生成签名结果
			$isSign = self::getSignVeryfy($_POST, $_POST["sign"]);
			//获取支付宝远程服务器ATN结果（验证是否是支付宝发来的消息）
			$responseTxt = 'true';
			if (!empty($_POST["notify_id"])) {$responseTxt = self::getResponse($_POST["notify_id"]);}

			//写日志记录
			//if ($isSign) {
			//	$isSignStr = 'true';
			//}
			//else {
			//	$isSignStr = 'false';
			//}
			//$log_text = "responseTxt=".$responseTxt."\n notify_url_log:isSign=".$isSignStr.",";
			//$log_text = $log_text.createLinkString($_POST);
			//logResult($log_text);

			//验证
			//$responsetTxt的结果不是true，与服务器设置问题、合作身份者ID、notify_id一分钟失效有关
			//isSign的结果不是true，与安全校验码、请求时的参数格式（如：带自定义参数等）、编码格式有关
			if (preg_match("/true$/i", $responseTxt) && $isSign) {
				return true;
			} else {
				return false;
			}
		}
	}

	/**
	 * 针对return_url验证消息是否是支付宝发出的合法消息
	 * @return 验证结果
	 */
	public static function verifyReturn() {
		if (empty($_GET)) {
			//判断POST来的数组是否为空
			return false;
		} else {
			//生成签名结果
			$isSign = self::getSignVeryfy($_GET, $_GET["sign"]);
			//获取支付宝远程服务器ATN结果（验证是否是支付宝发来的消息）
			$responseTxt = 'true';
			if (!empty($_GET["notify_id"])) {$responseTxt = self::getResponse($_GET["notify_id"]);}

			//写日志记录
			//if ($isSign) {
			//	$isSignStr = 'true';
			//}
			//else {
			//	$isSignStr = 'false';
			//}
			//$log_text = "responseTxt=".$responseTxt."\n return_url_log:isSign=".$isSignStr.",";
			//$log_text = $log_text.createLinkString($_GET);
			//logResult($log_text);

			//验证
			//$responsetTxt的结果不是true，与服务器设置问题、合作身份者ID、notify_id一分钟失效有关
			//isSign的结果不是true，与安全校验码、请求时的参数格式（如：带自定义参数等）、编码格式有关
			if (preg_match("/true$/i", $responseTxt) && $isSign) {
				return true;
			} else {
				return false;
			}
		}
	}

	/**
	 * 获取返回时的签名验证结果
	 * @param $para_temp 通知返回来的参数数组
	 * @param $sign 返回的签名结果
	 * @return 签名验证结果
	 */
	public static function getSignVeryfy($para_temp, $sign) {
		//除去待签名参数数组中的空值和签名参数
		$para_filter = self::paraFilter($para_temp);

		//对待签名参数数组排序
		$para_sort = self::argSort($para_filter);

		//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
		$prestr = self::createLinkstring($para_sort);

		$isSgin = false;
		switch (strtoupper(trim(self::$alipay_config['sign_type']))) {
			case "MD5":
				$isSgin = self::md5Verify($prestr, $sign, self::$alipay_config['key']);
				break;
			default:
				$isSgin = false;
		}

		return $isSgin;
	}

	/**
	 * 获取远程服务器ATN结果,验证返回URL
	 * @param $notify_id 通知校验ID
	 * @return 服务器ATN结果
	 * 验证结果集：
	 * invalid命令参数不对 出现这个错误，请检测返回处理中partner和key是否为空
	 * true 返回正确信息
	 * false 请检查防火墙或者是服务器阻止端口问题以及验证时间是否超过一分钟
	 */
	public static function getResponse($notify_id) {
		$transport = strtolower(trim(self::$alipay_config['transport']));
		$partner = trim(self::$alipay_config['partner']);
		$veryfy_url = '';
		if ($transport == 'https') {
			$veryfy_url = self::$https_verify_url;
		} else {
			$veryfy_url = self::$http_verify_url;
		}
		$veryfy_url = $veryfy_url . "partner=" . $partner . "&notify_id=" . $notify_id;
		$responseTxt = self::getHttpResponseGET($veryfy_url, self::$alipay_config['cacert']);

		return $responseTxt;
	}

	/**
	 * 生成签名结果
	 * @param $para_sort 已排序要签名的数组
	 * return 签名结果字符串
	 */
	public static function buildRequestMysign($para_sort) {
		//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
		$prestr = self::createLinkstring($para_sort);

		$mysign = "";
		switch (strtoupper(trim(self::$alipay_config['sign_type']))) {
			case "MD5":
				$mysign = self::md5Sign($prestr, self::$alipay_config['key']);
				break;
			default:
				$mysign = "";
		}

		return $mysign;
	}

	/**
	 * 生成要请求给支付宝的参数数组
	 * @param $para_temp 请求前的参数数组
	 * @return 要请求的参数数组
	 */
	public static function buildRequestPara($para_temp) {
		//除去待签名参数数组中的空值和签名参数
		$para_filter = self::paraFilter($para_temp);

		//对待签名参数数组排序
		$para_sort = self::argSort($para_filter);

		//生成签名结果
		$mysign = self::buildRequestMysign($para_sort);

		//签名结果与签名方式加入请求提交参数组中
		$para_sort['sign'] = $mysign;
		$para_sort['sign_type'] = strtoupper(trim(self::$alipay_config['sign_type']));

		return $para_sort;
	}

	/**
	 * 生成要请求给支付宝的参数数组
	 * @param $para_temp 请求前的参数数组
	 * @return 要请求的参数数组字符串
	 */
	public static function buildRequestParaToString($para_temp) {
		//待请求参数数组
		$para = self::buildRequestPara($para_temp);

		//把参数组中所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串，并对字符串做urlencode编码
		$request_data = self::createLinkstringUrlencode($para);

		return $request_data;
	}

	/**
	 * 建立请求，以表单HTML形式构造（默认）
	 * @param $para_temp 请求参数数组
	 * @param $method 提交方式。两个值可选：post、get
	 * @param $button_name 确认按钮显示文字
	 * @return 提交表单HTML文本
	 */
	public static function buildRequestForm($para_temp, $method, $button_name) {
		//待请求参数数组
		$para = self::buildRequestPara($para_temp);

		$sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='" . self::$alipay_gateway_new . "_input_charset=" . trim(strtolower(self::$alipay_config['input_charset'])) . "' method='" . $method . "'>";
		while (list($key, $val) = each($para)) {
			$sHtml .= "<input type='hidden' name='" . $key . "' value='" . $val . "'/>";
		}

		//submit按钮控件请不要含有name属性
		// $sHtml = $sHtml . "<input type='submit' value='" . $button_name . "'></form>";

		$sHtml = $sHtml . "<script>document.forms['alipaysubmit'].submit();</script>";

		return $sHtml;
	}

	/**
	 * 建立请求，以模拟远程HTTP的POST请求方式构造并获取支付宝的处理结果
	 * @param $para_temp 请求参数数组
	 * @return 支付宝处理结果
	 */
	public static function buildRequestHttp($para_temp) {
		$sResult = '';

		//待请求参数数组字符串
		$request_data = self::buildRequestPara($para_temp);

		//远程获取数据
		$sResult = self::getHttpResponsePOST(self::$alipay_gateway_new, self::$alipay_config['cacert'], $request_data, trim(strtolower(self::$alipay_config['input_charset'])));

		return $sResult;
	}

	/**
	 * 建立请求，以模拟远程HTTP的POST请求方式构造并获取支付宝的处理结果，带文件上传功能
	 * @param $para_temp 请求参数数组
	 * @param $file_para_name 文件类型的参数名
	 * @param $file_name 文件完整绝对路径
	 * @return 支付宝返回处理结果
	 */
	public static function buildRequestHttpInFile($para_temp, $file_para_name, $file_name) {

		//待请求参数数组
		$para = self::buildRequestPara($para_temp);
		$para[$file_para_name] = "@" . $file_name;

		//远程获取数据
		$sResult = self::getHttpResponsePOST(self::$alipay_gateway_new, self::$alipay_config['cacert'], $para, trim(strtolower(self::$alipay_config['input_charset'])));

		return $sResult;
	}

	/**
	 * 用于防钓鱼，调用接口query_timestamp来获取时间戳的处理函数
	 * 注意：该功能PHP5环境及以上支持，因此必须服务器、本地电脑中装有支持DOMDocument、SSL的PHP配置环境。建议本地调试时使用PHP开发软件
	 * return 时间戳字符串
	 */
	public static function query_timestamp() {
		$url = self::$alipay_gateway_new . "service=query_timestamp&partner=" . trim(strtolower(self::$alipay_config['partner'])) . "&_input_charset=" . trim(strtolower(self::$alipay_config['input_charset']));
		$encrypt_key = "";

		$doc = new DOMDocument();
		$doc->load($url);
		$itemEncrypt_key = $doc->getElementsByTagName("encrypt_key");
		$encrypt_key = $itemEncrypt_key->item(0)->nodeValue;

		return $encrypt_key;
	}
}
?>