#!/bin/bash

# Yo Salon Backend Deployment Script
# Run this on the production server after git pull

set -e

echo "🚀 Starting Yo Salon deployment..."

# Navigate to project directory
cd /var/www/yo.salon/backend

# Pull latest changes
echo "📥 Pulling latest changes from git..."
git pull origin main

# Check if .env exists
if [ ! -f .env ]; then
    echo "❌ .env file not found. Please create it from .env.example with production values."
    exit 1
fi

# Install dependencies
echo "📦 Installing composer dependencies..."
composer install --no-dev --optimize-autoloader

# Clear and cache config
echo "⚙️  Optimizing configuration..."
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan config:cache

# Clear and cache routes
echo "🛣️  Optimizing routes..."
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan route:cache

# Clear and cache views
echo "🎨 Optimizing views..."
sudo -u www-data php artisan view:clear
sudo -u www-data php artisan view:cache

# Run migrations
echo "🗄️  Running database migrations..."
sudo -u www-data php artisan migrate --force

# Clear and cache events
echo "📡 Optimizing events..."
sudo -u www-data php artisan event:clear
sudo -u www-data php artisan event:cache

# Copy Supervisor config
echo "🔧 Setting up Supervisor configuration..."
sudo cp supervisor.conf /etc/supervisor/conf.d/laravel.conf
sudo supervisorctl reread
sudo supervisorctl update

# Restart queue workers via Supervisor
echo "🔄 Restarting queue workers..."
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart laravel-worker:*
sudo supervisorctl restart laravel-scheduler:*
sudo supervisorctl restart laravel-reverb

# Restart PHP-FPM
echo "🐘 Restarting PHP-FPM..."
sudo systemctl restart php8.3-fpm

# Restart Nginx
echo "🌐 Restarting Nginx..."
sudo systemctl restart nginx

echo "✅ Deployment completed successfully!"
