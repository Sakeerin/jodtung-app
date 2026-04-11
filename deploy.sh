#!/bin/bash
set -e

echo "🚀 Deploying JodTung..."

# Pull latest code
echo "📥 Pulling latest code..."
git pull origin main

# Build and restart containers
echo "🔨 Building Docker images..."
docker compose build --no-cache

echo "🔄 Restarting containers..."
docker compose up -d

# Run migrations
echo "📊 Running migrations..."
docker compose exec app php artisan migrate --force

# Clear and rebuild caches
echo "🧹 Clearing caches..."
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache

echo "✅ Deployment complete!"
echo "🌐 App: https://your-domain.com"
echo "💚 Health: https://your-domain.com/api/health"
