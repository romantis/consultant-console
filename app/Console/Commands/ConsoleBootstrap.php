<?php

namespace App\Console\Commands;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;

class ConsoleBootstrap extends Command
{
    protected $signature = 'console:bootstrap
        {--email= : Owner/Staff email}
        {--name= : Display name (if creating)}
        {--role=OWNER : OWNER or ADMIN}
        {--org-slug= : Internal org slug (defaults to config)}
        {--org-name= : Internal org name (defaults to config)}
        {--send-reset : Send password reset link if user exists}';

    protected $description = 'Ensure internal org exists and assign staff membership';

    public function handle(): int
    {
        $slug = $this->option('org-slug') ?: config('console.internal_org_slug');
        $name = $this->option('org-name') ?: config('console.internal_org_name');
        $email = $this->option('email') ?: $this->ask('Owner/Staff email');
        $display = $this->option('name') ?: 'Owner';
        $role = strtoupper($this->option('role') ?: 'OWNER');
        if (! in_array($role, ['OWNER', 'ADMIN'], true)) {
            $this->error('Role must be OWNER or ADMIN');

            return 1;
        }

        $internal = Organization::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'is_internal' => true, 'settings_json' => ['locale' => 'en', 'currency' => 'USD']]
        );

        $user = User::firstOrCreate(['email' => $email], ['name' => $display, 'password' => bcrypt(str()->password(24))]);

        Membership::firstOrCreate(
            ['organization_id' => $internal->id, 'user_id' => $user->id],
            ['role' => constant(MembershipRole::class."::{$role}")->value]
        );

        $this->info("Internal org '{$internal->name}' ready. {$user->email} is {$role}.");

        if ($this->option('send-reset')) {
            Password::sendResetLink(['email' => $user->email]);
            $this->info('Password reset link sent (if mail is configured).');
        }

        return 0;
    }
}
