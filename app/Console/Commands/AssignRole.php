<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AssignRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:assign-role {email} {role} {--tenant=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign a role to a user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $roleName = $this->argument('role');
        $tenantId = $this->option('tenant');

        // Find user
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $this->error("User with email {$email} not found.");
            return 1;
        }

        // Set tenant context if provided
        if ($tenantId) {
            setPermissionsTeamId($tenantId);
        } else {
            setPermissionsTeamId($user->tenant_id);
        }

        // Check if role exists
        $role = Role::where('name', $roleName)->first();
        
        if (!$role) {
            $this->error("Role {$roleName} not found.");
            $this->info('Available roles: ' . Role::pluck('name')->implode(', '));
            return 1;
        }

        // Assign role
        $user->assignRole($role);

        $this->info("Role '{$roleName}' assigned to user '{$email}' successfully.");

        // Show user's current roles
        $this->info('User current roles: ' . $user->getRoleNames()->implode(', '));

        return 0;
    }
}