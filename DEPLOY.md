# JodTung Deployment Guide

## Prerequisites

- Docker & Docker Compose installed
- Domain name pointing to your server
- LINE Developer account with Messaging API channel

## Quick Start

```bash
# 1. Clone the repository
git clone https://github.com/your-repo/jodtung.git
cd jodtung/jodtung-app

# 2. Copy & configure environment
cp .env.example .env
# Edit .env with your settings:
#   APP_URL, DB_*, LINE_CHANNEL_ACCESS_TOKEN, LINE_CHANNEL_SECRET

# 3. Generate app key
php artisan key:generate

# 4. Build & start
docker compose up -d --build

# 5. Run migrations & seed
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force

# 6. Cache config for production
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

## Environment Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `APP_URL` | Your app URL | `https://jodtung.example.com` |
| `DB_DATABASE` | Database name | `jodtung` |
| `DB_USERNAME` | Database user | `jodtung` |
| `DB_PASSWORD` | Database password | `secure_password` |
| `LINE_CHANNEL_ACCESS_TOKEN` | LINE Bot access token | From LINE Developers Console |
| `LINE_CHANNEL_SECRET` | LINE Bot channel secret | From LINE Developers Console |

## LINE Bot Setup

1. Go to [LINE Developers Console](https://developers.line.biz/console/)
2. Create a **Messaging API** channel
3. Set webhook URL to: `https://your-domain.com/api/line/webhook`
4. Enable **Use webhook**
5. Copy **Channel access token** and **Channel secret** to `.env`

## SSL (Let's Encrypt)

```bash
# Install certbot
apt install certbot

# Generate certificate
certbot certonly --standalone -d your-domain.com

# Copy certificates
cp /etc/letsencrypt/live/your-domain.com/fullchain.pem docker/nginx/ssl/
cp /etc/letsencrypt/live/your-domain.com/privkey.pem docker/nginx/ssl/
```

## Redeployment

```bash
bash deploy.sh
```

## Health Check

```bash
curl https://your-domain.com/api/health
# {"status":"ok","timestamp":"...","app":"JodTung"}
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| 500 error | Check `docker compose exec app php artisan log:tail` |
| DB connection refused | Ensure MySQL container is healthy: `docker compose ps` |
| Webhook fails | Verify `LINE_CHANNEL_SECRET` in `.env` matches LINE Console |
| Rate limited | Default: 60 req/min API, 30 req/min webhook |
