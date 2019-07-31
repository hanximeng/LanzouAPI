<?php
/**
 * @package Lanzou
 * @author Filmy
 * @version 1.2.2
 * @link https://mlooc.cn
 */
header('Access-Control-Allow-Origin:*');
header('Content-Type:application/json; charset=utf-8');
$url = isset($_GET['url']) ? $_GET['url'] : "";
$pwd = isset($_GET['pwd']) ? $_GET['pwd'] : "";
$type = isset($_GET['type']) ? $_GET['type'] : "";
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
$softInfo = MloocCurlGet($url);
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
preg_match('~class="b">(.*?)<\/div>~', $softInfo, $softName);
if(!isset($softName[1])){
	preg_match('~<div class="n_box_fn".*?>(.*?)</div>~', $softInfo, $softName);
}
preg_match('~<div class="n_box_des".*?>(.*?)</div>~', $softInfo, $softDesc);
if (strstr($softInfo, "手机Safari可在线安装") != false) {
  	if(strstr($softInfo, "n_file_infos") != false){
      	$ipaInfo = MloocCurlGet($url, 'Mozilla/5.0 (iPhone; CPU iPhone OS 10_3_1 like Mac OS X) AppleWebKit/603.1.30 (KHTML, like Gecko) Version/10.0 Mobile/14E304 Safari/602.1');
    	preg_match('~href="(.*?)" target="_blank" class="appa"~', $ipaInfo, $ipaDownUrl);
    }else{
    	preg_match('~com/(\w+)~', $url, $lanzouId);
        if (!isset($lanzouId[1])) {
            die(
            json_encode(
                array(
                    'code' => 400,
                    'msg' => '解析失败，获取不到文件ID'
                )
                , JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        }
        $lanzouId = $lanzouId[1];
        $ipaInfo = MloocCurlGet("https://www.lanzous.com/tp/" . $lanzouId, 'Mozilla/5.0 (iPhone; CPU iPhone OS 10_3_1 like Mac OS X) AppleWebKit/603.1.30 (KHTML, like Gecko) Version/10.0 Mobile/14E304 Safari/602.1');
        preg_match('~href="(.*?)" id="plist"~', $ipaInfo, $ipaDownUrl);
    }
    
    $ipaDownUrl = isset($ipaDownUrl[1]) ? $ipaDownUrl[1] : "";
    if ($type != "down") {
        die(
        json_encode(
            array(
                'code' => 200,
                'msg' => '',
                'name' => isset($softName[1]) ? $softName[1] : "",
                'downUrl' => $ipaDownUrl
            )
            , JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    } else {
        header("Location:$ipaDownUrl");
        die;
    }
}
if(strstr($softInfo, "function down_p(){") != false){
	if(empty($pwd)){
		die(
		json_encode(
			array(
				'code' => 400,
				'msg' => '请输入分享密码'
			)
			, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
		);
	}
	preg_match("~'action=(.*?)&sign=(.*?)&p='\+(.*?),~", $softInfo, $segment);
	$post_data = array(
		"action" => $segment[1],
		"sign" => $segment[2],
		"p" => $pwd
	);
	$softInfo = MloocCurlPost($post_data, "https://www.lanzous.com/ajaxm.php", $url);
}else{
	preg_match("~iframe.*?name=\"[\s\S]*?\"\ssrc=\"\/(.*?)\"~", $softInfo, $link);
	$ifurl = "https://www.lanzous.com/" . $link[1];
	$softInfo = MloocCurlGet($ifurl);
	preg_match("~'action':'(.*?)','sign':'(.*?)'~", $softInfo, $segment);
	$post_data = array(
		"action" => $segment[1],
		"sign" => $segment[2],
		"p" => $pwd
	);
	$softInfo = MloocCurlPost($post_data, "https://www.lanzous.com/ajaxm.php", $ifurl);
}

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
$downUrl = $softInfo['dom'] . '/file/' . $softInfo['url'];

$headers = [
	'Host: vip.d0.baidupan.com',
	'Connection: keep-alive',
	'Upgrade-Insecure-Requests: 1',
	'User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36',
	'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
	'Accept-Encoding: gzip, deflate, br',
	'Accept-Language: zh-CN,zh;q=0.9'
];
$downUrl = MloocCurlGetDownUrl($downUrl, $headers);
if ($type != "down") {
    die(
    json_encode(
        array(
            'code' => 200,
            'msg' => '',
            'name' => isset($softName[1]) ? $softName[1] : "",
			'desc' => isset($softDesc[1]) ? $softDesc[1] : "",
            'downUrl' => $downUrl
        )
        , JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
} else {
    header("Location:$downUrl");
    die;
}
function MloocCurlGetDownUrl($url, $headers)
{
    $curl = curl_init();
	curl_setopt($curl, CURLOPT_HEADER, 1);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    #关闭SSL
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    #返回数据不直接显示
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($curl);
	$info = curl_getinfo($curl);
    curl_close($curl);
	if(isset($info['url']) && $info['url'] != null && $info['url'] != ""){
		return $info['url'];
	}
    return "";
}
function MloocCurlGet($url, $UserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36')
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    if ($UserAgent != "") {
        curl_setopt($curl, CURLOPT_USERAGENT, $UserAgent);
    }
    #关闭SSL
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    #返回数据不直接显示
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
function MloocCurlPost($post_data, $url, $ifurl = '', $UserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36')
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_USERAGENT, $UserAgent);
    if ($ifurl != '') {
        curl_setopt($curl, CURLOPT_REFERER, $ifurl);
    }
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
