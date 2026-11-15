<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\Tenancy;
use App\Models\User;
use App\Support\RoomFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Room filters on the home page and /rooms (location, budget, room type, keyword),
 * no occupant counts on public pages, and the map data.
 */
class RoomFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function room(string $name, array $extra = []): Room
    {
        return Room::create(array_merge([
            'room_name' => $name, 'price' => 3000, 'description' => 'Nice', 'status' => 'Available', 'max_occupants' => 2,
        ], $extra));
    }

    /** Names of the room cards on a page, in order. */
    private function cards(string $url): array
    {
        preg_match_all('/<h3 class="c-name"><a [^>]*>([^<]+)<\/a>/', $this->get($url)->assertOk()->getContent(), $m);

        return array_map('html_entity_decode', $m[1]);
    }

    private function fillRoom(Room $room): void
    {
        static $n = 0;
        $n++;
        $tenant = User::create(['name' => "T$n", 'age' => 20, 'email' => "t$n@gmail.com", 'password' => Hash::make('x'), 'role' => 'tenant']);
        Tenancy::create(['room_id' => $room->room_id, 'user_id' => $tenant->user_id, 'start_date' => '2026-01-01', 'end_date' => '2027-01-01', 'status' => 'Active']);
    }

    // ---------- Location ----------

    public function test_area_is_read_from_the_address(): void
    {
        $this->assertSame('Lourdes', RoomFilters::areaOf('12 Lower Magsaysay Ave, Brgy. Lourdes'));
        $this->assertSame('Aurora Hill', RoomFilters::areaOf('7 Aurora Hill Rd, Barangay Aurora Hill, Baguio City'));
        $this->assertSame('Engineers Hill', RoomFilters::areaOf('21 Kisad Rd, Engineers Hill, Baguio City'));
        $this->assertNull(RoomFilters::areaOf('Bonifacio St'));
        $this->assertNull(RoomFilters::areaOf(null));
    }

    public function test_location_select_lists_only_areas_of_listed_rooms(): void
    {
        $this->room('A', ['address' => '1 Road, Brgy. Lourdes']);
        $this->room('B', ['address' => '2 Road, Brgy. lourdes']);   // same place, other capitals
        $this->room('C', ['address' => '3 Road, Brgy. Bakakeng Central']);
        $this->room('D', ['address' => 'Bonifacio St']);              // no area: not offered
        $full = $this->room('E', ['address' => '4 Road, Brgy. Aurora Hill', 'max_occupants' => 1]);
        $this->fillRoom($full);                                       // full rooms are hidden, so is their area

        $this->get('/')->assertOk()
            ->assertSee('<option value="">Any location</option>', false)
            ->assertSeeInOrder(['Bakakeng Central (1)', 'Lourdes (2)'])
            ->assertDontSee('Aurora Hill');
    }

    public function test_location_filter_on_home_and_rooms_page(): void
    {
        $this->room('Lourdes Room', ['address' => '1 Road, Brgy. Lourdes']);
        $this->room('Bakakeng Room', ['address' => '3 Road, Brgy. Bakakeng Central']);

        foreach (['/', '/rooms'] as $page) {
            $this->assertSame(['Lourdes Room'], $this->cards("$page?area=lourdes"));
            $this->get("$page?area=Lourdes")->assertSee('<option value="Lourdes" selected>', false);
        }
    }

    // ---------- Budget ----------

    public function test_price_filter_uses_min_and_max(): void
    {
        $this->room('Cheap', ['price' => 2000]);
        $this->room('Mid', ['price' => 3500]);
        $this->room('Dear', ['price' => 6000]);

        $this->assertEqualsCanonicalizing(['Mid', 'Dear'], $this->cards('/rooms?min=3000'));
        $this->assertEqualsCanonicalizing(['Cheap', 'Mid'], $this->cards('/rooms?max=3500'));   // the limits count
        $this->assertSame(['Mid'], $this->cards('/rooms?min=3000&max=4000'));
        $this->assertSame(['Mid'], $this->cards('/rooms?min=₱3,000&max=4,000'));                 // typed with ₱ and commas
    }

    public function test_price_filter_swaps_min_and_max_typed_the_wrong_way_round(): void
    {
        $this->room('Cheap', ['price' => 2000]);
        $this->room('Mid', ['price' => 3500]);

        $this->assertSame(['Mid'], $this->cards('/rooms?min=4000&max=3000'));
        $this->get('/rooms?min=4000&max=3000')->assertSee('value="3000"', false)->assertSee('value="4000"', false);
    }

    public function test_whole_room_price_is_filtered_by_what_one_tenant_pays(): void
    {
        // ₱6,000 for a room of 3 = ₱2,000 each (Room::perPersonRent()).
        $this->room('Split Room', ['price' => 6000, 'price_type' => 'whole_room', 'max_occupants' => 3]);
        $this->room('Per Person', ['price' => 6000, 'price_type' => 'per_person', 'max_occupants' => 3]);

        $this->assertSame(['Split Room'], $this->cards('/?max=2500'));
        $this->assertSame(['Per Person'], $this->cards('/rooms?min=5000'));
        $this->assertSame(['Split Room'], $this->cards('/rooms?min=2000&max=2000'));
    }

    // ---------- Room type (capacity) ----------

    public function test_room_type_filter_uses_capacity(): void
    {
        $this->room('Solo', ['max_occupants' => 1]);
        $this->room('Twin', ['max_occupants' => 2]);
        $this->room('Four', ['max_occupants' => 4]);
        $this->room('Bunk', ['max_occupants' => 6]);

        $this->assertSame(['Solo'], $this->cards('/rooms?type=solo'));
        $this->assertSame(['Twin'], $this->cards('/rooms?type=two'));
        $this->assertSame(['Four'], $this->cards('/rooms?type=small'));
        $this->assertSame(['Bunk'], $this->cards('/?type=big'));
    }

    public function test_filters_combine_and_keyword_still_works(): void
    {
        $this->room('Loft A', ['address' => '1 Rd, Brgy. Lourdes', 'price' => 3000, 'max_occupants' => 2]);
        $this->room('Loft B', ['address' => '1 Rd, Brgy. Lourdes', 'price' => 3000, 'max_occupants' => 1]);
        $this->room('Loft C', ['address' => '1 Rd, Brgy. Bakakeng', 'price' => 3000, 'max_occupants' => 2]);
        $this->room('Studio', ['address' => '1 Rd, Brgy. Lourdes', 'price' => 3000, 'max_occupants' => 2]);

        $this->assertSame(['Loft A'], $this->cards('/rooms?name=loft&area=Lourdes&type=two&max=3000'));
        $this->get('/rooms?name=loft&area=Lourdes&type=two&max=3000')
            ->assertSee('1 room')
            ->assertSee('match your search')
            ->assertSee('Clear filters');
    }

    public function test_full_rooms_stay_hidden_with_filters(): void
    {
        $full = $this->room('Full Solo', ['max_occupants' => 1]);
        $this->fillRoom($full);
        $this->room('Open Solo', ['max_occupants' => 1]);

        $this->assertSame(['Open Solo'], $this->cards('/rooms?type=solo'));
        $this->assertSame(['Open Solo'], $this->cards('/?type=solo'));
    }

    // ---------- Bad query strings ----------

    public function test_junk_in_the_address_bar_is_ignored_not_an_error(): void
    {
        $this->room('Room 1', ['address' => '1 Rd, Brgy. Lourdes']);
        $this->room('Room 2');

        $junk = [
            '?min=abc&max=xyz', '?min=-500&max=0', '?min[]=1&max[x]=2', '?area[]=Lourdes', '?area=Nowhere',
            '?type=penthouse', '?type[]=solo', '?name[]=x', '?min=1e999', '?min=-1e999', '?max=1e400', '?max=99999999999999999999',
            '?area=%00%27%22', '?min=' . str_repeat('9', 400),
        ];
        foreach ($junk as $q) {
            $this->assertEqualsCanonicalizing(['Room 1', 'Room 2'], $this->cards('/rooms' . $q), "/rooms$q");
            $this->get('/' . $q)->assertOk();
        }
        $this->getJson('/?search[]=x')->assertOk();   // the old live search
    }

    public function test_empty_results_explain_what_to_do(): void
    {
        $this->room('Room 1', ['price' => 3000]);

        $this->get('/rooms?max=1000')->assertOk()
            ->assertSee('No rooms found.')
            ->assertSee('Try a higher budget')
            ->assertSee('Clear filters and see all rooms');
        $this->get('/?max=1000')->assertSee('No rooms found.')->assertDontSee('No rooms listed yet.');
    }

    public function test_active_filters_show_as_removable_chips(): void
    {
        $this->room('Room 1', ['address' => '1 Rd, Brgy. Lourdes']);

        $html = $this->get('/rooms?area=Lourdes&min=1000&type=solo')->getContent();
        $this->assertStringContainsString('Remove filter Lourdes', $html);
        $this->assertStringContainsString('From ₱1,000', $html);
        // removing "Lourdes" keeps the other filters
        $this->assertStringContainsString('href="' . route('rooms.index') . '?min=1000&amp;type=solo"', $html);
    }

    public function test_home_see_all_link_keeps_the_filters(): void
    {
        foreach (range(1, 10) as $i) {
            $this->room("Room $i", ['max_occupants' => 1]);
        }

        $this->get('/?type=solo')->assertSee('See all 10 rooms')
            ->assertSee('href="' . route('rooms.index', ['type' => 'solo']) . '"', false);
    }

    // ---------- No occupant counts on public pages ----------

    public function test_public_pages_do_not_show_occupant_counts(): void
    {
        $room = $this->room('Shared Room', ['max_occupants' => 3, 'address' => '1 Rd, Brgy. Lourdes', 'latitude' => 16.41, 'longitude' => 120.6]);
        $this->fillRoom($room);

        foreach (['/', '/rooms', '/rooms/' . $room->room_id] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('Shared Room')
                ->assertSee('Shared room')              // the room type, without numbers
                ->assertDontSee('Occupants')
                ->assertDontSee('1/3')
                ->assertDontSee('1 / 3')
                ->assertDontSee('Max occupants')
                ->assertDontSee('Slots available')
                ->assertDontSee('slots_left')
                ->assertDontSee('occ-bar', false);
        }
    }

    public function test_landlord_form_still_has_max_occupants(): void
    {
        $landlord = User::create(['name' => 'L', 'age' => 30, 'email' => 'l@gmail.com', 'password' => Hash::make('x'), 'role' => 'landlord']);
        $landlord->markEmailAsVerified();

        $this->actingAs($landlord)->get(route('landlord.dashboard'))->assertOk()->assertSee('name="max_occupants"', false);
    }

    // ---------- Map data ----------

    public function test_map_data_has_only_rooms_with_a_pin_and_follows_the_filters(): void
    {
        $this->room('Pinned Solo', ['max_occupants' => 1, 'latitude' => 16.41, 'longitude' => 120.59, 'address' => '1 Rd, Brgy. Lourdes']);
        $this->room('Pinned Twin', ['max_occupants' => 2, 'latitude' => 16.40, 'longitude' => 120.60]);
        $this->room('No Pin', ['max_occupants' => 1]);

        $all = $this->get('/rooms')->viewData('mapRooms');
        $this->assertEqualsCanonicalizing(['Pinned Solo', 'Pinned Twin'], $all->pluck('name')->all());
        $this->assertSame(['id', 'name', 'price', 'price_label', 'rent', 'area', 'image', 'lat', 'lng'], array_keys($all->first()));

        $solo = $this->get('/?type=solo')->viewData('mapRooms');
        $this->assertSame(['Pinned Solo'], $solo->pluck('name')->all());
        $this->assertSame('Lourdes', $solo->first()['area']);
    }

    public function test_home_search_card_shows_the_result_count_when_filtering(): void
    {
        $this->room('Solo A', ['max_occupants' => 1, 'latitude' => 16.41, 'longitude' => 120.59]);
        $this->room('Twin', ['max_occupants' => 2, 'latitude' => 16.40, 'longitude' => 120.60]);

        $this->get('/')->assertOk()->assertSee('data-map-cover', false)->assertDontSee('class="search-result"', false);
        $this->get('/?type=solo')->assertSee('class="search-result"', false)
            ->assertSee('<strong>1 room</strong> matches', false)
            ->assertSee('href="#rooms"', false);
    }

    public function test_map_shows_a_note_when_no_matching_room_has_a_pin(): void
    {
        $this->room('Pinned', ['max_occupants' => 2, 'latitude' => 16.41, 'longitude' => 120.59]);
        $this->room('No Pin Solo', ['max_occupants' => 1]);

        $this->get('/rooms?type=solo')->assertSee('id="rooms-map"', false)->assertSee('None of these rooms has a map pin yet.');
    }
}
