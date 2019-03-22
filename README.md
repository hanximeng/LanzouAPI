# LanzouAPI

## 说明
1. 支持检测文件是否被取消

2. 支持带密码的文件分享链接但不支持分享的文件夹

3. 支持生成直链或直接下载

4. 增加ios应用在线安装

## 使用方法

url:蓝奏云外链链接

type:是否直接下载 值：down

pwd:外链密码

### 直接下载：

无密码：https://pic.mlooc.cn/aaa.php?url=https://www.lanzous.com/i1aesgj&type=down

有密码：https://pic.mlooc.cn/aaa.php?url=https://www.lanzous.com/i19pnjc&type=down&pwd=1pud


### 输出直链：

无密码：https://pic.mlooc.cn/aaa.php?url=https://www.lanzous.com/i1aesgj

有密码：https://pic.mlooc.cn/aaa.php?url=https://www.lanzous.com/i19pnjc&pwd=1pud


## 问题

1. msg 返回 null 且 无直链返回，一般为服务器IP被防火墙自动屏蔽过滤，可能是短期的也可能是长期的
    > 解决方案：返回状态码为 301 或 302 用浏览器打开蓝奏云，复制所有cookie，添加到代码里面，具体添加方法如不会请百度 `PHP-CURL携带cookie请求`
    > 返回状态码为403 则必须要使用ip代理解决了，一般返回403的都是短期屏蔽 测试几次均为5分钟左右

## 转载或使用请保留版权！！
