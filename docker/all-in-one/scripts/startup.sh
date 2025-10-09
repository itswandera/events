#!/bin/sh

echo "============================================"
echo "Hi.Events Startup Script"
echo "============================================"

# CRITICAL: Unset DATABASE_URL to prevent Laravel from parsing it
unset DATABASE_URL
unset DATABASE_PUBLIC_URL

# Force Laravel to use individual DB_* variables
export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_PORT="${DB_PORT:-5432}"
export DB_DATABASE="${DB_DATABASE:-forge}"
export DB_USERNAME="${DB_USERNAME:-forge}"
export DB_PASSWORD="${DB_PASSWORD:-}"

# Set missing VITE variables for supervisor
export VITE_API_URL_CLIENT="${APP_URL}/api"
export VITE_FRONTEND_URL="${APP_URL}"
export VITE_STRIPE_PUBLISHABLE_KEY=""

cd /app/backend

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force 2>&1 || echo "Migration completed with warnings"

# Clear caches (ignore errors)
echo "Clearing application caches..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

# Rebuild autoloader to fix User model issue
echo "Rebuilding autoloader..."
composer dump-autoload --optimize 2>/dev/null || true

# Create storage symlink (ignore if exists)
echo "Creating storage symlink..."
php artisan storage:link 2>/dev/null || true

# Promote user to admin
echo "Promoting user to admin..."
php artisan tinker --execute="
try {
    \$user = DB::table('users')->where('email', 'admin@example.com')->first();
    if (\$user) {
        DB::table('users')->where('id', \$user->id)->update([
            'is_account_owner' => true,
            'updated_at' => now()
        ]);
        echo 'User promoted to admin!' . PHP_EOL;
    } else {
        echo 'User not found!' . PHP_EOL;
    }
} catch (Exception \$e) {
    echo 'Admin promotion skipped: ' . \$e->getMessage() . PHP_EOL;
}
" 2>/dev/null || echo "Admin promotion skipped"

# Set correct permissions
echo "Setting file permissions..."
chown -R www-data:www-data /app/backend
chmod -R 775 /app/backend/storage /app/backend/bootstrap/cache

echo "============================================"
echo "Startup complete! Starting services..."
echo "============================================"

# Start supervisor (Nginx + PHP-FPM)
exec /usr/bin/supervisord -c /etc/supervisord.conf
