#!/bin/sh

echo "============================================"
echo "Hi.Events Startup Script"
echo "============================================"

cd /app/backend

# Run database migrations
echo "Running database migrations..."
if ! php artisan migrate --force; then
    echo "============================================"
    echo "ERROR: Migrations could not complete. Check the error above."
    echo "Ensure DATABASE_URL or DB_* environment variables are set."
    echo "============================================"
    exit 1
fi

# Clear all caches
echo "Clearing application caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Create storage symlink
echo "Creating storage symlink..."
php artisan storage:link

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
        if (method_exists(\$user, 'is_admin')) {
            \$user->is_admin = true;
        }
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
" 2>&1

# Set correct permissions
echo "Setting file permissions..."
chown -R www-data:www-data /app/backend
chmod -R 775 /app/backend/storage /app/backend/bootstrap/cache

echo "============================================"
echo "Startup complete! Starting services..."
echo "============================================"

# Start supervisor (Nginx + PHP-FPM)
exec /usr/bin/supervisord -c /etc/supervisord.conf
