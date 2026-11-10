<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Part G — maintenance.php (landlord's Maintenance link).
 */
class MaintenancePageTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role): User
    {
        $u = User::create(['name' => 'X', 'age' => 30, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        $u->markEmailAsVerified();

        return $u;
    }

    private function request(User $tenant, Room $room, string $text, string $status, string $at): MaintenanceRequest
    {
        $r = MaintenanceRequest::create(['tenant_id' => $tenant->user_id, 'room_id' => $room->room_id, 'description' => $text, 'status' => $status]);
        $r->forceFill(['submitted_at' => $at])->save();

        return $r;
    }

    public function test_only_landlords_can_open_it(): void
    {
        $this->get('/landlord/maintenance')->assertRedirect(route('login'));
        $this->actingAs($this->user('t@gmail.com', 'tenant'))->get('/landlord/maintenance')->assertRedirect(route('login'));
    }

    public function test_empty_page_shows_the_old_messages(): void
    {
        $this->actingAs($this->user('l@gmail.com', 'landlord'))->get('/landlord/maintenance')
            ->assertOk()
            ->assertSee('No maintenance requests found.')
            ->assertSee('No status data available')
            ->assertSee('href="' . route('landlord.dashboard') . '"', false);
    }

    public function test_newest_8_requests_of_my_rooms_and_counts_per_status(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        $tenant = $this->user('t@gmail.com', 'tenant');
        $mine = Room::create(['owner_id' => $me->user_id, 'room_name' => 'Mine', 'price' => 1, 'max_occupants' => 1]);
        $theirs = Room::create(['owner_id' => $this->user('o@gmail.com', 'landlord')->user_id, 'room_name' => 'Theirs', 'price' => 1, 'max_occupants' => 1]);

        foreach (range(1, 9) as $i) {
            $this->request($tenant, $mine, "Problem $i", $i % 3 === 0 ? 'Completed' : ($i % 3 === 1 ? 'Pending' : 'In Progress'), "2026-05-0$i 10:00:00");
        }
        $this->request($tenant, $theirs, 'Not my room', 'Pending', '2026-06-01 10:00:00');

        $response = $this->actingAs($me)->get('/landlord/maintenance')->assertOk();

        $response->assertSeeInOrder(['Problem 9', 'Problem 8', 'Problem 2'])
            ->assertDontSee('Problem 1<', false)      // only the 8 newest
            ->assertDontSee('Not my room')
            ->assertSee('2026-05-09 10:00:00')
            ->assertSee('class="badge inprogress"', false)
            // counts: Pending 3 (1,4,7), In Progress 3 (2,5,8), Completed 3 (3,6,9) — in the old order
            ->assertSeeInOrder(['<p class="count">3</p>', 'Pending', 'In Progress', 'Completed'], false);
    }

    public function test_old_address_redirects(): void
    {
        $this->get('/maintenance.php')->assertRedirect(route('landlord.maintenance'));
    }
}