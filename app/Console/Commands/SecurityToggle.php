<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SecurityToggle extends Command
{
    protected $signature = 'security:toggle {feature} {--enable} {--disable}';
    protected $description = 'Enable or disable security features';

    public function handle()
    {
        $feature = $this->argument('feature');
        $enable = $this->option('enable');
        $disable = $this->option('disable');

        if (!$enable && !$disable) {
            $this->error('You must specify either --enable or --disable');
            return 1;
        }

        $envPath = base_path('.env');
        $envContent = File::get($envPath);

        $value = $enable ? 'true' : 'false';

        switch (strtolower($feature)) {
            case 'waf':
                $envContent = $this->updateEnvValue($envContent, 'WAF_ENABLED', $value);
                break;
            case 'csp':
                $envContent = $this->updateEnvValue($envContent, 'CSP_ENABLED', $value);
                break;
            case '2fa':
                $envContent = $this->updateEnvValue($envContent, '2FA_ENABLED', $value);
                break;
            case 'all':
                $envContent = $this->updateEnvValue($envContent, 'WAF_ENABLED', $value);
                $envContent = $this->updateEnvValue($envContent, 'CSP_ENABLED', $value);
                $envContent = $this->updateEnvValue($envContent, '2FA_ENABLED', $value);
                break;
            default:
                $this->error("Unknown feature: {$feature}. Available: waf, csp, 2fa, all");
                return 1;
        }

        File::put($envPath, $envContent);

        $status = $enable ? 'enabled' : 'disabled';
        $this->info("Security feature '{$feature}' has been {$status}");
        $this->info('Please clear config cache: php artisan config:clear');

        return 0;
    }

    private function updateEnvValue($envContent, $key, $value)
    {
        $pattern = "/^{$key}=.*$/m";
        $replacement = "{$key}={$value}";

        if (preg_match($pattern, $envContent)) {
            return preg_replace($pattern, $replacement, $envContent);
        } else {
            return $envContent . "\n{$replacement}";
        }
    }
}