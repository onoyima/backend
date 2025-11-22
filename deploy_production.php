<?php

/**
 * Production Deployment Script
 * This script helps deploy Laravel application to production environment
 * and fixes common 500 Internal Server Error issues
 */

echo "=== Laravel Production Deployment Script ===\n\n";

// Step 1: Backup current .env
echo "1. Backing up current .env file...\n";
if (file_exists('.env')) {
    copy('.env', '.env.backup.' . date('Y-m-d_H-i-s'));
    echo "✅ .env backed up successfully\n";
} else {
    echo "⚠️  No existing .env file found\n";
}

// Step 2: Copy production .env
echo "\n2. Setting up production environment...\n";
if (file_exists('.env.production')) {
    copy('.env.production', '.env');
    echo "✅ Production .env file activated\n";
} else {
    echo "❌ ERROR: .env.production file not found!\n";
    echo "   Please create .env.production file first\n";
    exit(1);
}

// Step 3: Clear all caches
echo "\n3. Clearing application caches...\n";
$commands = [
    'php artisan config:clear',
    'php artisan route:clear', 
    'php artisan view:clear',
    'php artisan cache:clear'
];

foreach ($commands as $command) {
    echo "   Running: {$command}\n";
    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);
    
    if ($returnCode === 0) {
        echo "   ✅ Success\n";
    } else {
        echo "   ❌ Failed: " . implode('\n', $output) . "\n";
    }
}

// Step 4: Optimize for production
echo "\n4. Optimizing for production...\n";
$optimizeCommands = [
    'php artisan config:cache',
    'php artisan route:cache',
    'php artisan view:cache'
];

foreach ($optimizeCommands as $command) {
    echo "   Running: {$command}\n";
    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);
    
    if ($returnCode === 0) {
        echo "   ✅ Success\n";
    } else {
        echo "   ⚠️  Warning: " . implode('\n', $output) . "\n";
    }
}

// Step 5: Test database connection
echo "\n5. Testing database connection...\n";
$output = [];
$returnCode = 0;
exec('php artisan migrate:status 2>&1', $output, $returnCode);

if ($returnCode === 0) {
    echo "✅ Database connection successful\n";
} else {
    echo "❌ Database connection failed:\n";
    echo "   " . implode('\n   ', $output) . "\n";
    echo "\n   💡 Check your database credentials in .env file\n";
}

// Step 6: Check storage permissions
echo "\n6. Checking storage permissions...\n";
$storageDirs = [
    'storage/app',
    'storage/framework/cache',
    'storage/framework/sessions', 
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache'
];

foreach ($storageDirs as $dir) {
    if (is_writable($dir)) {
        echo "   ✅ {$dir} is writable\n";
    } else {
        echo "   ❌ {$dir} is not writable\n";
        echo "      Fix: chmod 775 {$dir}\n";
    }
}

// Step 7: Generate application key if needed
echo "\n7. Checking application key...\n";
$envContent = file_get_contents('.env');
if (strpos($envContent, 'APP_KEY=base64:') !== false) {
    echo "✅ Application key is set\n";
} else {
    echo "⚠️  Generating new application key...\n";
    exec('php artisan key:generate --force 2>&1', $output, $returnCode);
    if ($returnCode === 0) {
        echo "✅ Application key generated\n";
    } else {
        echo "❌ Failed to generate application key\n";
    }
}

// Step 8: Final recommendations
echo "\n=== Deployment Complete ===\n";
echo "\n📋 Final Checklist:\n";
echo "   □ Verify .env file has correct database credentials\n";
echo "   □ Ensure APP_ENV=production and APP_DEBUG=false\n";
echo "   □ Check that storage directories are writable (775)\n";
echo "   □ Verify SANCTUM_STATEFUL_DOMAINS matches your frontend domain\n";
echo "   □ Test API endpoints to ensure they're working\n";
echo "   □ Monitor storage/logs/laravel.log for any errors\n";

echo "\n🚀 Your Laravel application should now be ready for production!\n";
echo "\nIf you still encounter 500 errors, check:\n";
echo "   - storage/logs/laravel.log for detailed error messages\n";
echo "   - Web server error logs\n";
echo "   - Database connection settings\n";
echo "   - File permissions on storage and bootstrap/cache directories\n";