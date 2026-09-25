<?php
/**
 * @package Lanzou
 * @author Filmy,hanximeng
 * @version 1.4.1
 * @Date 2026-09-25
 * @link https://hanximeng.com
 */
//屏蔽报错
error_reporting(0);
header('Access-Control-Allow-Origin:*');
header('Content-Type:application/json; charset=utf-8');
//========== 缓存配置（可按需自定义） ==========
//缓存目录：解析成功后会把结果写入此处，不存在时会自动创建，请确保 PHP 进程有写入权限
$cacheDir = __DIR__ . '/cache';
//缓存有效时间（秒）：短时间内的重复请求直接返回缓存结果，避免大量重复请求触发蓝奏风控；设为 0 表示关闭缓存
//同时作为缓存清理周期：过期缓存会在下次请求经过一个缓存周期后被清理
$cacheTime = 900;
//缓存密钥：参与缓存 key 的生成，多服务器部署时可防止缓存 key 被模拟/投毒，建议修改为随机字符串
$cacheSalt = 'LanzouAPI_Cache_5e3a9f1c';
//默认UA
$UserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36';
// 此分支由 GitHub Actions 自动生成，请勿手动修改。
$url = 'https://daxiaamu.lanzouu.com/ixLqp3xhy4ah';
$pwd = "";
$type = "down";
$webpage = parse_url($url, PHP_URL_QUERY);
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
//保留分享链接原始域名。蓝奏云的风控 Cookie 与域名关联，强制换域名会导致校验失败。
$parsedUrl = parse_url($url);
if (!isset($parsedUrl['scheme'], $parsedUrl['host'], $parsedUrl['path']) ||
    !in_array(strtolower($parsedUrl['scheme']), array('http', 'https'), true)) {
	JsonError('URL格式错误');
}
$origin = 'https://' . $parsedUrl['host'];
$url = $origin . $parsedUrl['path'];
if (!empty($parsedUrl['query'])) {
	$url .= '?' . $parsedUrl['query'];
}
//缓存 key 由解析参数与缓存密钥共同生成，命中时直接返回，不再请求蓝奏云
$cacheKey = md5($cacheSalt . '|' . $url . '|' . $pwd . '|' . (isset($_GET['n']) ? $_GET['n'] : ''));
$cached = CacheGet($cacheKey);
if ($cached !== false) {
	if ($type == "down") {
		header("X-Lanzou-Cache: HIT");
		header("Location:" . $cached['downUrl']);
		die;
	}
	die(
	    json_encode(
	        array(
	            'code' => 200,
	            'msg' => '解析成功',
	            'name' => isset($cached['name']) ? $cached['name'] : "",
	            'filesize' => isset($cached['filesize']) ? $cached['filesize'] : "",
	            'downUrl' => isset($cached['downUrl']) ? $cached['downUrl'] : "",
	            'fromCache' => true
	        )
	        , JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
	    );
}
$cookie="";
$softInfo = MloocCurlGetWithChallenge($url, $UserAgent, $cookie);
//判断文件链接是否失效
if (strpos($softInfo, "文件取消分享了") !== false || strpos($softInfo, "文件不存在") !== false) {
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
if(strpos($softInfo, "function down_p(){") !== false  && empty($webpage)) {
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
	//新版页面：data:{...'sign':(字面量|变量)}，行首锚定 data: 可排除 //data 注释中的伪参数
	preg_match("~^[ \t]*data\s*:\s*\{[^\r\n]*['\"]sign['\"]\s*:\s*(?:(['\"])(.*?)\\1|([A-Za-z_$][\w$]*))~m", $softInfo, $signParam);
	$sign = isset($signParam[2]) ? $signParam[2] : '';
	//sign 为变量（如 isngis）时，查找对应的 var 赋值
	if($sign === '' && !empty($signParam[3])) {
		preg_match_all("~var\s+" . preg_quote($signParam[3], "~") . "\s*=\s*(['\"])(.*?)\\1\s*;~", $softInfo, $signValues);
		$signValues = array_values(array_filter(isset($signValues[2]) ? $signValues[2] : array(), 'strlen'));
		$sign = !empty($signValues) ? end($signValues) : '';
	}
	//兼容旧版字面量 sign
	if($sign === '') {
		preg_match_all("~'sign':'(.*?)',~", $softInfo, $segment);
		$sign = isset($segment[1][1]) ? $segment[1][1] : '';
	}
	//下载接口可能是绝对地址（apifile.lanzouw.com），也可能是相对地址，统一补全为绝对地址
	preg_match_all("~(?:https?://[^/\s]+/)?(ajax(?:m|file)\.php\?file=\d+)~", $softInfo, $ajaxm);
	$ajaxPath = isset($ajaxm[0][0]) ? $ajaxm[0][0] : '';
	if ($ajaxPath !== '' && strpos($ajaxPath, 'http') !== 0) {
		$ajaxPath = $origin . '/' . ltrim($ajaxPath, '/');
	}
	if(empty($sign) || empty($ajaxPath)) {
		die(
			json_encode(
				array(
					'code' => 400,
					'msg' => '未找到密码页 sign 或下载接口参数'
			    )
				, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
			);
	}
	$post_data = array(
		"action" => "downprocess",
		"sign" => $sign,
		"p" => $pwd,
		"kd" => 1
	);
	$softInfo = MloocCurlPost($post_data, $ajaxPath, $url, $UserAgent, "acw_sc__v2=".$cookie);
	$nameInfo = json_decode($softInfo, true);
	$softName[1] = (is_array($nameInfo) && isset($nameInfo['inf'])) ? $nameInfo['inf'] : '';
} else {
	//不带密码的链接处理
	preg_match("~\n<iframe.*?name=\"[\s\S]*?\"\ssrc=\"\/(.*?)\"~", $softInfo, $link);
	//蓝奏云新版页面正则规则
	if(empty($link[1])) {
		preg_match("~<iframe.*?name=\"[\s\S]*?\"\ssrc=\"\/(.*?)\"~", $softInfo, $link);
	}
	$ifurl = $origin . "/" . $link[1];
	if(!empty($webpage)){
	    preg_match_all("~'sign':'(.*?)'~", $softInfo, $segment);
	    preg_match_all("~ajaxdata = '(.*?)'~", $softInfo, $signs);
	    preg_match_all("~(?:https?://[^/\s]+/)?(ajax(?:m|file)\.php\?file=\d+)~", $softInfo, $ajaxm);
	    $post_data = array(
		    "action" => "downprocess",
		    "websignkey" => "Em2R",
		    "sign" => $segment[1][1],
		    "websign" => 2,
		    "kd" => 1,
		    "ves" => 1
	    );
	}else{
	    if(empty($link[1])) {
		    JsonError('未找到下载入口，请检查链接是否有效');
	    }
	    $softInfo = MloocCurlGetWithChallenge($ifurl, $UserAgent, $cookie, $url);
	    preg_match_all("~wp_sign = '(.*?)'~", $softInfo, $segment);
	    preg_match_all("~ajaxdata = '(.*?)'~", $softInfo, $signs);
	    preg_match_all("~(?:https?://[^/\s]+/)?(ajax(?:m|file)\.php\?file=\d+)~", $softInfo, $ajaxm);
	    $post_data = array(
		    "action" => "downprocess",
		    "websignkey" => $signs[1][0],
		    "signs" => $signs[1][0],
		    "sign" => $segment[1][0],
		    "websign" => '',
		    "kd" => 1,
		    "ves" => 1
	    );
	}
	//下载接口可能是绝对地址（apifile.lanzouw.com），也可能是相对地址，统一补全为绝对地址
	$ajaxmPath = $ajaxm[0][0] ?? '';
	if ($ajaxmPath !== '' && strpos($ajaxmPath, 'http') !== 0) {
		$ajaxmPath = $origin . '/' . ltrim($ajaxmPath, '/');
	}
	$softInfo = MloocCurlPost($post_data, $ajaxmPath, $ifurl, $UserAgent, "acw_sc__v2=".$cookie);
}
//其他情况下的信息输出
$decoded = json_decode($softInfo, true);
if (!is_array($decoded) || !isset($decoded['zt']) || $decoded['zt'] != 1) {
	$errMsg = (is_array($decoded) && isset($decoded['inf'])) ? $decoded['inf'] : '解析失败，蓝奏云返回异常，请稍后重试';
	die(
	    json_encode(
	        array(
	            'code' => 400,
	            'msg' => $errMsg
	        )
	        , JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
	    );
}
$softInfo = $decoded;
//拼接链接
$downUrl1 = $softInfo['dom'] . '/file/' . $softInfo['url'];
//解析最终直链地址：蓝奏云 CDN 对缺少浏览器特征的请求会先返回 ESA 挑战页，需带上浏览器请求头并算出 acw_sc__v2 后重试
$downUrl2 = MloocCurlHead($downUrl1, $UserAgent);
//判断最终链接是否获取成功，如未成功则使用原链接
if(strpos($downUrl2,"http") === false) {
	$downUrl = $downUrl1;
} else {
	//2025-03-17 新增后缀自定义功能 https://github.com/hanximeng/LanzouAPI/issues/26
	if(!empty($_GET['n'])){
	    $downUrl = ReplaceUrlSuffix($downUrl2, $_GET['n']);
	}else{
	    $downUrl = $downUrl2;
	}
}
//2024-12-03 修复pid参数可能导致的服务器ip地址泄露
$downUrl=preg_replace('/pid=(.*?.)&/', '', $downUrl);
//解析成功，写入缓存供短时间内的重复请求复用
CacheSet($cacheKey, array(
	'name' => isset($softName[1]) ? $softName[1] : "",
	'filesize' => isset($softFilesize[1]) ? $softFilesize[1] : "",
	'downUrl' => $downUrl
));
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
//直链解析函数：蓝奏云 CDN 会先返回 ESA 挑战页，需按浏览器特征请求并算出 acw_sc__v2 重试，
//302 响应中的 Location 才是最终直链（因此不能开启自动跳转，否则会直接下载整个文件）
function MloocCurlHead($url, $UserAgent) {
	$cookies = array();
	$headers = array(
		'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
		'Accept-Language: zh-CN,zh;q=0.9,en-US;q=0.8,en;q=0.7',
		'Cache-Control: max-age=0',
		'Upgrade-Insecure-Requests: 1',
		'X-Requested-With: mark.via'
	);
	//挑战页也可能需要重试多次，最多请求 3 次
	for ($i = 0; $i < 3; $i++) {
		$location = '';
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_USERAGENT, $UserAgent);
		curl_setopt($curl, CURLOPT_ENCODING, 'gzip, deflate');
		if (!empty($cookies)) {
			$pairs = array();
			foreach ($cookies as $name => $value) {
				$pairs[] = $name . '=' . $value;
			}
			curl_setopt($curl, CURLOPT_COOKIE, implode('; ', $pairs));
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		//超时设置，默认为10秒
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);
		curl_setopt($curl, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$location, &$cookies) {
			$pos = strpos($header, ':');
			if ($pos === false) {
				return strlen($header);
			}
			$name = strtolower(trim(substr($header, 0, $pos)));
			$value = trim(substr($header, $pos + 1));
			if ($name === 'location' && $location === '') {
				$location = $value;
			} elseif ($name === 'set-cookie') {
				$pair = explode('=', explode(';', $value)[0], 2);
				if (count($pair) === 2) {
					$cookies[trim($pair[0])] = trim($pair[1]);
				}
			}
			return strlen($header);
		});
		$response = curl_exec($curl);
		curl_close($curl);
		if ($location !== '') {
			return $location;
		}
		//挑战页：从 var arg1 算出 acw_sc__v2 后带上 Cookie 重试
		if (preg_match("~var\\s+arg1=['\"]([0-9a-f]{40})['\"]~i", (string)$response, $match)) {
			$cookies['acw_sc__v2'] = acw_sc_v2_simple($match[1]);
			continue;
		}
		break;
	}
	return '';
}
//2025-03-17 新增后缀自定义功能 https://github.com/hanximeng/LanzouAPI/issues/26
//新版直链形如 https://xxx/file/xxx.txt?sg=...&fileName=xxx.txt，其中 sg 签名包含路径，改路径会 403，
//因此只替换查询参数中的文件名扩展名（新版 fileName、旧版 fn）
function ReplaceUrlSuffix($url, $suffix) {
	$suffix = ltrim($suffix, '.');
	return preg_replace_callback('~(fileName|fn)=([^&]*)~', function ($match) use ($suffix) {
		$value = preg_replace('~\\.[A-Za-z0-9]+$~', '', rawurldecode($match[2])) . '.' . $suffix;
		return $match[1] . '=' . rawurlencode($value);
	}, $url, 1);
}
//阿里云 ESA 会先返回 arg1 挑战页。计算 acw_sc__v2 后以同一 UA、Referer 重试。
function MloocCurlGetWithChallenge($url, $UserAgent, &$cookie, $referer = '') {
	$response = MloocCurlGet($url, $UserAgent, $cookie === '' ? '' : 'acw_sc__v2='.$cookie, $referer);
	if (preg_match("~var\\s+arg1=['\"]([0-9a-f]{40})['\"]~i", $response, $match)) {
		$cookie = acw_sc_v2_simple($match[1]);
		$response = MloocCurlGet($url, $UserAgent, 'acw_sc__v2='.$cookie, $referer);
	}
	return $response;
}
//JSON错误输出
function JsonError($message) {
	die(json_encode(array('code' => 400, 'msg' => $message), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
//CURL函数
function MloocCurlGet($url = '', $UserAgent = '', $cookie = '', $referer = '') {
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($curl, CURLOPT_COOKIE , $cookie);
	if ($UserAgent != "") {
		curl_setopt($curl, CURLOPT_USERAGENT, $UserAgent);
	}
	if ($referer != "") {
		curl_setopt($curl, CURLOPT_REFERER, $referer);
	}
	curl_setopt($curl, CURLOPT_HTTPHEADER, array('X-FORWARDED-FOR:'.Rand_IP(), 'CLIENT-IP:'.Rand_IP()));
	#关闭SSL
	    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	    curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
	    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	#返回数据不直接显示
	    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	#超时设置，默认为10秒
	    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
	$response = curl_exec($curl);
	return $response;
}
//POST函数
function MloocCurlPost($post_data = '', $url = '', $ifurl = '', $UserAgent = '',$cookie = '') {
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_COOKIE , $cookie);
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
	#超时设置，默认为10秒
	    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
	curl_setopt($curl, CURLOPT_POST, 1);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
	$response = curl_exec($curl);
	return $response;
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
//cookie生成函数(现在好像又不验证了，怕忘记就先留着吧，介意的可以删除这个函数)
function acw_sc_v2_simple($arg1) {
    $posList = [15,35,29,24,33,16,1,38,10,9,19,31,40,27,22,23,25,13,6,11,39,18,20,8,14,21,32,26,2,30,7,4,17,5,3,28,34,37,12,36];
    $mask = '3000176000856006061501533003690027800375';
    $outPutList = array_fill(0, 40, '');
    for ($i = 0; $i < strlen($arg1); $i++) {
        $char = $arg1[$i];
        foreach ($posList as $j => $pos) {
            if ($pos == $i + 1) {
                $outPutList[$j] = $char;
            }
        }
    }
    $arg2 = implode('', $outPutList);
    $result = '';
    $length = min(strlen($arg2), strlen($mask));
    for ($i = 0; $i < $length; $i += 2) {
        $strHex = substr($arg2, $i, 2);
        $maskHex = substr($mask, $i, 2);
        $xorResult = dechex(hexdec($strHex) ^ hexdec($maskHex));
        $result .= str_pad($xorResult, 2, '0', STR_PAD_LEFT);
    }
    return $result;
}
//读取缓存，命中且未过期时返回数据数组，否则返回 false
function CacheGet($key) {
	global $cacheDir, $cacheTime;
	if ($cacheTime <= 0) return false;
	//按周期清理已过期的缓存文件
	CacheClean();
	$file = $cacheDir . '/' . $key . '.json';
	if (!is_file($file)) return false;
	$data = json_decode(@file_get_contents($file), true);
	if (!is_array($data) || !isset($data['time'], $data['data'])) return false;
	if (time() - $data['time'] > $cacheTime) return false;
	return $data['data'];
}
//写入缓存
function CacheSet($key, $data) {
	global $cacheDir, $cacheTime;
	if ($cacheTime <= 0) return;
	if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0755, true)) return;
	$payload = json_encode(array('time' => time(), 'data' => $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	@file_put_contents($cacheDir . '/' . $key . '.json', $payload);
}
//按缓存有效时间 $cacheTime 周期清理一次已过期的缓存文件，防止失效缓存长期残留
function CacheClean() {
	global $cacheDir, $cacheTime;
	$flagFile = $cacheDir . '/.last_clean';
	$lastClean = is_file($flagFile) ? (int)@file_get_contents($flagFile) : 0;
	if (time() - $lastClean <= $cacheTime) return;
	@file_put_contents($flagFile, time());
	if (!is_dir($cacheDir)) return;
	foreach (glob($cacheDir . '/*.json') as $file) {
		if (time() - filemtime($file) > $cacheTime) @unlink($file);
	}
}
?>
