<?php

namespace Database\Seeders;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductionBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $slug = config('console.internal_org_slug');
        $name = config('console.internal_org_name');
        $email = (string) config('console.owner_email');
        $ownerName = (string) config('console.owner_name', 'Owner');
        $pw = config('console.owner_password'); // nullable

        if (empty($email)) {
            $this->command->error('CONSOLE_OWNER_EMAIL is required.');

            return;
        }

        $internal = Organization::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'is_internal' => true, 'settings_json' => ['locale' => 'en', 'currency' => 'USD']]
        );

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $ownerName, 'password' => Hash::make($pw ?: Str::password(24))]
        );

        // Attach as OWNER of internal org
        Membership::firstOrCreate(
            ['organization_id' => $internal->id, 'user_id' => $user->id],
            ['role' => MembershipRole::OWNER->value]
        );

        $this->command->info("Internal org [{$internal->name}] ready; owner: {$user->email}");
        if (! $pw) {
            $this->command->warn('No CONSOLE_OWNER_PASSWORD provided. Use password reset to set a secure password.');
        }
    }
}
