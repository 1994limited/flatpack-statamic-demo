<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\User;

/**
 * The demo's Control Panel account: admin@example.com with the password "password",
 * so this only runs on local sites.
 */
class DemoUsers extends Command
{
    protected $signature = 'demo:users';

    protected $description = 'Make the demo shop\'s admin account (local only)';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->components->error('The demo account has a known password, so it\'s only made on local sites.');

            return self::FAILURE;
        }

        $admin = User::findByEmail('admin@example.com') ?? User::make()->email('admin@example.com');
        $admin->set('name', 'Demo Admin')->password('password')->makeSuper()->save();
        $this->components->twoColumnDetail('admin@example.com', 'Control Panel (password: password)');

        return self::SUCCESS;
    }
}
