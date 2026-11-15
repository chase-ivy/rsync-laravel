<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The tenant accepts ("I agree") their contract; a changed contract must be accepted again.
 */
class ContractAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function user(string $email, string $role, bool $verified = true): User
    {
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'age' => 30, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        if ($verified) {
            $u->markEmailAsVerified();
        }

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

    private function editForm(array $overrides = []): array
    {
        return array_merge(['monthly_rent' => '4000', 'end_date' => '2026-12-01', 'repairs_policy' => 'landlord', 'deposit' => '', 'advance_months' => '',
            'utilities_note' => '', 'terms' => 'Old term.', 'house_rules' => 'Old rule.'], $overrides);
    }

    public function test_tenant_sees_the_accept_box_and_can_accept(): void
    {
        Carbon::setTestNow('2026-11-15 10:30:00');   // Asia/Manila (app timezone)
        [$c, , $tenant] = $this->contract();

        $this->actingAs($tenant)->get(route('contracts.show', $c->tenant_id))->assertOk()
            ->assertSee('I have read and agree to this contract')
            ->assertSee('Accept contract')
            ->assertSee(route('contracts.accept', $c->tenant_id));

        $this->post(route('contracts.accept', $c->tenant_id), ['agree' => '1'])
            ->assertRedirect(route('contracts.show', $c->tenant_id))
            ->assertSessionHas('msg', 'Thank you. You accepted this contract.');

        $c->refresh();
        $this->assertTrue($c->isAccepted());
        $this->assertSame('2026-11-15 10:30:00', $c->accepted_at->format('Y-m-d H:i:s'));
        $this->assertSame('127.0.0.1', $c->accepted_ip);

        $this->get(route('contracts.show', $c->tenant_id))
            ->assertSee('Accepted by <strong>T</strong> on <strong>November 15, 2026 at 10:30 AM</strong>', false)
            ->assertDontSee('Accept contract');
        Carbon::setTestNow();
    }

    public function test_checkbox_is_required(): void
    {
        [$c, , $tenant] = $this->contract();

        $this->actingAs($tenant)->from(route('contracts.show', $c->tenant_id))->post(route('contracts.accept', $c->tenant_id), [])
            ->assertRedirect(route('contracts.show', $c->tenant_id))
            ->assertSessionHasErrors('agree');
        $this->assertNull($c->fresh()->accepted_at);
    }

    public function test_other_tenant_landlord_admin_and_guests_cannot_accept(): void
    {
        [$c, $landlord] = $this->contract();
        $other = $this->user('o@gmail.com', 'tenant');
        $admin = $this->user('a@gmail.com', 'admin');

        $this->actingAs($other)->post(route('contracts.accept', $c->tenant_id), ['agree' => '1'])->assertForbidden();
        $this->actingAs($landlord)->post(route('contracts.accept', $c->tenant_id), ['agree' => '1'])->assertRedirect(route('login'));
        $this->actingAs($admin)->post(route('contracts.accept', $c->tenant_id), ['agree' => '1'])->assertRedirect(route('login'));
        auth()->logout();
        $this->post(route('contracts.accept', $c->tenant_id), ['agree' => '1'])->assertRedirect(route('login'));

        $this->assertNull($c->fresh()->accepted_at);
    }

    public function test_unverified_tenant_must_verify_first(): void
    {
        [$c, , $tenant] = $this->contract();
        $tenant->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($tenant)->post(route('contracts.accept', $c->tenant_id), ['agree' => '1'])->assertRedirect(route('verification.notice'));
        $this->assertNull($c->fresh()->accepted_at);
    }

    public function test_ended_contract_cannot_be_accepted(): void
    {
        [$c, , $tenant] = $this->contract('Left');

        $this->actingAs($tenant)->get(route('contracts.show', $c->tenant_id))->assertOk()->assertDontSee('Accept contract');
        $this->post(route('contracts.accept', $c->tenant_id), ['agree' => '1'])
            ->assertSessionHas('msg', 'err:Only current (Active) contracts can be accepted.');
        $this->assertNull($c->fresh()->accepted_at);
    }

    public function test_accepting_twice_keeps_the_first_time(): void
    {
        [$c, , $tenant] = $this->contract();
        $c->update(['accepted_at' => '2026-11-01 08:00:00', 'accepted_ip' => '10.0.0.1']);

        $this->actingAs($tenant)->post(route('contracts.accept', $c->tenant_id), ['agree' => '1'])->assertSessionHas('msg', 'You already accepted this contract.');
        $this->assertSame('10.0.0.1', $c->fresh()->accepted_ip);
    }

    public function test_landlord_and_admin_see_the_status_but_no_button(): void
    {
        [$c, $landlord] = $this->contract();
        $admin = $this->user('a@gmail.com', 'admin');

        $this->actingAs($landlord)->get(route('contracts.show', $c->tenant_id))->assertSee('Waiting for the tenant to accept')->assertDontSee('Accept contract');
        $this->actingAs($admin)->get(route('contracts.show', $c->tenant_id))->assertSee('Waiting for the tenant to accept')->assertDontSee('Accept contract');

        $c->update(['accepted_at' => '2026-11-01 08:00:00']);
        $this->actingAs($landlord)->get(route('contracts.show', $c->tenant_id))->assertSee('Accepted by <strong>T</strong> on <strong>November 1, 2026 at 8:00 AM</strong>', false);
        $this->actingAs($admin)->get(route('contracts.show', $c->tenant_id))->assertSee('November 1, 2026 at 8:00 AM');
    }

    public function test_preview_has_no_acceptance_box(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');

        $this->actingAs($landlord)->get('/landlord/contract/preview')->assertOk()
            ->assertDontSee("Tenant's acceptance", false)->assertDontSee('Accept contract');
    }

    public function test_changing_the_contract_requires_a_new_acceptance(): void
    {
        [$c, $landlord] = $this->contract();
        $c->update(['accepted_at' => '2026-11-01 08:00:00', 'accepted_ip' => '10.0.0.1']);

        $this->actingAs($landlord)->post(route('landlord.contracts.update', $c->tenant_id), $this->editForm(['monthly_rent' => '4100']))
            ->assertRedirect('/landlord/tenants')
            ->assertSessionHas('msg', fn ($m) => str_contains($m, 'has to accept the contract again'));

        $c->refresh();
        $this->assertNull($c->accepted_at);
        $this->assertNull($c->accepted_ip);
    }

    /** Each part of the agreement counts as a change. */
    public function test_every_agreement_field_counts(): void
    {
        [$c, $landlord] = $this->contract();

        foreach ([['end_date' => '2027-01-01'], ['repairs_policy' => 'tenant'], ['deposit' => '500'], ['advance_months' => '1'],
            ['utilities_note' => 'Water included.'], ['terms' => "Old term.\nNew term."], ['house_rules' => 'Other rule.']] as $change) {
            $c->refresh()->update(['accepted_at' => now()]);
            $this->actingAs($landlord)->post(route('landlord.contracts.update', $c->tenant_id), $this->editForm($change));
            $this->assertNull($c->fresh()->accepted_at, 'Not cleared for ' . key($change));
            // put it back for the next round
            $this->post(route('landlord.contracts.update', $c->tenant_id), $this->editForm());
        }
    }

    public function test_saving_without_changes_keeps_the_acceptance(): void
    {
        [$c, $landlord] = $this->contract();
        $c->update(['accepted_at' => '2026-11-01 08:00:00', 'accepted_ip' => '10.0.0.1']);

        // Same values (blank lines and spaces are cleaned the same way); also saved as default — not an agreement change.
        $this->actingAs($landlord)->post(route('landlord.contracts.update', $c->tenant_id), $this->editForm(['terms' => "Old term.\n\n", 'save_as_default' => '1']))
            ->assertSessionHas('msg', fn ($m) => str_contains($m, 'Nothing in the agreement changed'));

        $this->assertSame('10.0.0.1', $c->fresh()->accepted_ip);
        Notification::assertNothingSent();
    }

    public function test_my_tenants_and_admin_pages_show_badges(): void
    {
        [$c, $landlord, $tenant] = $this->contract();
        $other = $this->user('o@gmail.com', 'tenant');
        Tenancy::create(['user_id' => $other->user_id, 'room_id' => $c->room_id, 'start_date' => '2026-06-01', 'end_date' => '2026-12-01', 'status' => 'Active', 'accepted_at' => '2026-11-02 09:00:00']);
        $admin = $this->user('a@gmail.com', 'admin');

        $page = $this->actingAs($landlord)->get('/landlord/tenants')->assertOk()->getContent();
        $this->assertSame(1, substr_count($page, '>Accepted</span>'));
        $this->assertSame(1, substr_count($page, '>Waiting</span>'));

        $page = $this->actingAs($admin)->get('/admin/tenants')->assertOk()->assertSee('<th>Accepted</th>', false)->getContent();
        $this->assertSame(1, substr_count($page, '>Accepted</span>'));
        $this->assertSame(1, substr_count($page, '>Waiting</span>'));
    }

    public function test_tenant_dashboard_reminds_to_accept(): void
    {
        [$c, , $tenant] = $this->contract();

        $this->actingAs($tenant)->get('/tenant/dashboard')->assertSee('Please review and accept your contract');

        $c->update(['accepted_at' => now()]);
        $this->actingAs($tenant)->get('/tenant/dashboard')->assertDontSee('Please review and accept your contract')->assertSee('✓ Accepted');
    }
}
