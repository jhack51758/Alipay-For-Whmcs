<?php

# Required File Includes
require("../../../init.php");
class alipay_notify {
	var $gateway;           //支付接口
	var $security_code;  	//安全校验码
	var $partner;           //合作伙伴ID
	var $sign_type;         //加密方式 系统默认
	var $mysign;            //签名
	var $_input_charset;    //字符编码格式
	var $transport;         //访问模式
	function alipay_notify($partner,$security_code,$sign_type = "MD5",$_input_charset = "GBK",$transport= "https") {
		$this->partner        = $partner;
		$this->security_code  = $security_code;
		$this->sign_type      = $sign_type;
		$this->mysign         = "";
		$this->_input_charset = $_input_charset ;
		$this->transport      = $transport;
		if($this->transport == "https") {
			$this->gateway = "https://www.alipay.com/cooperate/gateway.do?";
		}else $this->gateway = "http://notify.alipay.com/trade/notify_query.do?";
	}
/****************************************对notify_url的认证*********************************/
	function notify_verify() {
		if($this->transport == "https") {
			$veryfy_url = $this->gateway. "service=notify_verify" ."&partner=" .$this->partner. "&notify_id=".$_POST["notify_id"];
		} else {
			$veryfy_url = $this->gateway. "partner=".$this->partner."&notify_id=".$_POST["notify_id"];
		}
		$veryfy_result = $this->get_verify($veryfy_url);
		$post          = $this->para_filter($_POST);
		$sort_post     = $this->arg_sort($post);
		while (list ($key, $val) = each ($sort_post)) {
			$arg.=$key."=".$val."&";
		}
		$prestr = substr($arg,0,count($arg)-2);  //去掉最后一个&号
		$this->mysign = $this->sign($prestr.$this->security_code);
		////log_result("notify_url_log:sign=".$_POST["sign"]."&mysign=".$this->mysign."&".$this->charset_decode(implode(",",$_POST),$this->_input_charset ));
		if (eregi("true$",$veryfy_result) && $this->mysign == $_POST["sign"])  {
			return true;
		} else return false;
	}
/*******************************************************************************************/

/**********************************对return_url的认证***************************************/
	function return_verify() {
		$sort_get= $this->arg_sort($_GET);
		while (list ($key, $val) = each ($sort_get)) {
			if($key != "sign" && $key != "sign_type")
			$arg.=$key."=".$val."&";
		}
		$prestr = substr($arg,0,count($arg)-2);  //去掉最后一个&号
		$this->mysign = $this->sign($prestr.$this->security_code);
		while (list ($key, $val) = each ($_GET)) {
		$arg_get.=$key."=".$val."&";
		}
		//log_result("return_url_log=".$_GET["sign"]."&".$this->mysign."&".$this->charset_decode(implode(",",$_GET),$this->_input_charset ));
		if ($this->mysign == $_GET["sign"])  return true;
		else return false;
	}
/*******************************************************************************************/

	function get_verify($url,$time_out = "60") {
		$urlarr     = parse_url($url);
		$errno      = "";
		$errstr     = "";
		$transports = "";
		if($urlarr["scheme"] == "https") {
			$transports = "ssl://";
			$urlarr["port"] = "443";
		} else {
			$transports = "tcp://";
			$urlarr["port"] = "80";
		}
		$fp=@fsockopen($transports . $urlarr['host'],$urlarr['port'],$errno,$errstr,$time_out);
		if(!$fp) {
			die("ERROR: $errno - $errstr<br />\n");
		} else {
			fputs($fp, "POST ".$urlarr["path"]." HTTP/1.1\r\n");
			fputs($fp, "Host: ".$urlarr["host"]."\r\n");
			fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
			fputs($fp, "Content-length: ".strlen($urlarr["query"])."\r\n");
			fputs($fp, "Connection: close\r\n\r\n");
			fputs($fp, $urlarr["query"] . "\r\n\r\n");
			while(!feof($fp)) {
				$info[]=@fgets($fp, 1024);
			}
			fclose($fp);
			$info = implode(",",$info);
			while (list ($key, $val) = each ($_POST)) {
				$arg.=$key."=".$val."&";
			}
			//log_result("return_url_log=".$url.$this->charset_decode($info,$this->_input_charset));
			//log_result("return_url_log=".$this->charset_decode($arg,$this->_input_charset));
			return $info;
		}
	}

	function arg_sort($array) {
		ksort($array);
		reset($array);
		return $array;
	}

	function sign($prestr) {
		$sign='';
		if($this->sign_type == 'MD5') {
			$sign = md5($prestr);
		}elseif($this->sign_type =='DSA') {
			//DSA 签名方法待后续开发
			die("DSA 签名方法待后续开发，请先使用MD5签名方式");
		}else {
			die("支付宝暂不支持".$this->sign_type."类型的签名方式");
		}
		return $sign;
	}
/***********************除去数组中的空值和签名模式*****************************/
	function para_filter($parameter) {
		$para = array();
		while (list ($key, $val) = each ($parameter)) {
			if($key == "sign" || $key == "sign_type" || $val == "")continue;
			else	$para[$key] = $parameter[$key];
		}
		return $para;
	}
/********************************************************************************/

/******************************实现多种字符编码方式*****************************/
	function charset_encode($input,$_output_charset ,$_input_charset ="utf-8" ) {
		$output = "";
		if(!isset($_output_charset) )$_output_charset  = $this->parameter['_input_charset'];
		if($_input_charset == $_output_charset || $input ==null ) {
			$output = $input;
		} elseif (function_exists("mb_convert_encoding")){
			$output = mb_convert_encoding($input,$_output_charset,$_input_charset);
		} elseif(function_exists("iconv")) {
			$output = iconv($_input_charset,$_output_charset,$input);
		} else die("sorry, you have no libs support for charset change.");
		return $output;
	}
/********************************************************************************/

/******************************实现多种字符解码方式******************************/
	function charset_decode($input,$_input_charset ,$_output_charset="utf-8"  ) {
		$output = "";
		if(!isset($_input_charset) )$_input_charset  = $this->_input_charset ;
		if($_input_charset == $_output_charset || $input ==null ) {
			$output = $input;
		} elseif (function_exists("mb_convert_encoding")){
			$output = mb_convert_encoding($input,$_output_charset,$_input_charset);
		} elseif(function_exists("iconv")) {
			$output = iconv($_input_charset,$_output_charset,$input);
		} else die("sorry, you have no libs support for charset changes.");
		return $output;
	}
/*********************************************************************************/
}
function  log_result($word) {
	$fp = fopen("/tmp/alipay_log.txt","a");
	flock($fp, LOCK_EX) ;
	fwrite($fp,$word."：执行日期：".strftime("%Y%m%d%H%I%S",time())."\t\n");
	flock($fp, LOCK_UN);
	fclose($fp);
}

$gatewaymodule = "alipay"; # Enter your gateway module name here replacing template
$GATEWAY = getGatewayVariables($gatewaymodule);
if (!$GATEWAY["type"]) die("Module Not Activated"); # Checks gateway module is active before accepting callback

$_input_charset  = "utf-8";   //字符编码格式 目前支持 GBK 或 utf-8
$sign_type       = "MD5";     //加密方式 系统默认(不要修改)
$transport       = "https";   //访问模式,你可以根据自己的服务器是否支持ssl访问而选择http以及https访问模式(系统默认,不要修改)
$gatewayPID = $GATEWAY['partnerID'];
$gatewaySELLER_EMAIL = $GATEWAY['seller_email'];
$gatewaySECURITY_CODE = $GATEWAY['security_code'];
$alipay = new alipay_notify($gatewayPID,$gatewaySECURITY_CODE,$sign_type,$_input_charset,$transport);
$verify_result = $alipay->return_verify();
if(!$verify_result) {
	logTransaction($GATEWAY["name"],$_GET,"Unsuccessful");
}
else{
	# Get Returned Variables
	$status = $_GET['trade_status'];    //获取支付宝传递过来的交易状态
	$invoiceid = $_GET['out_trade_no']; //获取支付宝传递过来的订单号
	$transid = $_GET['trade_no'];       //获取支付宝传递过来的交易号
	$amount = $_GET['total_fee'];       //获取支付宝传递过来的总价格
	$fee = 0;

	if($status == 'TRADE_FINISHED' || $status == 'TRADE_SUCCESS') {
		$invoiceid = checkCbInvoiceID($invoiceid,$GATEWAY["name"]); # Checks invoice ID is a valid invoice number or ends processing
		//checkCbTransID($transid); # Checks transaction number isn't already in the database and ends processing if it does

		$table = "tblaccounts";
		$fields = "transid";
		$where = array("transid"=>$transid);
		$result = select_query($table,$fields,$where);
		$data = mysql_fetch_array($result);
		if(!$data){
			addInvoicePayment($invoiceid,$transid,$amount,$fee,$gatewaymodule);
			logTransaction($GATEWAY["name"],$_GET,"Successful");
		}
	}

}
$url=$GATEWAY['systemurl'];


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>Payment OK</title>
</head>
<body style="margin:0;padding:0;">
	<div style="text-align:center;padding:20% 0 0 0;margin:0;">
			<table width="350" border="0" align="center" cellpadding="0" cellspacing="0" style="background:#ccccff;border:1px solid #999999;margin:5px auto;">
			<tr height="47" valign="middle">
				<td align="left">

				</td>
			</tr>
			<tr>
				<td align="center" style="padding:8px 4px;">
					<table border="0" cellpadding="4" cellspacing="0">
						<tr>
							<td colspan="2" align="center">
								你的支付已经完成,如果支付成功,那资金将会很快进入你的帐户处理.如果10分钟内还未处理,请提交服务单给我们财务人员
							</td>
						</tr>
						<tr height="47" ><td colspan="4" align="center">按<a href="<?php echo $url ?>" target="">这里</a>返回客户系统</td></tr>
					</table>
				</td>
			</tr>
		</table>

	</div>
</body>
</html>
