<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Turns an existing account into an admin. Nobody can sign up as admin on the website,
 * so this is how the first (real) admin is made.
 *
 *   php artisan rsync:make-admin someone@gmail.com
 *
 * Changes only that one user's role. Nothing is deleted.
 */
class MakeAdmin extends Command
{
    protected $signature = 'rsync:make-admin {email : Email of an account that already exists}';

    protected $description = 'Give an existing account the admin role';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $user = User::whereRaw('LOWER(email) = LOWER(?)', [$email])->first();

        if (! $user) {
            $this->error("No account with the email {$email}. Register it on the website first.");
            return self::FAILURE;
        }
        if ($user->role === 'admin') {
            $this->info("{$user->email} is already an admin.");
            return self::SUCCESS;
        }

        $this->line("Database: " . config('database.connections.' . config('database.default') . '.database'));
        $this->line("User:     {$user->name} <{$user->email}> (now: {$user->role})");

        if (! $this->confirm('Make this user an admin?')) {
            $this->line('Nothing changed.');
            return self::SUCCESS;
        }

        $user->forceFill(['role' => 'admin'])->save();
        $this->info("{$user->email} is now an admin. They will see the Admin Dashboard after logging in.");
        if (! $user->hasVerifiedEmail()) {
            $this->warn('Their email is not verified yet, so they can look but not approve anything until they click the email link.');
        }

        return self::SUCCESS;
    }
}