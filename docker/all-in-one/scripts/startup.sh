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

cd /app/backend

# Run database migrations
echo "Running database migrations..."
if ! php artisan migrate --force; then
    echo "============================================"
    echo "ERROR: Migrations could not complete. Check the error above."
    echo "Database connection details:"
    echo "  Host: $DB_HOST"
    echo "  Port: $DB_PORT"
    echo "  Database: $DB_DATABASE"
    echo "  User: $DB_USERNAME"
    echo "============================================"
    # Don't exit - continue to start services anyway
fi

# Clear all caches
echo "Clearing application caches..."
php artisan cache:clear 2>/dev/null || true
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

# Create storage symlink
echo "Creating storage symlink..."
php artisan storage:link 2>/dev/null || true

# Create admin user if it doesn't exist
echo "Checking for admin user..."
php artisan tinker --execute="
try {
    if (!\App\Models\User::where('email', 'admin@hievents.com')->exists()) {
        \$user = new \App\Models\User();
        \$user->name = 'Admin';
        \$user->email = 'admin@hievents.com';
        \$user->password = bcrypt('Admin123!ChangeMe');
        \$user->email_verified_at = now();
        \$user->save();
        echo PHP_EOL . '✓ Admin user created successfully!' . PHP_EOL;
        echo 'Email: admin@hievents.com' . PHP_EOL;
        echo 'Password: Admin123!ChangeMe' . PHP_EOL;
        echo 'IMPORTANT: Change this password after first login!' . PHP_EOL;
    } else {
        echo PHP_EOL . '✓ Admin user already exists.' . PHP_EOL;
    }
} catch (\Exception \$e) {
    echo PHP_EOL . '✗ Could not create admin user: ' . \$e->getMessage() . PHP_EOL;
}
" 2>/dev/null || echo "Skipping admin user creation"

# Set correct permissions
echo "Setting file permissions..."
chown -R www-data:www-data /app/backend
chmod -R 775 /app/backend/storage /app/backend/bootstrap/cache

echo "============================================"
echo "Startup complete! Starting services..."
echo "============================================"

# Start supervisor (Nginx + PHP-FPM)
exec /usr/bin/supervisord -c /etc/supervisord.conf
