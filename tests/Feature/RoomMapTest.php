<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomImage;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Map pins: the landlord drops a pin, the home page and the room page show it.
 */
class RoomMapTest extends TestCase
{
    use RefreshDatabase;

    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Uploaded photos go to a temporary folder, never to the real public/images.
        $this->publicDir = sys_get_temp_dir() . '/rsync-test-public-' . uniqid();
        File::makeDirectory($this->publicDir . '/images', 0755, true);
        $this->app->usePublicPath($this->publicDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicDir);
        parent::tearDown();
    }

    private function landlord(string $email = 'me@gmail.com'): User
    {
        $u = User::create(['name' => 'Landlord', 'age' => 30, 'email' => $email, 'password' => Hash::make('x'), 'role' => 'landlord']);
        $u->markEmailAsVerified();

        return $u;
    }

    private function room(string $name, array $extra = []): Room
    {
        return Room::create(array_merge(['room_name' => $name, 'price' => 4000, 'description' => 'Nice', 'status' => 'Available', 'max_occupants' => 2], $extra));
    }

    private function roomForm(array $overrides = []): array
    {
        return array_merge([
            'room_name' => 'Room 305', 'price' => '3900', 'max_occupants' => '2', 'description' => 'Near the school',
            'images' => [UploadedFile::fake()->image('cover.jpg')],
        ], $overrides);
    }

    // ---------- Landlord drops a pin ----------

    public function test_landlord_can_add_a_room_with_a_pin(): void
    {
        $me = $this->landlord();

        $this->actingAs($me)->post('/landlord/rooms', $this->roomForm(['latitude' => '16.4023001', 'longitude' => '120.5960002']))
            ->assertRedirect(route('landlord.dashboard'));

        $room = Room::firstWhere('room_name', 'Room 305');
        $this->assertTrue($room->hasLocation());
        $this->assertEqualsWithDelta(16.4023001, $room->latitude, 0.0000001);
        $this->assertEqualsWithDelta(120.5960002, $room->longitude, 0.0000001);
    }

    public function test_pin_is_optional(): void
    {
        $this->actingAs($this->landlord())->post('/landlord/rooms', $this->roomForm(['latitude' => '', 'longitude' => '']));

        $room = Room::firstWhere('room_name', 'Room 305');
        $this->assertNotNull($room);
        $this->assertFalse($room->hasLocation());
    }

    public function test_bad_or_half_pins_are_refused(): void
    {
        $me = $this->landlord();

        $this->actingAs($me)->post('/landlord/rooms', $this->roomForm(['latitude' => '91', 'longitude' => '120']));
        $this->post('/landlord/rooms', $this->roomForm(['latitude' => '16.4', 'longitude' => '181']));
        $this->post('/landlord/rooms', $this->roomForm(['latitude' => '16.4']));              // longitude missing
        $this->post('/landlord/rooms', $this->roomForm(['latitude' => 'abc', 'longitude' => '120']));

        $this->assertSame(0, Room::count());
    }

    public function test_landlord_can_move_and_remove_a_pin(): void
    {
        $me = $this->landlord();
        $room = $this->room('Room 1', ['owner_id' => $me->user_id, 'latitude' => 16.4, 'longitude' => 120.5]);

        $this->actingAs($me)->post('/landlord/rooms/update', [
            'room_id' => $room->room_id, 'room_name' => 'Room 1', 'price' => '4000', 'max_occupants' => '2',
            'latitude' => '16.41', 'longitude' => '120.61',
        ]);
        $room->refresh();
        $this->assertEqualsWithDelta(16.41, $room->latitude, 0.0000001);
        $this->assertEqualsWithDelta(120.61, $room->longitude, 0.0000001);

        $this->post('/landlord/rooms/update', [
            'room_id' => $room->room_id, 'room_name' => 'Room 1', 'price' => '4000', 'max_occupants' => '2',
            'latitude' => '', 'longitude' => '',
        ]);
        $this->assertFalse($room->refresh()->hasLocation());
    }

    public function test_update_without_pin_fields_keeps_the_pin(): void
    {
        $me = $this->landlord();
        $room = $this->room('Room 1', ['owner_id' => $me->user_id, 'latitude' => 16.4, 'longitude' => 120.5]);

        $this->actingAs($me)->post('/landlord/rooms/update', ['room_id' => $room->room_id, 'room_name' => 'Renamed', 'price' => '4000', 'max_occupants' => '2']);

        $room->refresh();
        $this->assertSame('Renamed', $room->room_name);
        $this->assertTrue($room->hasLocation());
    }

    public function test_dashboard_has_the_pin_picker_and_current_pin_on_the_edit_button(): void
    {
        $me = $this->landlord();
        $this->room('Pinned', ['owner_id' => $me->user_id, 'latitude' => 16.4023, 'longitude' => 120.596]);

        $this->actingAs($me)->get('/landlord/dashboard')->assertOk()
            ->assertSee('data-picker', false)
            ->assertSee('data-lat="16.4023"', false)
            ->assertSee('data-lng="120.596"', false);
    }

    // ---------- Home page ----------

    public function test_home_map_lists_only_pinned_rooms_that_still_have_a_free_slot(): void
    {
        $this->room('Pinned Room', ['latitude' => 16.4, 'longitude' => 120.5]);
        $this->room('No Pin Room');
        $full = $this->room('Full Pinned Room', ['max_occupants' => 1, 'latitude' => 16.5, 'longitude' => 120.6]);
        $tenant = User::create(['name' => 'T', 'age' => 20, 'email' => 't@gmail.com', 'password' => Hash::make('x'), 'role' => 'tenant']);
        Tenancy::create(['room_id' => $full->room_id, 'user_id' => $tenant->user_id, 'start_date' => '2026-01-01', 'end_date' => '2027-01-01', 'status' => 'Active']);

        $response = $this->get('/')->assertOk()
            ->assertSee('id="rooms-map"', false)
            ->assertSee('Pinned Room');
        $mapRooms = $response->viewData('mapRooms');

        $this->assertSame(['Pinned Room'], $mapRooms->pluck('name')->all());
    }

    public function test_home_has_no_map_section_when_nothing_is_pinned(): void
    {
        $this->room('No Pin Room');

        $this->get('/')->assertOk()->assertDontSee('id="rooms-map"', false);
    }

    // ---------- Room page ----------

    public function test_room_page_shows_a_map_only_when_the_room_has_a_pin(): void
    {
        $pinned = $this->room('Pinned', ['latitude' => 16.4023, 'longitude' => 120.596]);
        $plain = $this->room('Plain');

        $this->get('/rooms/' . $pinned->room_id)->assertOk()
            ->assertSee('id="room-map"', false)
            ->assertSee('data-lat="16.4023"', false);

        $this->get('/rooms/' . $plain->room_id)->assertOk()->assertDontSee('id="room-map"', false);
    }
}
