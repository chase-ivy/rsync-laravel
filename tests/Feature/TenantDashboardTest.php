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
            ->assertSee('May 01, 2026 to Aug 29, 2026')
            ->assertSee('left of 120')
            ->assertSee('2 of 4 <small>occupants</small>', false)
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

    public function test_next_rent_due_is_the_next_monthly_anniversary_inside_the_contract(): void
    {
        $t = new Tenancy(['start_date' => '2026-01-31', 'end_date' => '2026-06-29']);

        $this->assertSame('2026-01-31', $t->nextRentDue(\Carbon\Carbon::parse('2026-01-10'))->toDateString());   // before move-in: first rent
        $this->assertSame('2026-01-31', $t->nextRentDue(\Carbon\Carbon::parse('2026-01-31'))->toDateString());   // due today
        $this->assertSame('2026-02-28', $t->nextRentDue(\Carbon\Carbon::parse('2026-02-01'))->toDateString());   // short month: no overflow
        $this->assertSame('2026-05-31', $t->nextRentDue(\Carbon\Carbon::parse('2026-05-15'))->toDateString());
        $this->assertNull($t->nextRentDue(\Carbon\Carbon::parse('2026-06-05')));                                // next one is after the end
    }

    public function test_stay_months_mark_past_current_and_coming_months(): void
    {
        $t = new Tenancy(['start_date' => '2026-10-15', 'end_date' => '2027-01-14']);
        $months = $t->stayMonths(\Carbon\Carbon::parse('2026-11-20'));

        $this->assertSame(['Oct', 'Nov', 'Dec', 'Jan'], array_column($months, 'label'));
        $this->assertSame(['past', 'now', 'next', 'next'], array_column($months, 'state'));
        $this->assertSame(2027, $months[3]['year']);
    }

    public function test_dashboard_shows_next_rent_and_totals_paid(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-15 10:00', 'Asia/Manila'));
        $me = $this->user('t@gmail.com');
        $room = $this->room('Room 201', 4500);
        Tenancy::create(['user_id' => $me->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'status' => 'Active', 'monthly_rent' => 4500]);
        \App\Models\Payment::create(['user_id' => $me->user_id, 'amount' => 4500, 'date' => '2026-10-01', 'status' => 'Verified']);
        \App\Models\Payment::create(['user_id' => $me->user_id, 'amount' => 4500, 'date' => '2026-11-01', 'status' => 'Verified']);
        \App\Models\Payment::create(['user_id' => $me->user_id, 'amount' => 1000, 'date' => '2026-11-10', 'status' => 'Pending']);

        $this->actingAs($me)->get('/tenant/dashboard')
            ->assertSee('Next rent')
            ->assertSee('due Tue, Dec 1')
            ->assertSee('In 16 days')
            ->assertSee('Paid and confirmed: <strong>₱9,000.00</strong>', false)
            ->assertSee('Waiting for confirmation: <strong>₱1,000.00</strong>', false);
    }
}
