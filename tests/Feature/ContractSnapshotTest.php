<?php

namespace Tests\Feature;

use App\Models\ContractTemplate;
use App\Models\Room;
use App\Models\Tenancy;
use App\Models\User;
use App\Support\ContractDefaults;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Each contract keeps its own copy of the rent and terms from the moment the tenant was assigned.
 */
class ContractSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role): User
    {
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'age' => 30, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        $u->markEmailAsVerified();

        return $u;
    }

    private function template(User $owner, array $overrides = []): ContractTemplate
    {
        return ContractTemplate::create(array_merge([
            'user_id' => $owner->user_id,
            'repairs_policy' => 'tenant',
            'deposit' => 2500,
            'advance_months' => 2,
            'utilities_note' => 'Water included.',
            'terms' => ['Rent is due every 5th.'],
            'house_rules' => ['Curfew at 11 PM.'],
        ], $overrides));
    }

    private function assign(User $landlord, User $tenant, Room $room)
    {
        return $this->actingAs($landlord)->post('/landlord/tenants/assign', [
            'room_id' => $room->room_id, 'user_id' => $tenant->user_id, 'start_date' => '2026-06-01', 'end_date' => '2026-12-01',
        ]);
    }

    public function test_assign_saves_a_snapshot_of_rent_and_template(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $tenant = $this->user('t@gmail.com', 'tenant');
        $room = Room::create(['owner_id' => $landlord->user_id, 'room_name' => 'Room 1', 'price' => 4500, 'max_occupants' => 2, 'status' => 'Available']);
        $this->template($landlord);

        $this->assign($landlord, $tenant, $room)->assertExactJson(['status' => 'success']);

        $c = Tenancy::first();
        $this->assertTrue($c->hasSnapshot());
        $this->assertSame(4500.0, $c->monthly_rent);
        $this->assertSame('tenant', $c->repairs_policy);
        $this->assertSame(2500.0, $c->deposit);
        $this->assertSame(2, $c->advance_months);
        $this->assertSame('Water included.', $c->utilities_note);
        $this->assertSame(['Rent is due every 5th.'], $c->terms);
        $this->assertSame(['Curfew at 11 PM.'], $c->house_rules);
        $this->assertNull($c->terms_updated_at);
    }

    public function test_assign_without_a_template_saves_the_standard_format(): void
    {
        $landlord = $this->user('l@gmail.com', 'sublessor');
        $tenant = $this->user('t@gmail.com', 'tenant');
        $room = Room::create(['owner_id' => $landlord->user_id, 'room_name' => 'Room 1', 'price' => 3000, 'max_occupants' => 1, 'status' => 'Available']);

        $this->assign($landlord, $tenant, $room)->assertExactJson(['status' => 'success']);

        $c = Tenancy::first();
        $this->assertSame(ContractDefaults::TERMS, $c->terms);
        $this->assertSame(ContractDefaults::HOUSE_RULES, $c->house_rules);
        $this->assertSame('landlord', $c->repairs_policy);
        $this->assertSame(3000.0, $c->monthly_rent);
    }

    public function test_later_price_and_template_changes_do_not_change_an_existing_contract(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $tenant = $this->user('t@gmail.com', 'tenant');
        $room = Room::create(['owner_id' => $landlord->user_id, 'room_name' => 'Room 1', 'price' => 4500, 'max_occupants' => 2, 'status' => 'Available']);
        $this->template($landlord);
        $this->assign($landlord, $tenant, $room);
        $c = Tenancy::first();

        // The landlord raises the price and rewrites the template afterwards.
        $room->update(['price' => 9999]);
        $this->actingAs($landlord)->post('/landlord/contract', [
            'repairs_policy' => 'landlord', 'deposit' => '8000', 'advance_months' => '3', 'utilities_note' => 'New note.',
            'terms' => 'Brand new term.', 'house_rules' => 'Brand new rule.',
        ])->assertSessionHasNoErrors();

        $this->actingAs($tenant)->get(route('contracts.show', $c->tenant_id))->assertOk()
            ->assertSee('₱4,500.00')
            ->assertDontSee('₱9,999.00')
            ->assertSee('₱2,500.00')
            ->assertSee('2 months of rent in advance')
            ->assertSee('Water included.')
            ->assertSee(ContractDefaults::REPAIRS_SENTENCES['tenant'])
            ->assertSee('Rent is due every 5th.')
            ->assertSee('Curfew at 11 PM.')
            ->assertSee('Terms set by the landlord')
            ->assertDontSee('Last updated on')
            ->assertDontSee('Brand new term.')
            ->assertDontSee('Brand new rule.')
            ->assertDontSee('New note.');

        // A new tenant assigned now gets the new terms and price.
        $tenant2 = $this->user('t2@gmail.com', 'tenant');
        $this->assign($landlord, $tenant2, $room)->assertExactJson(['status' => 'success']);
        $c2 = Tenancy::where('user_id', $tenant2->user_id)->first();
        $this->actingAs($tenant2)->get(route('contracts.show', $c2->tenant_id))
            ->assertSee('₱9,999.00')->assertSee('Brand new term.')->assertSee(ContractDefaults::REPAIRS_SENTENCES['landlord']);
    }

    public function test_old_contracts_fall_back_to_the_current_template_then_the_standard_format(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $tenant = $this->user('t@gmail.com', 'tenant');
        $room = Room::create(['owner_id' => $landlord->user_id, 'room_name' => 'Room 1', 'price' => 4200, 'max_occupants' => 2, 'status' => 'Available']);
        // Made before this feature: no snapshot columns filled.
        $old = Tenancy::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-01-01', 'end_date' => '2026-12-01', 'status' => 'Active']);
        $this->assertFalse($old->hasSnapshot());
        $url = route('contracts.show', $old->tenant_id);

        // No template yet → standard format and the room's current price.
        $this->actingAs($tenant)->get($url)->assertOk()
            ->assertSee('₱4,200.00')
            ->assertSee(ContractDefaults::TERMS[0])
            ->assertSee(ContractDefaults::HOUSE_RULES[0])
            ->assertSee(ContractDefaults::REPAIRS_SENTENCES['landlord'])
            ->assertDontSee('Security deposit');

        // Landlord saves a template → the old contract shows it.
        $this->template($landlord);
        $this->actingAs($tenant)->get($url)->assertOk()
            ->assertSee('Rent is due every 5th.')
            ->assertSee('Security deposit')
            ->assertDontSee(ContractDefaults::TERMS[0]);
    }

    public function test_tenant_landlord_and_admin_can_view_others_cannot(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $tenant = $this->user('t@gmail.com', 'tenant');
        $room = Room::create(['owner_id' => $landlord->user_id, 'room_name' => 'Room 1', 'price' => 4500, 'max_occupants' => 2, 'status' => 'Available']);
        $this->template($landlord);
        $this->assign($landlord, $tenant, $room);
        $url = route('contracts.show', Tenancy::first()->tenant_id);

        $this->actingAs($tenant)->get($url)->assertOk()->assertSee('Rent is due every 5th.')->assertDontSee('Edit contract');
        $this->actingAs($landlord)->get($url)->assertOk();
        $this->actingAs($this->user('a@gmail.com', 'admin'))->get($url)->assertOk()->assertSee('Curfew at 11 PM.');
        $this->actingAs($this->user('x@gmail.com', 'tenant'))->get($url)->assertForbidden();
        $this->actingAs($this->user('y@gmail.com', 'landlord'))->get($url)->assertForbidden();
    }

    public function test_demo_seeder_adds_a_template_and_snapshot_once(): void
    {
        $this->seed(DemoAccountsSeeder::class);

        $landlord = User::where('email', 'landlord@rsync.test')->first();
        $template = ContractTemplate::where('user_id', $landlord->user_id)->first();
        $this->assertNotNull($template);
        $c = Tenancy::first();
        $this->assertTrue($c->hasSnapshot());
        $this->assertSame(3500.0, $c->monthly_rent);
        $this->assertSame($template->house_rules, $c->house_rules);

        // Running again changes nothing (a landlord's edited template stays).
        $template->update(['terms' => ['Edited by me.']]);
        $this->seed(DemoAccountsSeeder::class);
        $this->assertSame(1, ContractTemplate::count());
        $this->assertSame(['Edited by me.'], $template->fresh()->terms);
        $this->assertSame(1, Tenancy::count());
        $this->assertNotSame(['Edited by me.'], $c->fresh()->terms);
    }
}
