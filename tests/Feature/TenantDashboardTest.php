<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomApplication;
use App\Models\RoomImage;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Part E — tenant_dashboard.php.
 */
class TenantDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role = 'tenant'): User
    {
        $user = User::create(['name' => 'Kobe', 'age' => 21, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        $user->markEmailAsVerified();

        return $user;
    }

    private function room(string $name, int $price, int $max = 4): Room
    {
        return Room::create(['room_name' => $name, 'price' => $price, 'description' => "About $name", 'max_occupants' => $max]);
    }

    public function test_only_tenants_can_open_it(): void
    {
        $this->get('/tenant/dashboard')->assertRedirect(route('login'));
        $this->actingAs($this->user('l@gmail.com', 'landlord'))->get('/tenant/dashboard')->assertRedirect(route('login'));
        $this->actingAs($this->user('t@gmail.com'))->get('/tenant/dashboard')->assertOk()->assertSee('Your Dashboard');
    }

    public function test_empty_dashboard_shows_the_old_messages(): void
    {
        $this->actingAs($this->user('t@gmail.com'))->get('/tenant/dashboard')
            ->assertSee('to find your next home.')
            ->assertSee('No applications yet.')
            ->assertSee('href="' . route('rooms.index') . '"', false)
            ->assertSee('href="' . route('logout') . '"', false);
    }

    public function test_active_rentals_with_dates_days_and_occupancy(): void
    {
        $me = $this->user('t@gmail.com');
        $other = $this->user('o@gmail.com');
        $room = $this->room('Room 201', 4500, 4);
        Tenancy::create(['user_id' => $me->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-05-01', 'end_date' => '2026-08-29', 'status' => 'Active']);
        Tenancy::create(['user_id' => $other->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-05-01', 'end_date' => '2026-06-01', 'status' => 'Active']);
        // Not shown: an old rental and someone else's rental
        Tenancy::create(['user_id' => $me->user_id, 'room_id' => $this->room('Old Room', 1000)->room_id, 'start_date' => '2025-01-01', 'end_date' => '2025-02-01', 'status' => 'Left']);

        $this->actingAs($me)->get('/tenant/dashboard')
            ->assertSee('Room 201')
            ->assertSee('₱4,500')
            ->assertSee('May 01, 2026 - Aug 29, 2026')
            ->assertSee('120 days')
            ->assertSee('2/4 occupants')
            ->assertDontSee('Old Room');
    }

    public function test_applications_newest_first_with_status_messages(): void
    {
        $me = $this->user('t@gmail.com');
        $a = $this->room('Room A', 1000);
        $b = $this->room('Room B', 2000);
        $c = $this->room('Room C', 3000);
        RoomImage::create(['room_id' => $a->room_id, 'filename' => 'a.jpg', 'sort_order' => 0]);
        RoomApplication::create(['user_id' => $me->user_id, 'room_id' => $a->room_id, 'status' => 'Verified', 'applied_at' => '2026-04-20 14:05:00']);
        RoomApplication::create(['user_id' => $me->user_id, 'room_id' => $b->room_id, 'status' => 'Pending', 'applied_at' => '2026-05-02 09:30:00']);
        RoomApplication::create(['user_id' => $me->user_id, 'room_id' => $c->room_id, 'status' => 'Rejected', 'applied_at' => '2026-05-03 16:45:00']);
        // someone else's application is not shown
        RoomApplication::create(['user_id' => $this->user('o@gmail.com')->user_id, 'room_id' => $a->room_id, 'status' => 'Pending']);

        $this->actingAs($me)->get('/tenant/dashboard')
            ->assertSeeInOrder(['Room C', 'Room B', 'Room A'])
            ->assertSee('Applied:</strong> May 03, 2026 at 04:45 PM', false)
            ->assertSee('⏳ Waiting for landlord approval')
            ->assertSee('✓ Your application was verified! Proceed to booking.')
            ->assertSee('✕ Your application was rejected. Try another room.')
            ->assertSee('class="room-card-status pending"', false)
            ->assertSee(asset('images/a.jpg'), false)
            ->assertSee('₱2,000');

        $this->assertSame(3, substr_count($this->actingAs($me)->get('/tenant/dashboard')->getContent(), 'class="room-card-name"'));
    }

    public function test_room_text_is_escaped(): void
    {
        $me = $this->user('t@gmail.com');
        $room = $this->room('<script>x()</script>', 1000);
        RoomApplication::create(['user_id' => $me->user_id, 'room_id' => $room->room_id, 'status' => 'Pending']);

        $this->actingAs($me)->get('/tenant/dashboard')->assertDontSee('<script>x()</script>', false);
    }

    public function test_one_time_message_like_the_old_session_msg(): void
    {
        $me = $this->user('t@gmail.com');

        $this->actingAs($me)->withSession(['msg' => 'err:Something failed.'])->get('/tenant/dashboard')
            ->assertSee('<div class="toast err">Something failed.</div>', false);
        $this->actingAs($me)->get('/tenant/dashboard')->assertDontSee('Something failed.');
    }
}