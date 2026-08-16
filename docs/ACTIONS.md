# API 有效性监控

`.github/workflows/api-health-check.yml` 每 6 小时启动一次当前仓库中的 API，使用测试分享链接执行真实解析，并检查返回结果是否包含有效的 HTTP 下载地址。也可以在 GitHub Actions 页面手动运行。

解析失败时，工作流会通过 SMTP 发送邮件，并将本次运行标记为失败。

## GitHub Secrets

在仓库的 `Settings → Secrets and variables → Actions` 中配置：

| Secret | 必填 | 说明 |
| --- | --- | --- |
| `SMTP_HOST` | 是 | SMTP 服务器，例如 `smtp.qq.com` |
| `SMTP_PORT` | 否 | 默认 `465`；其他端口使用 STARTTLS |
| `SMTP_USERNAME` | 是 | SMTP 登录用户名，通常是发件邮箱 |
| `SMTP_PASSWORD` | 是 | SMTP 授权码，不是普通登录密码 |
| `SMTP_FROM` | 否 | 发件地址，默认使用 `SMTP_USERNAME` |
| `ALERT_EMAIL` | 是 | 接收告警的邮箱地址 |

首次配置后，在 Actions 页面手动运行一次 `API health check`，确认解析和发信配置正常。
