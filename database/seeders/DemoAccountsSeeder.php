<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomApplication;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo accounts and a little demo data for testing on your own computer.
 *
 * - Only ADDS rows. Never changes or deletes your imported data.
 * - Safe to run again: anything that already exists is left alone
 *   (a password you changed is never reset).
 *
 * Logins (all emails end in @rsync.test):
 *   landlord@rsync.test   / landlord123   (landlord, owns "Demo Room A")
 *   tenant@rsync.test     / tenant123     (tenant, has a Pending application for Demo Room A)
 *   tenant2@rsync.test    / tenant123     (tenant, currently renting Demo Room A)
 *   sublessor@rsync.test  / sublessor123  (sublessor)
 *   unverified@rsync.test / tenant123     (tenant whose email is NOT verified yet — for Step 3b)
 *  *   admin@rsync.test      / admin123      (admin — Admin Dashboard)
 */
class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
                // Safety: demo accounts (with known passwords) must never exist on the live site.
        if (app()->environment('production')) {
            $this->command?->error('Refusing: demo accounts are never created when APP_ENV=production.');

            return;
        }
        $accounts = [
            // email, name, age, role, password, verified?
            ['landlord@rsync.test', 'Demo Landlord', 35, 'landlord', 'landlord123', true],
            ['tenant@rsync.test', 'Demo Tenant', 20, 'tenant', 'tenant123', true],
            ['tenant2@rsync.test', 'Demo Tenant Two', 21, 'tenant', 'tenant123', true],
            ['sublessor@rsync.test', 'Demo Sublessor', 30, 'sublessor', 'sublessor123', true],
            ['unverified@rsync.test', 'Demo Unverified', 19, 'tenant', 'tenant123', false],
            ['admin@rsync.test', 'Demo Admin', 30, 'admin', 'admin123', true],
        ];

        $users = [];
        foreach ($accounts as [$email, $name, $age, $role, $password, $verified]) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                $user = User::create([
                    'name' => $name,
                    'age' => $age,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'role' => $role,
                ]);
                if ($verified) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }
                $this->command?->info("Created {$email}");
            } else {
                $this->command?->line("{$email} already exists, left unchanged.");
            }

            $users[$email] = $user;
        }

        // A room owned by the demo landlord (the imported rooms have no usable owner).
        $room = Room::firstOrCreate(
            ['room_name' => 'Demo Room A', 'owner_id' => $users['landlord@rsync.test']->user_id],
            ['price' => 3500, 'description' => 'Demo room for testing. Near the university.', 'status' => 'Available', 'max_occupants' => 2]
        );

        // tenant2 currently rents it (1 of 2 slots taken).
        Tenancy::firstOrCreate(
            ['user_id' => $users['tenant2@rsync.test']->user_id, 'room_id' => $room->room_id, 'status' => 'Active'],
            ['start_date' => now()->toDateString(), 'end_date' => now()->addMonth()->toDateString()]
        );

        // tenant has applied and is waiting for the landlord.
        RoomApplication::firstOrCreate(
            ['user_id' => $users['tenant@rsync.test']->user_id, 'room_id' => $room->room_id],
            ['status' => 'Pending']
        );

        $this->command?->info('Demo data ready: Demo Room A (1/2 occupied, 1 pending application).');
    }
}