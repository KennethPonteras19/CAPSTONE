<?php

namespace App\Console\Commands;

use App\Models\SuperAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class UpgradeSuperAdminPasswords extends Command
{
    protected $signature = 'superadmin:upgrade-password-hashes';
    protected $description = 'Upgrade legacy plain-text superadmin passwords to secure hashes';

    public function handle(): int
    {
        $updated = 0;

        SuperAdmin::query()->chunk(100, function ($admins) use (&$updated) {
            foreach ($admins as $admin) {
                $password = (string) $admin->password;

                if ($password === '') {
                    continue;
                }

                if (!Hash::needsRehash($password)) {
                    continue;
                }

                $admin->password = Hash::make($password);
                $admin->save();
                $updated++;
            }
        });

        $this->info("Superadmin password hash upgrade completed. Updated records: {$updated}");

        return self::SUCCESS;
    }
}
