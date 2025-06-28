<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AssignAdminRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:assign-admin-role';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign admin role to all existing users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $adminRole = Role::where('name', 'admin')->first();
        
        if (!$adminRole) {
            $this->error('Admin role not found. Please run the RoleSeeder first.');
            return 1;
        }

        $users = User::all();
        $count = 0;

        foreach ($users as $user) {
            if (!$user->hasRole('admin')) {
                $user->assignRole('admin');
                $count++;
                $this->info("Assigned admin role to user: {$user->name} ({$user->email})");
            }
        }

        $this->info("Successfully assigned admin role to {$count} users.");
        return 0;
    }
}
