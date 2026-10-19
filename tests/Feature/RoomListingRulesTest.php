<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomImage;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Full rooms are hidden from the public lists, and every room needs at least one photo.
 */
class RoomListingRulesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role = 'tenant'): User
    {
        $user = User::create(['name' => 'Kobe', 'age' => 21, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        $user->markEmailAsVerified();

        return $user;
    }

    /** A room with $max slots and $taken active tenants. */
    private function room(string $name, int $max, int $taken, ?User $owner = null): Room
    {
        $room = Room::create(['room_name' => $name, 'price' => 4500, 'description' => 'x', 'status' => 'Available', 'max_occupants' => $max, 'owner_id' => $owner?->user_id]);
        for ($i = 0; $i < $taken; $i++) {
            Tenancy::create(['user_id' => $this->user("t{$name}{$i}@gmail.com")->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-05-01', 'end_date' => '2026-12-01', 'status' => 'Active']);
        }

        return $room;
    }

    public function test_full_rooms_are_hidden_from_the_rooms_page(): void
    {
        $this->room('Open Room', 2, 1);
        $this->room('Full Room', 1, 1);

        $this->get('/rooms')->assertSee('Open Room')->assertDontSee('Full Room');
    }

    public function test_full_rooms_are_hidden_from_home_featured_and_search(): void
    {
        $this->room('Open Room', 2, 0);
        $this->room('Full Room', 2, 2);

        $this->get('/')->assertSee('Open Room')->assertDontSee('Full Room');
        $this->getJson('/?search=room')->assertJsonCount(1)->assertJsonPath('0.room_name', 'Open Room');
    }

    public function test_a_full_room_page_still_opens_from_a_direct_link(): void
    {
        $room = $this->room('Full Room', 1, 1);

        $this->get('/rooms/' . $room->room_id)->assertOk()->assertSee('Full Room');
    }

    public function test_new_room_needs_at_least_one_photo(): void
    {
        $me = $this->user('l@gmail.com', 'landlord');

        $this->actingAs($me)->post('/landlord/rooms', ['room_name' => 'Room 305', 'price' => 3900, 'max_occupants' => 2, 'description' => 'x'])
            ->assertRedirect(route('landlord.dashboard'));
        $this->get('/landlord/dashboard')->assertSee('Please add at least one photo of the room.');

        $this->assertSame(0, Room::count());
    }

    public function test_the_last_photo_cannot_be_removed(): void
    {
        $me = $this->user('l@gmail.com', 'landlord');
        $room = $this->room('Room 305', 2, 0, $me);
        $img = RoomImage::create(['room_id' => $room->room_id, 'filename' => 'only.jpg', 'sort_order' => 0]);

        $this->actingAs($me)->post('/landlord/rooms/images/delete', ['image_id' => $img->image_id, 'room_id' => $room->room_id])
            ->assertJsonPath('status', 'error');

        $this->assertNotNull($img->fresh());
    }
}