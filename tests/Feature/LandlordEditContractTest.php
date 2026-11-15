<?php

namespace Tests\Feature;

use App\Models\ContractTemplate;
use App\Models\Room;
use App\Models\Tenancy;
use App\Models\User;
use App\Support\ContractDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * "Edit contract" on My Tenants: the room's landlord changes one current contract.
 */
class LandlordEditContractTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role): User
    {
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'age' => 30, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        $u->markEmailAsVerified();

        return $u;
    }

    /** @return array{0: Tenancy, 1: User, 2: User} contract, landlord, tenant */
    private function contract(string $status = 'Active'): array
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $tenant = $this->user('t@gmail.com', 'tenant');
        $room = Room::create(['owner_id' => $landlord->user_id, 'room_name' => 'Room 7', 'price' => 4000, 'max_occupants' => 2, 'status' => 'Available']);
        $c = Tenancy::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-06-01', 'end_date' => '2026-12-01', 'status' => $status,
            'monthly_rent' => 4000, 'repairs_policy' => 'landlord', 'terms' => ['Old term.'], 'house_rules' => ['Old rule.']]);

        return [$c, $landlord, $tenant];
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'monthly_rent' => '4250.50',
            'end_date' => '2027-03-01',
            'repairs_policy' => 'shared',
            'deposit' => '4000',
            'advance_months' => '1',
            'utilities_note' => 'Electricity is sub-metered.',
            'terms' => "New term one.\n\nNew term two.",
            'house_rules' => 'New rule.',
        ], $overrides);
    }

    public function test_my_tenants_page_links_to_edit(): void
    {
        [$c, $landlord] = $this->contract();

        $this->actingAs($landlord)->get('/landlord/tenants')->assertOk()
            ->assertSee(route('landlord.contracts.edit', $c->tenant_id))
            ->assertSee('Edit contract');
    }

    public function test_edit_form_is_prefilled_with_this_contract(): void
    {
        [$c, $landlord] = $this->contract();

        $this->actingAs($landlord)->get(route('landlord.contracts.edit', $c->tenant_id))->assertOk()
            ->assertSee('Edit Contract C' . str_pad((string) $c->tenant_id, 3, '0', STR_PAD_LEFT))
            ->assertSee('Old term.')
            ->assertSee('Old rule.')
            ->assertSee('value="4000"', false)
            ->assertSee('value="2026-12-01"', false)
            ->assertSee('Also save these terms as my default contract format');

        $this->actingAs($landlord)->get(route('landlord.contracts.edit', ['tenancy' => $c->tenant_id, 'standard' => 1]))->assertOk()
            ->assertSee(ContractDefaults::TERMS[0])
            ->assertDontSee('Old term.');
    }

    public function test_happy_path_saves_and_tenant_sees_the_change(): void
    {
        [$c, $landlord, $tenant] = $this->contract();
        $this->travelTo('2026-11-20 10:00:00');

        $this->actingAs($landlord)->post(route('landlord.contracts.update', $c->tenant_id), $this->form())
            ->assertRedirect(route('landlord.tenants'))->assertSessionHas('msg');

        $c->refresh();
        $this->assertSame(4250.5, $c->monthly_rent);
        $this->assertSame('2027-03-01', $c->end_date->format('Y-m-d'));
        $this->assertSame('shared', $c->repairs_policy);
        $this->assertSame(4000.0, $c->deposit);
        $this->assertSame(['New term one.', 'New term two.'], $c->terms);
        $this->assertSame(['New rule.'], $c->house_rules);
        $this->assertSame('2026-11-20', $c->terms_updated_at->format('Y-m-d'));
        $this->assertSame(0, ContractTemplate::count());   // box not ticked: template untouched

        $this->actingAs($landlord)->get('/landlord/tenants')->assertSee('was updated');

        $this->actingAs($tenant)->get(route('contracts.show', $c->tenant_id))->assertOk()
            ->assertSee('Last updated on')
            ->assertSee('November 20, 2026')
            ->assertSee('₱4,250.50')
            ->assertSee('March 1, 2027')
            ->assertSee(ContractDefaults::REPAIRS_SENTENCES['shared'])
            ->assertSee('New term two.')
            ->assertSee('Electricity is sub-metered.')
            ->assertDontSee('Old term.');
    }

    public function test_also_save_as_my_default_template(): void
    {
        [$c, $landlord] = $this->contract();

        $this->actingAs($landlord)->post(route('landlord.contracts.update', $c->tenant_id), $this->form(['save_as_default' => '1']))
            ->assertRedirect(route('landlord.tenants'));

        $t = ContractTemplate::where('user_id', $landlord->user_id)->first();
        $this->assertSame('shared', $t->repairs_policy);
        $this->assertSame(['New term one.', 'New term two.'], $t->terms);
    }

    public function test_validation(): void
    {
        [$c, $landlord] = $this->contract();
        $url = route('landlord.contracts.update', $c->tenant_id);
        $from = route('landlord.contracts.edit', $c->tenant_id);
        $post = fn (array $o) => $this->actingAs($landlord)->from($from)->post($url, $this->form($o));

        $post(['end_date' => '2026-06-01'])->assertRedirect($from)
            ->assertSessionHasErrors(['end_date' => 'The end date must be after the start date (Jun 1, 2026).']);
        $post(['end_date' => '2026-05-01'])->assertSessionHasErrors('end_date');
        $post(['end_date' => 'soon'])->assertSessionHasErrors(['end_date' => 'Please enter a valid end date.']);
        $post(['monthly_rent' => '-1'])->assertSessionHasErrors(['monthly_rent' => 'The monthly rent cannot be negative.']);
        $post(['monthly_rent' => ''])->assertSessionHasErrors(['monthly_rent' => 'Please enter the monthly rent.']);
        $post(['repairs_policy' => 'nobody'])->assertSessionHasErrors('repairs_policy');
        $post(['advance_months' => '13'])->assertSessionHasErrors('advance_months');
        $post(['deposit' => '-100'])->assertSessionHasErrors('deposit');
        $post(['terms' => implode("\n", array_fill(0, 31, 'x'))])->assertSessionHasErrors('terms');

        $c->refresh();
        $this->assertSame(['Old term.'], $c->terms);
        $this->assertNull($c->terms_updated_at);
    }

    public function test_other_landlords_get_403_and_non_owners_are_sent_to_login(): void
    {
        [$c, , $tenant] = $this->contract();
        $other = $this->user('other@gmail.com', 'landlord');
        $sub = $this->user('s@gmail.com', 'sublessor');

        foreach ([$other, $sub] as $u) {
            $this->actingAs($u)->get(route('landlord.contracts.edit', $c->tenant_id))->assertForbidden();
            $this->actingAs($u)->post(route('landlord.contracts.update', $c->tenant_id), $this->form())->assertForbidden();
        }
        $this->actingAs($tenant)->post(route('landlord.contracts.update', $c->tenant_id), $this->form())->assertRedirect(route('login'));
        $this->actingAs($this->user('a@gmail.com', 'admin'))->get(route('landlord.contracts.edit', $c->tenant_id))->assertRedirect(route('login'));
        $this->actingAs($other)->get('/landlord/contracts/99999/edit')->assertNotFound();

        $this->assertSame(['Old term.'], $c->fresh()->terms);
    }

    public function test_contracts_that_are_not_active_cannot_be_edited(): void
    {
        [$c, $landlord] = $this->contract('Left');

        $this->actingAs($landlord)->get(route('landlord.contracts.edit', $c->tenant_id))
            ->assertRedirect(route('landlord.tenants'))->assertSessionHas('msg', 'err:Only current (Active) contracts can be edited.');
        $this->actingAs($landlord)->post(route('landlord.contracts.update', $c->tenant_id), $this->form())
            ->assertRedirect(route('landlord.tenants'));

        $this->assertSame(['Old term.'], $c->fresh()->terms);
        $this->assertNull($c->fresh()->terms_updated_at);
        $this->actingAs($landlord)->get('/landlord/tenants')->assertSee('Only current (Active) contracts can be edited.');
    }

    public function test_editing_an_old_contract_without_a_snapshot_creates_one(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $tenant = $this->user('t@gmail.com', 'tenant');
        $room = Room::create(['owner_id' => $landlord->user_id, 'room_name' => 'Room 7', 'price' => 3600, 'max_occupants' => 2, 'status' => 'Available']);
        $c = Tenancy::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-06-01', 'end_date' => '2026-12-01', 'status' => 'Active']);

        // The form starts from the standard format and the room's current price.
        $this->actingAs($landlord)->get(route('landlord.contracts.edit', $c->tenant_id))->assertOk()
            ->assertSee(ContractDefaults::TERMS[0])->assertSee('value="3600"', false);

        $this->actingAs($landlord)->post(route('landlord.contracts.update', $c->tenant_id), $this->form(['monthly_rent' => '3600']));
        $c->refresh();
        $this->assertTrue($c->hasSnapshot());
        $room->update(['price' => 5000]);
        $this->actingAs($tenant)->get(route('contracts.show', $c->tenant_id))->assertSee('₱3,600.00')->assertDontSee('₱5,000.00');
    }
}
