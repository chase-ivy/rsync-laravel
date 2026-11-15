<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomApplication;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Applicants window: Verified applicants get "Assign to room" (or the reason they can't be assigned).
 */
class ApplicantAssignTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role): User
    {
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'age' => 30, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        $u->markEmailAsVerified();

        return $u;
    }

    private function room(User $owner, string $name = 'Room 1', int $max = 2): Room
    {
        return Room::create(['owner_id' => $owner->user_id, 'room_name' => $name, 'price' => 3000, 'max_occupants' => $max, 'status' => 'Available']);
    }

    private function apply(User $tenant, Room $room, string $status): RoomApplication
    {
        return RoomApplication::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'status' => $status]);
    }

    private function live(User $tenant, Room $room): Tenancy
    {
        return Tenancy::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-06-01', 'end_date' => '2026-12-01', 'status' => 'Active']);
    }

    private function list(User $landlord, Room $room): array
    {
        return collect($this->actingAs($landlord)->post('/landlord/rooms/applicants', ['room_id' => $room->room_id])->assertOk()->json())
            ->keyBy('name')->all();
    }

    public function test_json_has_assign_fields(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $room = $this->room($landlord);
        $other = $this->room($landlord, 'Room 2');
        $free = $this->user('free@gmail.com', 'tenant');
        $housed = $this->user('housed@gmail.com', 'tenant');
        $here = $this->user('here@gmail.com', 'tenant');
        $pending = $this->user('pending@gmail.com', 'tenant');
        $rejected = $this->user('rejected@gmail.com', 'tenant');
        $this->apply($free, $room, 'Verified');
        $this->apply($housed, $room, 'Verified');
        $this->apply($here, $room, 'Verified');
        $this->apply($pending, $room, 'Pending');
        $this->apply($rejected, $room, 'Rejected');
        $this->live($housed, $other);
        $this->live($here, $room);

        $apps = $this->list($landlord, $room);

        $this->assertSame(['user_id' => $free->user_id, 'can_assign' => true, 'reason' => null],
            array_intersect_key($apps['Free'], array_flip(['user_id', 'can_assign', 'reason'])));
        $this->assertFalse($apps['Housed']['can_assign']);
        $this->assertSame('Already has a room', $apps['Housed']['reason']);
        $this->assertSame('Already lives here', $apps['Here']['reason']);
        $this->assertFalse($apps['Pending']['can_assign']);
        $this->assertNull($apps['Pending']['reason']);
        $this->assertFalse($apps['Rejected']['can_assign']);
        // Old fields are still there.
        $this->assertSame(['application_id', 'user_id', 'status', 'applied_at', 'name', 'email', 'can_assign', 'reason'], array_keys($apps['Free']));
    }

    public function test_full_room_cannot_assign(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $room = $this->room($landlord, 'Room 1', 1);
        $this->apply($a = $this->user('a@gmail.com', 'tenant'), $room, 'Verified');
        $this->live($this->user('b@gmail.com', 'tenant'), $room);

        $apps = $this->list($landlord, $room);
        $this->assertFalse($apps['A']['can_assign']);
        $this->assertSame('Room is full', $apps['A']['reason']);
    }

    public function test_assigning_a_verified_applicant_works_and_then_shows_the_reason(): void
    {
        Notification::fake();
        $landlord = $this->user('l@gmail.com', 'landlord');
        $room = $this->room($landlord);
        $tenant = $this->user('a@gmail.com', 'tenant');
        $this->apply($tenant, $room, 'Verified');

        $this->assertTrue($this->list($landlord, $room)['A']['can_assign']);
        $this->actingAs($landlord)->post('/landlord/tenants/assign', ['room_id' => $room->room_id, 'user_id' => $tenant->user_id, 'start_date' => '2026-12-01', 'end_date' => '2027-06-01'])
            ->assertExactJson(['status' => 'success']);

        $apps = $this->list($landlord, $room);
        $this->assertFalse($apps['A']['can_assign']);
        $this->assertSame('Already lives here', $apps['A']['reason']);
    }

    public function test_someone_elses_room_gives_nothing(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $theirs = $this->room($this->user('o@gmail.com', 'landlord'));
        $this->apply($this->user('a@gmail.com', 'tenant'), $theirs, 'Verified');

        $this->actingAs($landlord)->post('/landlord/rooms/applicants', ['room_id' => $theirs->room_id])->assertExactJson([]);
    }

    public function test_page_script_has_the_assign_button(): void
    {
        $js = file_get_contents(resource_path('js/landlord-dashboard.js'));

        $this->assertStringContainsString('Assign to room', $js);
        $this->assertStringContainsString('esc(app.reason', $js);
        $this->assertStringContainsString('window.openAssign = function (rid, userId)', $js);
    }
}
