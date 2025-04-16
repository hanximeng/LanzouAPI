<?php
/**
 * @package Lanzou
 * @author Filmy,hanximeng
 * @version 1.3.101
 * @Date 2025-04-16
 * @link https://hanximeng.com
 */
header('Access-Control-Allow-Origin:*');
header('Content-Type:application/json; charset=utf-8');
//默认UA
$UserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36';
$url = isset($_GET['url']) ? $_GET['url'] : "";
$pwd = isset($_GET['pwd']) ? $_GET['pwd'] : "";
$type = isset($_GET['type']) ? $_GET['type'] : "";
//判断传入链接参数是否为空
if (empty($url)) {
	die(
	    json_encode(
	        array(
	            'code' => 400,
	            'msg' => '请输入URL'
	        )
	        , JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
	    );
}
//一个简单的链接处理
$url='https://www.lanzoup.com/'.explode('.com/',$url)['1'];
$softInfo = MloocCurlGet($url);
//判断文件链接是否失效
if (strstr($softInfo, "文件取消分享了") != false) {
	die(
	    json_encode(
	        array(
	            'code' => 400,
	            'msg' => '文件取消分享了'
	        )
	        , JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
	    );
}
//取文件名称、大小
preg_match('~style="font-size: 30px;text-align: center;padding: 56px 0px 20px 0px;">(.*?)</div>~', $softInfo, $softName);
if(!isset($softName[1])) {
	preg_match('~<div class="n_box_3fn".*?>(.*?)</div>~', $softInfo, $softName);
}
preg_match('~<div class="n_filesize".*?>大小：(.*?)</div>~', $softInfo, $softFilesize);
if(!isset($softFilesize[1])) {
	preg_match('~<span class="p7">文件大小：</span>(.*?)<br>~', $softInfo, $softFilesize);
}
if(!isset($softName[1])) {
	preg_match('~var filename = \'(.*?)\';~', $softInfo, $softName);
}
if(!isset($softName[1])) {
	preg_match('~div class="b"><span>(.*?)</span></div>~', $softInfo, $softName);
}
//带密码的链接的处理
if(strstr($softInfo, "function down_p(){") != false) {
	if(empty($pwd)) {
		die(
				json_encode(
					array(
						'code' => 400,
						'msg' => '请输入分享密码'
					)
					, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
				);
	}
	preg_match_all("~'sign':'(.*?)',~", $softInfo, $segment);
	preg_match_all("~ajaxdata = '(.*?)'~", $softInfo, $signs);
	preg_match_all("/ajaxm\.php\?file=(\d+)/", $softInfo, $ajaxm);
	$post_data = array(
		"action" => "downprocess",
		"sign" => $segment[1][1],
		"p" => $pwd,
		"kd" => 1
	);
	$softInfo = MloocCurlPost($post_data, "https://www.lanzoup.com/".$ajaxm[0][0], $url);
	$softName[1] = json_decode($softInfo,JSON_UNESCAPED_UNICODE)['inf'];
} else {
	//不带密码的链接处理
	preg_match("~\n<iframe.*?name=\"[\s\S]*?\"\ssrc=\"\/(.*?)\"~", $softInfo, $link);
	//蓝奏云新版页面正则规则
	if(empty($link[1])) {
		preg_match("~<iframe.*?name=\"[\s\S]*?\"\ssrc=\"\/(.*?)\"~", $softInfo, $link);
	}
	$ifurl = "https://www.lanzoup.com/" . $link[1];
	$softInfo = MloocCurlGet($ifurl);
	preg_match_all("~wp_sign = '(.*?)'~", $softInfo, $segment);
	preg_match_all("~ajaxdata = '(.*?)'~", $softInfo, $signs);
	preg_match_all("/ajaxm\.php\?file=(\d+)/", $softInfo, $ajaxm);
	$post_data = array(
		"action" => "downprocess",
		"websignkey" => $signs[1][0],
		"signs" => $signs[1][0],
		"sign" => $segment[1][0],
		"websign" => '',
		"kd" => 1,
		"ves" => 1
	);
	$softInfo = MloocCurlPost($post_data, "https://www.lanzoup.com/".$ajaxm[0][1], $ifurl);
}
//其他情况下的信息输出
$softInfo = json_decode($softInfo, true);
if ($softInfo['zt'] != 1) {
	die(
	    json_encode(
	        array(
	            'code' => 400,
	            'msg' => $softInfo['inf']
	        )
	        , JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
	    );
}
//拼接链接
$downUrl1 = $softInfo['dom'] . '/file/' . $softInfo['url'];
//解析最终直链地址
$downUrl2 = MloocCurlHead($downUrl1,"https://developer.lanzoug.com",$UserAgent,"down_ip=1; expires=Sat, 16-Nov-2019 11:42:54 GMT; path=/; domain=.baidupan.com");
//判断最终链接是否获取成功，如未成功则使用原链接
if(strpos($downUrl2,"http") === false) {
	$downUrl = $downUrl1;
} else {
	//2025-03-17 新增后缀自定义功能 https://github.com/hanximeng/LanzouAPI/issues/26
	if(!empty($_GET['n'])){
	    preg_match_all("~(.*?)\?fn=(.*?)\\.~", $downUrl2, $rename);
	    $downUrl = $rename['0']['0'].$_GET['n'];
	}else{
	    $downUrl = $downUrl2;
	}
}
//2024-12-03 修复pid参数可能导致的服务器ip地址泄露
$downUrl=preg_replace('/pid=(.*?.)&/', '', $downUrl);
//判断是否是直接下载
if ($type != "down") {
	die(
	    json_encode(
	        array(
	            'code' => 200,
	            'msg' => '解析成功',
	            'name' => isset($softName[1]) ? $softName[1] : "",
	            'filesize' => isset($softFilesize[1]) ? $softFilesize[1] : "",
	            'downUrl' => $downUrl
	        )
	        , JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
	    );
} else {
	header("Location:$downUrl");
	die;
}
//获取下载链接函数
function MloocCurlGetDownUrl($url) {
	$header = get_headers($url,1);
	if(isset($header['Location'])) {
		return $header['Location'];
	}
	return "";
}
//CURL函数
function MloocCurlGet($url = '', $UserAgent = '') {
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
	if ($UserAgent != "") {
		curl_setopt($curl, CURLOPT_USERAGENT, $UserAgent);
	}
	curl_setopt($curl, CURLOPT_HTTPHEADER, array('X-FORWARDED-FOR:'.Rand_IP(), 'CLIENT-IP:'.Rand_IP()));
	#关闭SSL
	    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	#返回数据不直接显示
	    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	$response = curl_exec($curl);
	curl_close($curl);
	return $response;
}
//POST函数
function MloocCurlPost($post_data = '', $url = '', $ifurl = '', $UserAgent = '') {
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_USERAGENT, $UserAgent);
	if ($ifurl != '') {
		curl_setopt($curl, CURLOPT_REFERER, $ifurl);
	}
	curl_setopt($curl, CURLOPT_HTTPHEADER, array('X-FORWARDED-FOR:'.Rand_IP(), 'CLIENT-IP:'.Rand_IP()));
	#关闭SSL
	    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	#返回数据不直接显示
	    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_POST, 1);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
	$response = curl_exec($curl);
	curl_close($curl);
	return $response;
}
//直链解析函数
function MloocCurlHead($url,$guise,$UserAgent,$cookie) {
	$headers = array(
		'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
		'Accept-Encoding: gzip, deflate',
		'Accept-Language: zh-CN,zh;q=0.9',
		'Cache-Control: no-cache',
		'Connection: keep-alive',
		'Pragma: no-cache',
		'Upgrade-Insecure-Requests: 1',
		'User-Agent: '.$UserAgent
	);
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_HTTPHEADER,$headers);
	curl_setopt($curl, CURLOPT_REFERER, $guise);
	curl_setopt($curl, CURLOPT_COOKIE , $cookie);
	curl_setopt($curl, CURLOPT_USERAGENT, $UserAgent);
	curl_setopt($curl, CURLOPT_NOBODY, 0);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLINFO_HEADER_OUT, TRUE);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	//超时设置，默认为10秒
	curl_setopt($curl, CURLOPT_TIMEOUT, 10);
	$data = curl_exec($curl);
	$url=curl_getinfo($curl);
	curl_close($curl);
	return $url["redirect_url"];
}
//随机IP函数
function Rand_IP() {
	$ip2id = round(rand(600000, 2550000) / 10000);
	$ip3id = round(rand(600000, 2550000) / 10000);
	$ip4id = round(rand(600000, 2550000) / 10000);
	$arr_1 = array("218","218","66","66","218","218","60","60","202","204","66","66","66","59","61","60","222","221","66","59","60","60","66","218","218","62","63","64","66","66","122","211");
	$randarr= mt_rand(0,count($arr_1)-1);
	$ip1id = $arr_1[$randarr];
	return $ip1id.".".$ip2id.".".$ip3id.".".$ip4id;
}
?>
