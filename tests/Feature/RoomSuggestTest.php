<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Live search: suggestions under the keyword box while typing (GET /rooms/suggest?q=...).
 */
class RoomSuggestTest extends TestCase
{
    use RefreshDatabase;

    private function room(string $name, string $address, array $extra = []): Room
    {
        return Room::create(array_merge([
            'room_name' => $name, 'price' => 3000, 'description' => 'Nice', 'status' => 'Available',
            'max_occupants' => 2, 'address' => $address,
        ], $extra));
    }

    private function labels(string $q): array
    {
        return array_column($this->getJson('/rooms/suggest?q=' . urlencode($q))->assertOk()->json(), 'label');
    }

    public function test_rooms_areas_and_streets_that_contain_the_text_starting_ones_first(): void
    {
        $this->room('Pine Loft 2', '5 Upper General Luna Rd, Brgy. Aurora Hill');
        $this->room('Lourdes Bed Space', '5 Lourdes Ext, Brgy. Lourdes');
        $this->room('Session Solo', '12 Lower Magsaysay Ave, Brgy. Lourdes');

        $all = $this->getJson('/rooms/suggest?q=lo')->assertOk()->json();
        $labels = array_column($all, 'label');

        $this->assertContains('Lourdes Bed Space', $labels);
        $this->assertContains('Lourdes', $labels);              // the area
        $this->assertContains('Lower Magsaysay Ave', $labels);  // the street, without the house number
        $this->assertContains('Pine Loft 2', $labels);          // a word starts with "lo"
        // Starting with the text comes before a word inside the name.
        $this->assertLessThan(array_search('Pine Loft 2', $labels), array_search('Lourdes', $labels));

        $room = collect($all)->firstWhere('label', 'Lourdes Bed Space');
        $this->assertSame('room', $room['type']);
        $this->assertSame(route('rooms.show', Room::where('room_name', 'Lourdes Bed Space')->value('room_id')), $room['url']);
        $this->assertSame('area', collect($all)->firstWhere('label', 'Lourdes')['type']);
    }

    public function test_capitals_do_not_matter_and_no_duplicates(): void
    {
        $this->room('Twin A', '1 Lopez Jaena St, Brgy. Lourdes');
        $this->room('Twin B', '2 Lopez Jaena St, Brgy. Lourdes');

        $labels = $this->labels('LOPEZ');
        $this->assertSame(['Lopez Jaena St'], $labels);
    }

    public function test_full_rooms_are_not_suggested(): void
    {
        $full = $this->room('Full House', '1 Session Rd, Brgy. Session Road Area', ['max_occupants' => 1]);
        $tenant = User::create(['name' => 'T', 'age' => 20, 'email' => 't@gmail.com', 'password' => Hash::make('x'), 'role' => 'tenant']);
        Tenancy::create(['room_id' => $full->room_id, 'user_id' => $tenant->user_id, 'start_date' => '2026-01-01', 'end_date' => '2027-01-01', 'status' => 'Active']);
        $this->room('Open House', '2 Kisad Rd, Brgy. Kisad');

        $labels = $this->labels('house');
        $this->assertContains('Open House', $labels);
        $this->assertNotContains('Full House', $labels);
    }

    public function test_at_most_eight_and_empty_or_junk_input_is_safe(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->room("Room $i", "$i Bokawkan Rd, Brgy. Bakakeng $i");
        }

        $this->assertCount(8, $this->labels('room'));
        $this->assertSame([], $this->labels(''));
        $this->assertSame([], $this->labels('   '));
        $this->assertSame([], $this->labels('zzzz'));
        $this->getJson('/rooms/suggest?q[]=x')->assertOk()->assertExactJson([]);
        $this->getJson('/rooms/suggest?q=' . urlencode('%_\'"<b>'))->assertOk();
        $this->getJson('/rooms/suggest?q=' . str_repeat('a', 500))->assertOk();
    }

    public function test_keyword_box_turns_off_the_browser_list_and_points_to_the_suggestions(): void
    {
        foreach (['/', '/rooms'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('autocomplete="off"', false)
                ->assertSee('data-suggest="' . route('rooms.suggest') . '"', false);
        }
    }

    public function test_room_ids_still_open_the_room_page(): void
    {
        $room = $this->room('Real Room', '3 Kisad Rd, Brgy. Kisad');
        $this->get('/rooms/' . $room->room_id)->assertOk()->assertSee('Real Room');
    }
}
