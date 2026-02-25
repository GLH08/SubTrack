# SubTrack

SubTrack 是一个轻量订阅管理系统，支持登录鉴权、订阅管理、统计分析、通知提醒与密码重置。

本项目支持两种部署方式：
- 原代码部署（本地源码 + Docker 构建）
- 远程镜像部署（从 GHCR 拉取镜像）

---

## 一、准备条件

- Docker 24+
- Docker Compose V2
- 一个可用域名（用于 HTTPS 反代）

---

## 二、GitHub Actions：构建并推送 GHCR 镜像

已提供工作流：
- `.github/workflows/ghcr.yml`

触发条件：
- push 到 `main/master`
- push `v*` 标签
- 手动触发 `workflow_dispatch`

镜像地址规则：
- `ghcr.io/<你的GitHub用户名小写>/subtrack:<tag>`
- 默认分支会额外推送 `latest`

> 注意：工作流已配置 `paths-ignore: docs/**`，仅修改 docs 不触发镜像构建。

---

## 三、部署方式 A：原代码部署（保持现有方式）

### 1) 配置环境变量

在项目根目录创建 `.env`：

```env
APP_DEBUG=0
APP_BASE_URL=https://your.domain.com
SUBTRACK_ADMIN_PASSWORD=请替换为强密码
SUBTRACK_CRON_TOKEN=请替换为高强度随机串
```

### 2) 启动

```bash
docker compose up -d --build
```

### 3) 验证

```bash
docker exec subtrack-app php /var/www/html/cli/preflight.php
```

---

## 四、部署方式 B：远程镜像部署（GHCR）

新建 `docker-compose.ghcr.yml`（示例）：

```yaml
services:
  subtrack:
    image: ghcr.io/glh08/subtrack:latest
    container_name: subtrack-app
    ports:
      - "18082:80"
    environment:
      TZ: Asia/Shanghai
      APP_DEBUG: ${APP_DEBUG:-0}
      APP_BASE_URL: ${APP_BASE_URL:-}
      SUBTRACK_DB_PATH: /var/www/html/db/subtrack.db
      SUBTRACK_CRON_TOKEN: ${SUBTRACK_CRON_TOKEN:?SUBTRACK_CRON_TOKEN is required}
      SUBTRACK_ADMIN_PASSWORD: ${SUBTRACK_ADMIN_PASSWORD:?SUBTRACK_ADMIN_PASSWORD is required}
    volumes:
      - ./db:/var/www/html/db
      - ./public/assets/images/uploads/logos:/var/www/html/public/assets/images/uploads/logos
    restart: unless-stopped
```

### 启动命令

```bash
docker compose -f docker-compose.ghcr.yml up -d
```

### 升级镜像

```bash
docker compose -f docker-compose.ghcr.yml pull
docker compose -f docker-compose.ghcr.yml up -d
```

---

## 五、宿主机 Nginx 反向代理（完整可用模板）

> 使用者仅需修改：
> - `server_name`（域名）
> - `ssl_certificate`（证书路径）
> - `ssl_certificate_key`（私钥路径）

将以下内容保存为：`/etc/nginx/conf.d/subtrack.conf`

```nginx
server {
    listen 80;
    server_name your.domain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your.domain.com;

    ssl_certificate     /etc/nginx/ssl/your.domain.com/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/your.domain.com/privkey.pem;

    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:10m;
    ssl_session_tickets off;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    client_max_body_size 10m;

    location / {
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-Host $host;

        proxy_connect_timeout 30s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;

        proxy_pass http://127.0.0.1:18082;
    }
}
```

重载 Nginx：

```bash
nginx -t && systemctl reload nginx
```

---

## 六、上线后建议

- 定时备份 `db/subtrack.db`
- 使用 CLI cron：
  - `php /var/www/html/cli/cron.php reminders -v`
- 如需 HTTP 触发 cron，必须带 `X-CRON-TOKEN`
- 每次升级后执行：
  - `docker exec subtrack-app php /var/www/html/cli/preflight.php`

---

## 七、关于 docs 目录不推送

已配置：
- `.gitignore` 忽略 `docs/`（默认不会进入 Git 提交与推送）
- `.github/workflows/ghcr.yml` 对 `docs/**` 变更不触发镜像构建
- `.dockerignore` 忽略 `docs/`（不进入镜像构建上下文）
