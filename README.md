# SubTrack

SubTrack 是一个轻量的订阅管理系统，提供：
- 账号登录与会话管理
- 订阅增删改查、批量操作
- 统计分析与历史记录
- 汇率更新与多币种支持
- 通知提醒（Telegram / Email）
- 数据导入导出

本项目支持两种部署方式：
1. **源码部署**（本地代码构建镜像）
2. **GHCR 镜像部署**（服务器直接拉取远程镜像）

---

## 1. 快速开始（源码部署）

### 1.1 前置条件
- Docker 24+
- Docker Compose V2

### 1.2 创建环境变量文件
在项目根目录创建 `.env`：

```env
APP_DEBUG=0
# 可留空；生产建议填写为 HTTPS 正式域名
APP_BASE_URL=
SUBTRACK_ADMIN_PASSWORD=ChangeToAStrongPassword
SUBTRACK_CRON_TOKEN=ChangeToALongRandomToken
```

建议生成随机 token：

```bash
openssl rand -hex 32
```

### 1.3 启动服务

```bash
docker compose up -d --build
```

默认映射端口：`18082`，访问：
- `http://127.0.0.1:18082`

### 1.4 健康检查（上线前必做）

```bash
docker exec subtrack-app php /var/www/html/cli/preflight.php
```

---

## 2. GHCR 镜像部署（推荐生产）

当前仓库镜像地址：
- `ghcr.io/glh08/subtrack:latest`

### 2.1 新建 `docker-compose.ghcr.yml`

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
      - ./data/uploads:/var/www/html/public/assets/images/uploads
    restart: unless-stopped
```

### 2.2 启动与升级

首次启动：

```bash
docker compose -f docker-compose.ghcr.yml up -d
```

更新镜像：

```bash
docker compose -f docker-compose.ghcr.yml pull
docker compose -f docker-compose.ghcr.yml up -d
```

---

## 3. GitHub Actions 自动构建 GHCR

工作流文件：
- `.github/workflows/ghcr.yml`

触发条件：
- push 到 `main` / `master`
- push `v*` 标签
- 手动触发 `workflow_dispatch`

镜像标签规则：
- 分支构建：`ghcr.io/<owner-lowercase>/subtrack:<branch-tag>`
- 默认分支额外推送：`latest`

---

## 4. 环境变量说明

| 变量名 | 必填 | 说明 |
|---|---|---|
| `APP_DEBUG` | 否 | `0` 或 `1`，生产建议 `0` |
| `APP_BASE_URL` | 否 | 可留空；留空时重置链接将使用当前访问地址自动生成。生产建议设置为 HTTPS 正式域名 |
| `SUBTRACK_ADMIN_PASSWORD` | 是 | 初始化管理员密码 |
| `SUBTRACK_CRON_TOKEN` | 是 | HTTP cron 鉴权 token |
| `SUBTRACK_DB_PATH` | 否 | 容器内数据库路径（默认已配置） |

通知与汇率相关配置说明：
- 货币汇率 API 提供商/API Key：在后台设置页保存（数据库）
- Telegram Token / Chat ID：在后台设置页保存（数据库）
- Email SMTP 参数：在后台设置页保存（数据库）
- 因此不需要在 `.env` 中额外配置这些项

---

## 5. 宿主机 Nginx 反向代理（HTTPS）

完整模板文件：
- `nginx.reverse-proxy.example.conf`

你只需要改 3 处即可直接使用：
1. `server_name`
2. `ssl_certificate`
3. `ssl_certificate_key`

部署步骤（Linux）：

```bash
cp nginx.reverse-proxy.example.conf /etc/nginx/conf.d/subtrack.conf
nginx -t && systemctl reload nginx
```

反向代理目标默认是：
- `http://127.0.0.1:18082`

---

## 6. 定时任务（Cron）

推荐使用 CLI cron（更稳定）：

```bash
# 每小时执行一次
0 * * * * cd /path/to/SubTrack && php cli/cron.php all
```

仅执行提醒：

```bash
php cli/cron.php reminders -v
```

仅执行汇率更新：

```bash
php cli/cron.php exchange -v
```

如果你使用 HTTP 触发 cron，必须在请求头带：
- `X-CRON-TOKEN: <SUBTRACK_CRON_TOKEN>`

---

## 7. 备份与恢复

### 7.1 备份
最少备份以下目录：
- `db/`
- `data/uploads/`（其中包含 `logos/`）

示例：

```bash
tar -czf subtrack-backup-$(date +%F).tar.gz db data/uploads
```

### 7.2 恢复
将备份文件恢复到原路径后，重启容器：

```bash
docker compose restart
```

---

## 8. 安全基线（已实现）

- 初始化管理员密码改为环境变量注入
- 关键会话 Cookie 安全属性（HttpOnly / SameSite / Secure）
- CSRF 校验使用安全比较
- cron 接口强制 token 鉴权
- Nginx 安全响应头（CSP / X-Frame-Options / X-Content-Type-Options 等）
- 调试接口已加保护

---

## 9. 常见运维命令

查看容器状态：

```bash
docker ps
```

查看日志：

```bash
docker logs -f subtrack-app
```

进入容器：

```bash
docker exec -it subtrack-app sh
```

重建并重启：

```bash
docker compose up -d --build
```

---

## 10. `docs/` 不参与推送与构建

已配置：
- `.gitignore`：忽略 `docs/`
- `.dockerignore`：忽略 `docs/`
- `.github/workflows/ghcr.yml`：`paths-ignore: docs/**`

因此：
- `docs/` 默认不会进入 Git 提交
- `docs/` 不会进入 Docker 镜像上下文
- 仅改 `docs/` 不触发镜像构建
