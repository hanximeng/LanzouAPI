# 自动生成直下分支

`.github/workflows/generate-direct-download-branch.yml` 会在 `master` 更新后自动重新生成 `direct-download` 分支，也支持从 Actions 页面手动运行。

生成过程只替换 `index.php` 的入口参数块：

- 固定分享链接；
- 密码设为空；
- 下载类型强制设为 `down`。

因此部署 `direct-download` 分支后，访问入口无需携带查询参数，解析成功便直接跳转到下载地址，不返回成功 JSON。

`direct-download` 是生成产物，不应手动维护。需要更换目标文件时，请修改生成工作流中的 `DIRECT_DOWNLOAD_URL`。
