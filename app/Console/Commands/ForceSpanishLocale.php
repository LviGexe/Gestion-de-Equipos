<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ForceSpanishLocale extends Command
{
    protected $signature = 'locale:force-spanish';
    protected $description = 'Force all locale settings to es-ES (Spanish) across the entire application';

    public function handle()
    {
        $this->info('🌍 Forcing Spanish locale (es-ES) across entire application...');
        
        // 1. Update settings table
        try {
            $settings = Setting::getSettings();
            if ($settings) {
                $oldLocale = $settings->locale;
                $settings->locale = 'es-ES';
                $settings->save();
                $this->info("✅ Settings table updated: {$oldLocale} → es-ES");
            } else {
                $this->warn("⚠️  No settings found in database");
            }
        } catch (\Exception $e) {
            $this->error("❌ Error updating settings: " . $e->getMessage());
        }
        
        // 2. Update all users to use Spanish locale
        try {
            $userCount = User::whereNotNull('locale')->where('locale', '!=', 'es-ES')->count();
            User::whereNotNull('locale')->update(['locale' => 'es-ES']);
            User::whereNull('locale')->update(['locale' => 'es-ES']);
            $this->info("✅ Updated {$userCount} users to es-ES locale");
        } catch (\Exception $e) {
            $this->error("❌ Error updating users: " . $e->getMessage());
        }
        
        // 3. Clear all caches
        $this->call('config:clear');
        $this->call('cache:clear');
        $this->call('view:clear');
        $this->call('route:clear');
        
        // 4. Rebuild config cache
        $this->call('config:cache');
        
        $this->info('✅ All caches cleared and rebuilt');
        $this->info('');
        $this->info('🎉 Spanish locale (es-ES) has been forced successfully!');
        $this->info('   All users and system settings are now set to Spanish.');
        $this->info('   Please refresh your browser to see the changes.');
        
        return 0;
    }
}
