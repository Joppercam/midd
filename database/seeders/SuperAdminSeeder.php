<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SuperAdmin;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default super admin
        SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@crecepyme.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // Create default system settings
        $defaultSettings = [
            // General Settings
            ['key' => 'app_name', 'value' => 'CrecePyme', 'type' => 'string', 'category' => 'general', 'description' => 'Application name', 'is_public' => true],
            ['key' => 'app_url', 'value' => config('app.url'), 'type' => 'string', 'category' => 'general', 'description' => 'Application URL', 'is_public' => true],
            ['key' => 'maintenance_mode', 'value' => 'false', 'type' => 'boolean', 'category' => 'general', 'description' => 'Enable maintenance mode', 'is_public' => false],
            
            // Tenant Settings
            ['key' => 'default_trial_days', 'value' => '14', 'type' => 'integer', 'category' => 'tenants', 'description' => 'Default trial period in days', 'is_public' => false],
            ['key' => 'max_tenants', 'value' => '1000', 'type' => 'integer', 'category' => 'tenants', 'description' => 'Maximum number of tenants allowed', 'is_public' => false],
            ['key' => 'tenant_auto_suspension', 'value' => 'true', 'type' => 'boolean', 'category' => 'tenants', 'description' => 'Auto-suspend tenants on payment failure', 'is_public' => false],
            
            // Email Settings
            ['key' => 'smtp_host', 'value' => config('mail.mailers.smtp.host'), 'type' => 'string', 'category' => 'email', 'description' => 'SMTP host', 'is_public' => false],
            ['key' => 'smtp_port', 'value' => config('mail.mailers.smtp.port'), 'type' => 'integer', 'category' => 'email', 'description' => 'SMTP port', 'is_public' => false],
            ['key' => 'from_email', 'value' => config('mail.from.address'), 'type' => 'string', 'category' => 'email', 'description' => 'From email address', 'is_public' => false],
            
            // Backup Settings
            ['key' => 'backup_retention_days', 'value' => '30', 'type' => 'integer', 'category' => 'backup', 'description' => 'Backup retention period in days', 'is_public' => false],
            ['key' => 'auto_backup_enabled', 'value' => 'true', 'type' => 'boolean', 'category' => 'backup', 'description' => 'Enable automatic backups', 'is_public' => false],
            ['key' => 'backup_schedule', 'value' => 'daily', 'type' => 'string', 'category' => 'backup', 'description' => 'Backup frequency (daily, weekly, monthly)', 'is_public' => false],
            
            // Security Settings
            ['key' => 'session_timeout', 'value' => '120', 'type' => 'integer', 'category' => 'security', 'description' => 'Session timeout in minutes', 'is_public' => false],
            ['key' => 'max_login_attempts', 'value' => '5', 'type' => 'integer', 'category' => 'security', 'description' => 'Maximum login attempts before lockout', 'is_public' => false],
            ['key' => 'password_min_length', 'value' => '8', 'type' => 'integer', 'category' => 'security', 'description' => 'Minimum password length', 'is_public' => false],
            
            // Performance Settings
            ['key' => 'cache_ttl', 'value' => '3600', 'type' => 'integer', 'category' => 'performance', 'description' => 'Default cache TTL in seconds', 'is_public' => false],
            ['key' => 'pagination_limit', 'value' => '15', 'type' => 'integer', 'category' => 'performance', 'description' => 'Default pagination limit', 'is_public' => false],
            ['key' => 'api_rate_limit', 'value' => '1000', 'type' => 'integer', 'category' => 'performance', 'description' => 'API rate limit per hour', 'is_public' => false],
            
            // Billing Settings
            ['key' => 'default_currency', 'value' => 'CLP', 'type' => 'string', 'category' => 'billing', 'description' => 'Default currency', 'is_public' => true],
            ['key' => 'invoice_prefix', 'value' => 'INV-', 'type' => 'string', 'category' => 'billing', 'description' => 'Invoice number prefix', 'is_public' => false],
            ['key' => 'payment_grace_period', 'value' => '3', 'type' => 'integer', 'category' => 'billing', 'description' => 'Payment grace period in days', 'is_public' => false],
        ];

        foreach ($defaultSettings as $setting) {
            SystemSetting::create($setting);
        }
    }
}