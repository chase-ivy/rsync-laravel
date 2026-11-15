<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature 4: printable rental agreement per contract, for the tenant, the landlord and admins.
 */
class ContractPageTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role, string $name, array $extra = []): User
    {
        $u = User::create(['name' => $name, 'age' => 30, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        $u->forceFill($extra)->save();
        $u->markEmailAsVerified();

        return $u;
    }

    /** @return array{0: Tenancy, 1: User, 2: User} contract, tenant, landlord */
    private function contract(): array
    {
        $landlord = $this->user('l@gmail.com', 'landlord', 'Lando Landlord', ['contact_number' => '09171112222']);
        $tenant = $this->user('t@gmail.com', 'tenant', 'Tina Tenant', [
            'contact_number' => '09173334444', 'emergency_contact_name' => 'Maria Tenant',
            'emergency_contact_relationship' => 'Mother', 'emergency_contact_number' => '09175556666',
        ]);
        $room = Room::create([
            'owner_id' => $landlord->user_id, 'room_name' => 'Room 201', 'price' => 4500, 'status' => 'Available',
            'max_occupants' => 2, 'address' => '12 Lower Magsaysay Ave, Brgy. Lourdes', 'amenities' => ['wifi'],
        ]);
        $contract = Tenancy::create([
            'user_id' => $tenant->user_id, 'room_id' => $room->room_id,
            'start_date' => '2026-06-01', 'end_date' => '2026-12-01', 'status' => 'Active',
        ]);

        return [$contract, $tenant, $landlord];
    }

    public function test_tenant_sees_their_contract_with_all_details(): void
    {
        [$contract, $tenant] = $this->contract();

        $this->actingAs($tenant)->get(route('contracts.show', $contract->tenant_id))->assertOk()
            ->assertSee('Boarding House Rental Agreement')
            ->assertSee('C' . str_pad((string) $contract->tenant_id, 3, '0', STR_PAD_LEFT))
            ->assertSee('Lando Landlord')
            ->assertSee('09171112222')
            ->assertSee('Tina Tenant')
            ->assertSee('09173334444')
            ->assertSee('Maria Tenant')
            ->assertSee('Mother')
            ->assertSee('09175556666')
            ->assertSee('Room 201')
            ->assertSee('12 Lower Magsaysay Ave, Brgy. Lourdes')
            ->assertSee('₱4,500.00')
            ->assertSee('June 1, 2026')
            ->assertSee('December 1, 2026')
            ->assertSee('2 persons')
            ->assertSee('House rules')
            ->assertSee('Signature over printed name')
            ->assertSee('onclick="window.print()"', false)
            ->assertSee('Print / Save as PDF');
    }

    public function test_landlord_of_the_room_and_admins_can_open_it(): void
    {
        [$contract, , $landlord] = $this->contract();
        $url = route('contracts.show', $contract->tenant_id);

        $this->actingAs($landlord)->get($url)->assertOk();
        $this->actingAs($this->user('a@gmail.com', 'admin', 'Admin'))->get($url)->assertOk();
    }

    public function test_everyone_else_is_refused(): void
    {
        [$contract] = $this->contract();
        $url = route('contracts.show', $contract->tenant_id);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->user('t2@gmail.com', 'tenant', 'Other Tenant'))->get($url)->assertForbidden();
        $this->actingAs($this->user('l2@gmail.com', 'landlord', 'Other Landlord'))->get($url)->assertForbidden();
        $this->actingAs($this->user('s@gmail.com', 'sublessor', 'Some Sublessor'))->get($url)->assertForbidden();
        $this->get('/contracts/99999')->assertNotFound();
    }

    public function test_links_on_the_dashboards(): void
    {
        [$contract, $tenant, $landlord] = $this->contract();
        $url = route('contracts.show', $contract->tenant_id);

        $this->actingAs($tenant)->get('/tenant/dashboard')->assertSee($url)->assertSee('View contract');
        $this->actingAs($landlord)->get('/landlord/tenants')->assertSee($url)->assertSee('Contract');
        $this->actingAs($this->user('a@gmail.com', 'admin', 'Admin'))->get('/admin/tenants')->assertSee($url);
    }

    public function test_missing_details_show_blank_lines_instead_of_errors(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord', 'Lando');
        $tenant = $this->user('t@gmail.com', 'tenant', 'Tina');
        $room = Room::create(['owner_id' => $landlord->user_id, 'room_name' => 'Old Room', 'price' => 3000, 'max_occupants' => 1]);
        $contract = Tenancy::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-06-01', 'end_date' => '2026-07-01', 'status' => 'Active']);

        $this->actingAs($tenant)->get(route('contracts.show', $contract->tenant_id))->assertOk()
            ->assertSee('________________')
            ->assertSee('1 person');
    }

    public function test_names_are_escaped(): void
    {
        [$contract, $tenant] = $this->contract();
        $tenant->forceFill(['emergency_contact_name' => '<script>x</script>'])->save();

        $this->actingAs($tenant)->get(route('contracts.show', $contract->tenant_id))
            ->assertDontSee('<script>x</script>', false)
            ->assertSee('&lt;script&gt;x&lt;/script&gt;', false);
    }
}
