<?php
/**
 * Fix locale setting in database to es-ES
 * Run this once: php scripts/fix_locale_in_db.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Setting;

try {
    $settings = Setting::getSettings();
    
    if (!$settings) {
        echo "❌ No settings found in database. Please run migrations first.\n";
        exit(1);
    }
    
    $oldLocale = $settings->locale;
    
    $settings->locale = 'es-ES';
    $settings->save();
    
    echo "✅ Locale updated successfully!\n";
    echo "   Old locale: " . ($oldLocale ?: '(empty)') . "\n";
    echo "   New locale: es-ES\n";
    
    // Also clear config cache to ensure it takes effect
    \Artisan::call('config:clear');
    \Artisan::call('cache:clear');
    \Artisan::call('config:cache');
    
    echo "✅ Cache cleared and recached.\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
