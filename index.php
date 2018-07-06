<?php
/**
 * @package Lanzou
 * @author Mlooc
 * @version 1.0.2
 * @link https://mlooc.cn
 */
	function MloocCurl($url,$method,$ifurl,$post_data){
		$UserAgent = 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36';#设置UserAgent
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_USERAGENT, $UserAgent);
		#关闭SSL
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		#返回数据不直接显示
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		if ($method == "post") {
			curl_setopt($curl, CURLOPT_REFERER, $ifurl); 
			curl_setopt($curl, CURLOPT_POST, 1); 
        	curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
		}
		$response = curl_exec($curl);
		curl_close($curl);
		return $response;
	}
	if (!empty($_GET['url'])) {
		$url = $_GET['url'];
		#判断文件是否被取消
		if (strstr(MloocCurl($url,null,null,null),"来晚啦...文件取消分享了") != false) {
			echo "文件取消分享了";
			exit;
		}
		#第一步
		$ruleMatchDetailInList = "~ifr2\"\sname=\"[\s\S]*?\"\ssrc=\"\/(.*?)\"~";#正则表达式
		preg_match($ruleMatchDetailInList, MloocCurl($url,null,null,null),$link);
		$ifurl = "https://www.lanzous.com/".$link[1];
		#第二步
		$ruleMatchDetailInList = "~=\s'(.*?)';[\S\s]*?=\s'(.*?)'[\S\s]*?=\s'(.*?)'[\S\s]*?=\s'(.*?)'~";#正则表达式
		preg_match($ruleMatchDetailInList, MloocCurl($ifurl,null,null,null),$segment);
		#第三步
		#post提交的数据
		$post_data = array(
			"action" => $segment[1],
			"file_id" => $segment[2],
			"t" => $segment[3],
			"k" => $segment[4]
			);
		$obj = json_decode(MloocCurl("https://www.lanzous.com/ajaxm.php","post",$ifurl,$post_data));#json解析
		if ($obj->inf == "密码不正确" && !empty($_GET['pwd'])) {
			$post_data = array(
			"action" => $segment[1],
			"file_id" => $segment[2],
			"t" => $segment[3],
			"k" => $segment[4],
			"p" => $_GET['pwd']
			);
			$obj = json_decode(MloocCurl("https://www.lanzous.com/ajaxm.php","post",$ifurl,$post_data));#json解析
		}elseif($obj->inf == "密码不正确"){
			echo "密码不正确";
			exit;
		}
		if ($obj->dom == "") {#判断链接是否正确
			echo "链接有误！";
			exit;
		}else{
			$downUrl = $obj->dom."/file/".$obj->url;
			if (!empty($_GET['type'])) {
				$type = $_GET['type'];
				if ($type == "down") {
					header('Location:'.$downUrl);#直接下载
				}else{
					echo $downUrl;#输出直链
				}
			}else{
				echo $downUrl;#输出直链
			}
		}
	}else{
		$result_url = str_replace("index.php","","//".$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']."?url=https://www.lanzous.com/i1aesgj");
		$result_url_pwd = str_replace("index.php","","//".$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']."?url=https://www.lanzous.com/i19pnjc");
      	echo "url:蓝奏云外链链接";
      	echo "<br/><br/>";
      	echo "type:是否直接下载 值：down";
      	echo "<br/><br/>";
      	echo "pwd:外链密码";
        echo "<br/><br/>";
		echo "直接下载：";
		echo "<br/>";
		echo "无密码：<a href='".$result_url."&type=down' target='_blank'>".$result_url."&type=down</a>";
		echo "<br/><br/>";
		echo "有密码：<a href='".$result_url_pwd."&type=down&pwd=1pud' target='_blank'>".$result_url_pwd."&type=down&pwd=1pud</a>";
		echo "<br/><br/>";
		echo "输出直链：";
		echo "<br/>";
		echo "无密码：<a href='".$result_url."' target='_blank'>".$result_url."</a>";
		echo "<br/><br/>";
		echo "有密码：<a href='".$result_url_pwd."&pwd=1pud' target='_blank'>".$result_url_pwd."&pwd=1pud</a>";
	}
?>