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

# Install dependencies
echo "📦 Installing composer dependencies..."
composer install --no-dev --optimize-autoloader

# Clear and cache config
echo "⚙️  Optimizing configuration..."
php artisan config:clear
php artisan config:cache

# Clear and cache routes
echo "🛣️  Optimizing routes..."
php artisan route:clear
php artisan route:cache

# Clear and cache views
echo "🎨 Optimizing views..."
php artisan view:clear
php artisan view:cache

# Run migrations
echo "🗄️  Running database migrations..."
php artisan migrate --force

# Clear and cache events
echo "📡 Optimizing events..."
php artisan event:clear
php artisan event:cache

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
