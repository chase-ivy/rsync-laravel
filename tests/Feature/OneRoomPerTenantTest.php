<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomApplication;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Business rule: a tenant rents one room at a time.
 * A tenant with an Active contract can't apply for rooms, and a landlord can't assign them to a second room.
 */
class OneRoomPerTenantTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role = 'tenant'): User
    {
        $user = User::create(['name' => 'Kobe', 'age' => 21, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        $user->markEmailAsVerified();

        return $user;
    }

    private function room(string $name, ?User $owner = null, int $max = 2): Room
    {
        return Room::create(['room_name' => $name, 'price' => 4500, 'description' => 'x', 'status' => 'Available', 'max_occupants' => $max, 'owner_id' => $owner?->user_id]);
    }

    private function rent(User $tenant, Room $room, string $status = 'Active'): Tenancy
    {
        return Tenancy::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-05-01', 'end_date' => '2026-12-01', 'status' => $status]);
    }

    public function test_tenant_with_a_room_cannot_apply_for_another(): void
    {
        $tenant = $this->user('t@gmail.com');
        $this->rent($tenant, $this->room('Room 101'));
        $other = $this->room('Room 202');

        $this->actingAs($tenant)->post('/rooms/apply', ['room_id' => $other->room_id])
            ->assertExactJson(['status' => 'error', 'message' => 'You already have a room (Room 101). You can apply for another room after your current stay ends.']);

        $this->assertSame(0, RoomApplication::count());
    }

    public function test_tenant_whose_stay_ended_can_apply_again(): void
    {
        $tenant = $this->user('t@gmail.com');
        $this->rent($tenant, $this->room('Room 101'), 'Left');
        $other = $this->room('Room 202');

        $this->actingAs($tenant)->post('/rooms/apply', ['room_id' => $other->room_id])
            ->assertJsonPath('status', 'success');
    }

    public function test_room_page_shows_why_tenant_cannot_apply(): void
    {
        $tenant = $this->user('t@gmail.com');
        $mine = $this->room('Room 101');
        $other = $this->room('Room 202');
        $this->rent($tenant, $mine);

        $this->actingAs($tenant)->get('/rooms/' . $other->room_id)->assertSee('You already have a room')->assertDontSee('Apply Now');
        $this->actingAs($tenant)->get('/rooms/' . $mine->room_id)->assertSee('You live here')->assertDontSee('Apply Now');
    }

    public function test_home_page_hides_apply_buttons_for_tenant_with_a_room(): void
    {
        $tenant = $this->user('t@gmail.com');
        $this->room('Room 202');

        $this->actingAs($tenant)->get('/')->assertSee('Apply</button>', false);

        $this->rent($tenant, $this->room('Room 101'));
        $this->actingAs($tenant)->get('/')->assertDontSee('Apply</button>', false);
    }

    public function test_landlord_cannot_assign_a_tenant_who_already_has_a_room(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $tenant = $this->user('t@gmail.com');
        $this->rent($tenant, $this->room('Room 101'));
        $mine = $this->room('Room 305', $landlord);

        $this->actingAs($landlord)->post('/landlord/tenants/assign', [
            'room_id' => $mine->room_id, 'user_id' => $tenant->user_id, 'start_date' => '2026-06-01', 'end_date' => '2026-12-01',
        ])->assertExactJson(['status' => 'error', 'message' => 'Kobe already has a room (Room 101). Remove them there first.']);

        $this->assertSame(0, $mine->tenancies()->count());
    }

    public function test_landlord_can_assign_after_the_old_stay_ended(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $tenant = $this->user('t@gmail.com');
        $this->rent($tenant, $this->room('Room 101'), 'Left');
        $mine = $this->room('Room 305', $landlord);

        $this->actingAs($landlord)->post('/landlord/tenants/assign', [
            'room_id' => $mine->room_id, 'user_id' => $tenant->user_id, 'start_date' => '2026-06-01', 'end_date' => '2026-12-01',
        ])->assertExactJson(['status' => 'success']);
    }
}