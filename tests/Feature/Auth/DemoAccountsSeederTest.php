<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomApplication;
use App\Models\Tenancy;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_demo_accounts_room_tenancy_and_application(): void
    {
        $this->seed(DemoAccountsSeeder::class);

        $this->assertSame(5, User::where('email', 'like', '%@rsync.test')->count());
        $landlord = User::where('email', 'landlord@rsync.test')->first();
        $room = Room::where('room_name', 'Demo Room A')->first();

        $this->assertSame($landlord->user_id, $room->owner_id);
        $this->assertSame(1, $room->activeTenancies()->count());
        $this->assertSame('Pending', RoomApplication::where('room_id', $room->room_id)->value('status'));
        $this->assertNotNull($landlord->email_verified_at);
        $this->assertNull(User::where('email', 'unverified@rsync.test')->value('email_verified_at'));
    }

    public function test_every_demo_account_can_log_in_and_lands_on_the_right_page(): void
    {
        $this->seed(DemoAccountsSeeder::class);

        $cases = [
            ['landlord@rsync.test', 'landlord123', route('landlord.dashboard')],
            ['tenant@rsync.test', 'tenant123', route('tenant.dashboard')],
            ['tenant2@rsync.test', 'tenant123', route('tenant.dashboard')],
            ['sublessor@rsync.test', 'sublessor123', route('home')],
            ['unverified@rsync.test', 'tenant123', route('tenant.dashboard')],
        ];

        foreach ($cases as [$email, $password, $target]) {
            $this->post('/login', ['email' => $email, 'password' => $password])->assertRedirect($target);
            $this->get('/logout');
        }
    }

    public function test_running_twice_adds_nothing_and_keeps_changed_passwords(): void
    {
        $this->seed(DemoAccountsSeeder::class);
        User::where('email', 'tenant@rsync.test')->update(['password' => Hash::make('changed-by-me')]);
        $counts = [User::count(), Room::count(), Tenancy::count(), RoomApplication::count()];

        $this->seed(DemoAccountsSeeder::class);

        $this->assertSame($counts, [User::count(), Room::count(), Tenancy::count(), RoomApplication::count()]);
        $this->assertTrue(Hash::check('changed-by-me', User::where('email', 'tenant@rsync.test')->value('password')));
    }

    public function test_existing_data_is_not_touched(): void
    {
        $existing = User::create(['name' => 'kobe', 'age' => 21, 'email' => 'kobe@gmail.com', 'password' => Hash::make('x'), 'role' => 'tenant']);

        $this->seed(DemoAccountsSeeder::class);

        $this->assertSame('kobe', $existing->fresh()->name);
        $this->assertTrue(Hash::check('x', $existing->fresh()->password));
    }
}