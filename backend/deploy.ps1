# Deploy script for Windows
Write-Host "Deploying backend..."

composer install --no-interaction --prefer-dist --optimize-autoloader
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

Write-Host "Deploy complete."
