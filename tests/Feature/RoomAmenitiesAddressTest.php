<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature 2: a written address and an amenities checklist for rooms.
 */
class RoomAmenitiesAddressTest extends TestCase
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

    private function landlord(string $email = 'l@gmail.com'): User
    {
        $u = User::create(['name' => 'Lando', 'age' => 30, 'email' => $email, 'password' => Hash::make('x'), 'role' => 'landlord']);
        $u->markEmailAsVerified();

        return $u;
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'room_name' => 'Room 305', 'price' => '3900', 'max_occupants' => '2', 'description' => 'Near the school',
            'address' => '12 Lower Magsaysay Ave, Brgy. Lourdes',
            'amenities' => ['wifi', 'own_cr'],
            'images' => [UploadedFile::fake()->image('cover.jpg')],
        ], $overrides);
    }

    private function room(User $owner, array $extra = []): Room
    {
        $room = Room::create(array_merge(['owner_id' => $owner->user_id, 'room_name' => 'Room 201', 'price' => 4500, 'status' => 'Available', 'max_occupants' => 2], $extra));
        RoomImage::create(['room_id' => $room->room_id, 'filename' => 'a.jpg', 'sort_order' => 0]);

        return $room;
    }

    public function test_landlord_adds_a_room_with_address_and_amenities(): void
    {
        $me = $this->landlord();

        $this->actingAs($me)->post('/landlord/rooms', $this->form([
            'address' => '  12   Lower Magsaysay Ave,  Brgy. Lourdes ',
            'amenities' => ['cctv', 'wifi', 'wifi'],
        ]))->assertSessionHas('msg', 'Room added successfully.');

        $room = Room::first();
        $this->assertSame('12 Lower Magsaysay Ave, Brgy. Lourdes', $room->address);   // extra spaces removed
        $this->assertSame(['wifi', 'cctv'], $room->amenities);                         // list order, no duplicates
    }

    public function test_address_is_required_for_a_new_room(): void
    {
        $me = $this->landlord();

        $this->actingAs($me)->post('/landlord/rooms', $this->form(['address' => '']))
            ->assertSessionHas('msg', 'err:Please enter the address of the room (street and barangay), e.g. 12 Lower Magsaysay Ave, Brgy. Lourdes.');
        $this->post('/landlord/rooms', $this->form(['address' => str_repeat('a', 256)]))
            ->assertSessionHas('msg', 'err:The address must be 255 characters or fewer.');

        $this->assertSame(0, Room::count());
    }

    public function test_unknown_amenities_are_rejected(): void
    {
        $me = $this->landlord();

        $this->actingAs($me)->post('/landlord/rooms', $this->form(['amenities' => ['wifi', 'swimming_pool']]))
            ->assertSessionHas('msg', 'err:Please choose amenities from the list.');
        $this->post('/landlord/rooms', $this->form(['amenities' => 'wifi']))
            ->assertSessionHas('msg', 'err:Please choose amenities from the list.');

        $this->assertSame(0, Room::count());
    }

    public function test_edit_changes_address_and_amenities(): void
    {
        $me = $this->landlord();
        $room = $this->room($me, ['address' => 'Old St, Brgy. Old', 'amenities' => ['wifi']]);

        $this->actingAs($me)->post('/landlord/rooms/update', $this->form([
            'room_id' => $room->room_id, 'address' => '5 Session Rd, Brgy. Session', 'amenities' => ['parking'], 'images' => [],
        ]))->assertSessionHas('msg', 'Room updated.');

        $room->refresh();
        $this->assertSame('5 Session Rd, Brgy. Session', $room->address);
        $this->assertSame(['parking'], $room->amenities);

        // No boxes ticked = no amenities.
        $this->post('/landlord/rooms/update', $this->form(['room_id' => $room->room_id, 'amenities' => null, 'images' => []]));
        $this->assertSame([], $room->fresh()->amenities);
    }

    public function test_old_rooms_without_an_address_can_still_be_edited_but_an_address_cannot_be_removed(): void
    {
        $me = $this->landlord();
        $old = $this->room($me);   // added before addresses existed

        $this->actingAs($me)->post('/landlord/rooms/update', $this->form(['room_id' => $old->room_id, 'room_name' => 'Renamed', 'address' => '', 'images' => []]))
            ->assertSessionHas('msg', 'Room updated.');
        $this->assertSame('Renamed', $old->fresh()->room_name);
        $this->assertNull($old->fresh()->address);

        // The dashboard reminds the landlord to add it.
        $this->get('/landlord/dashboard')->assertSee('No address yet');

        $withAddress = $this->room($me, ['address' => 'Old St, Brgy. Old']);
        $this->post('/landlord/rooms/update', $this->form(['room_id' => $withAddress->room_id, 'address' => '', 'images' => []]))
            ->assertSessionHas('msg', 'err:Please enter the address of the room (street and barangay), e.g. 12 Lower Magsaysay Ave, Brgy. Lourdes.');
        $this->assertSame('Old St, Brgy. Old', $withAddress->fresh()->address);
    }

    public function test_cannot_edit_another_landlords_room(): void
    {
        $theirs = $this->room($this->landlord('other@gmail.com'), ['address' => 'Their St']);

        $this->actingAs($this->landlord())->post('/landlord/rooms/update', $this->form(['room_id' => $theirs->room_id, 'address' => 'Hacked St', 'images' => []]))
            ->assertSessionHas('msg', 'err:Failed to update. You can only change your own rooms.');
        $this->assertSame('Their St', $theirs->fresh()->address);
    }

    public function test_dashboard_shows_address_and_passes_safe_data_to_the_edit_button(): void
    {
        $me = $this->landlord();
        $this->room($me, ['address' => '7 "Quote" St <b>', 'amenities' => ['wifi', 'cctv']]);

        $this->actingAs($me)->get('/landlord/dashboard')->assertOk()
            ->assertSee('7 &quot;Quote&quot; St &lt;b&gt;', false)
            ->assertDontSee('<b>', false)
            ->assertSee('data-amenities="[&quot;wifi&quot;,&quot;cctv&quot;]"', false)
            ->assertSee('name="amenities[]" value="pet_friendly"', false)
            ->assertSee('Pet-friendly');
    }

    public function test_room_page_and_cards_show_address_and_amenity_chips(): void
    {
        $room = $this->room($this->landlord(), ['address' => '12 Lower Magsaysay Ave, Brgy. Lourdes', 'amenities' => ['wifi', 'own_cr']]);

        $this->get('/rooms/' . $room->room_id)->assertOk()
            ->assertSee('12 Lower Magsaysay Ave, Brgy. Lourdes')
            ->assertSee('Amenities')
            ->assertSee('bx-wifi', false)
            ->assertSeeInOrder(['WiFi', 'Own CR / Bathroom'])
            ->assertDontSee('Air-conditioning');

        $this->get('/rooms')->assertSee('12 Lower Magsaysay Ave, Brgy. Lourdes');
        $this->get('/')->assertSee('12 Lower Magsaysay Ave, Brgy. Lourdes');
    }

    public function test_room_without_amenities_has_no_amenities_section(): void
    {
        $room = $this->room($this->landlord());

        $this->get('/rooms/' . $room->room_id)->assertOk()->assertDontSee('<h4>Amenities</h4>', false);
    }

    public function test_search_finds_rooms_by_address(): void
    {
        $me = $this->landlord();
        $this->room($me, ['room_name' => 'Sunny Loft', 'address' => '3 Legarda Rd, Brgy. Bakakeng']);
        $this->room($me, ['room_name' => 'Other', 'address' => 'Session Rd']);

        $this->assertSame(['Sunny Loft'], collect($this->getJson('/?search=legarda')->json())->pluck('room_name')->all());

        $this->get('/rooms?name=BAKAKENG')->assertSee('Sunny Loft')->assertDontSee('>Other<', false);
    }

    public function test_demo_seeder_gives_the_demo_room_an_address_and_amenities_only_when_creating_it(): void
    {
        $this->seed(\Database\Seeders\DemoAccountsSeeder::class);
        $room = Room::where('room_name', 'Demo Room A')->first();
        $this->assertSame('12 Lower Magsaysay Ave, Brgy. Lourdes', $room->address);
        $this->assertContains('wifi', $room->amenities);

        // Running again leaves a changed room alone.
        $room->update(['address' => 'Changed St', 'amenities' => []]);
        $this->seed(\Database\Seeders\DemoAccountsSeeder::class);
        $this->assertSame('Changed St', $room->fresh()->address);
        $this->assertSame([], $room->fresh()->amenities);
        $this->assertSame(1, Room::where('room_name', 'Demo Room A')->count());
    }
}
