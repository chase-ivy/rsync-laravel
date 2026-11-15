<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\Tenancy;
use App\Models\User;
use App\Notifications\ContractNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * Email + dashboard notice to the tenant when a landlord assigns them (new contract) or edits their contract.
 */
class ContractNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role, ?string $name = null): User
    {
        $u = User::create(['name' => $name ?? ucfirst(strtok($email, '@')), 'age' => 30, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        $u->markEmailAsVerified();

        return $u;
    }

    /** @return array{0: User, 1: Room, 2: User} landlord, room, tenant */
    private function setUpRoom(): array
    {
        $landlord = $this->user('l@gmail.com', 'landlord', 'Mang Jose');
        $room = Room::create(['owner_id' => $landlord->user_id, 'room_name' => 'Room 7', 'price' => 4000, 'max_occupants' => 2, 'status' => 'Available']);

        return [$landlord, $room, $this->user('t@gmail.com', 'tenant', 'Ana Cruz')];
    }

    private function assign(User $landlord, Room $room, User $tenant)
    {
        return $this->actingAs($landlord)->post('/landlord/tenants/assign', [
            'room_id' => $room->room_id, 'user_id' => $tenant->user_id, 'start_date' => '2026-12-01', 'end_date' => '2027-06-01',
        ]);
    }

    private function edit(User $landlord, Tenancy $c, array $overrides = [])
    {
        return $this->actingAs($landlord)->post(route('landlord.contracts.update', $c->tenant_id), array_merge([
            'monthly_rent' => '4500', 'end_date' => '2027-06-01', 'repairs_policy' => 'landlord', 'terms' => 'A', 'house_rules' => 'B',
        ], $overrides));
    }

    public function test_assigning_sends_new_contract_email_and_notice(): void
    {
        Notification::fake();
        [$landlord, $room, $tenant] = $this->setUpRoom();

        $this->assign($landlord, $room, $tenant)->assertExactJson(['status' => 'success']);
        $c = Tenancy::first();

        Notification::assertSentTo($tenant, ContractNotice::class, fn (ContractNotice $n, array $channels) => $n->kind === ContractNotice::NEW
            && $n->tenancyId === $c->tenant_id && $n->roomName === 'Room 7' && $n->landlordName === 'Mang Jose' && $channels === ['mail']);
        Notification::assertSentTo($tenant, ContractNotice::class, fn (ContractNotice $n, array $channels) => $channels === ['database']);
        Notification::assertNotSentTo($landlord, ContractNotice::class);
    }

    public function test_failed_assign_sends_nothing(): void
    {
        Notification::fake();
        [$landlord, $room, $tenant] = $this->setUpRoom();

        $this->actingAs($landlord)->post('/landlord/tenants/assign', ['room_id' => $room->room_id, 'user_id' => $tenant->user_id, 'start_date' => '', 'end_date' => ''])
            ->assertJsonPath('status', 'error');
        Notification::assertNothingSent();
    }

    public function test_email_content(): void
    {
        [$landlord, $room, $tenant] = $this->setUpRoom();
        $c = Tenancy::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-12-01', 'end_date' => '2027-06-01', 'status' => 'Active']);

        $new = ContractNotice::for($c, ContractNotice::NEW)->toMail($tenant);
        $this->assertSame('You have a new rental contract', $new->subject);
        $this->assertSame(route('contracts.show', $c->tenant_id), $new->actionUrl);
        $text = implode(' ', $new->introLines);
        $this->assertStringContainsString('Room 7', $text);
        $this->assertStringContainsString('Mang Jose', $text);
        $this->assertStringContainsString(route('contracts.show', $c->tenant_id), (string) $new->render());

        $this->assertSame('Your rental contract was updated', ContractNotice::for($c, ContractNotice::UPDATED)->toMail($tenant)->subject);
    }

    public function test_editing_sends_updated_email_and_notice(): void
    {
        Notification::fake();
        [$landlord, $room, $tenant] = $this->setUpRoom();
        $c = Tenancy::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-12-01', 'end_date' => '2027-06-01', 'status' => 'Active', 'monthly_rent' => 4000]);

        $this->edit($landlord, $c)->assertRedirect('/landlord/tenants');

        Notification::assertSentTo($tenant, ContractNotice::class, fn (ContractNotice $n, array $channels) => $n->kind === ContractNotice::UPDATED && $channels === ['mail']);
        Notification::assertSentTo($tenant, ContractNotice::class, fn (ContractNotice $n, array $channels) => $n->kind === ContractNotice::UPDATED && $channels === ['database']);
    }

    public function test_failed_email_does_not_break_assign_or_edit(): void
    {
        [$landlord, $room, $tenant] = $this->setUpRoom();
        // Every email fails, like Brevo being down.
        $this->app->make(ChannelManager::class)->extend('mail', fn () => new class {
            public function send($notifiable, $notification) { throw new RuntimeException('mail down'); }
        });

        $this->assign($landlord, $room, $tenant)->assertExactJson(['status' => 'success']);
        $c = Tenancy::first();
        $this->assertNotNull($c);
        $this->assertSame(1, $tenant->unreadNotifications()->count());   // the dashboard notice still arrives

        $this->edit($landlord, $c)->assertRedirect('/landlord/tenants')->assertSessionHas('msg', fn ($m) => str_contains($m, 'was updated'));
        $this->assertSame(4500.0, $c->fresh()->monthly_rent);
        $this->assertSame(2, $tenant->unreadNotifications()->count());
    }

    public function test_every_notification_failing_does_not_break_assign(): void
    {
        [$landlord, $room, $tenant] = $this->setUpRoom();
        $this->app->instance(\Illuminate\Contracts\Notifications\Dispatcher::class, new class {
            public function send($n, $x) { throw new RuntimeException('down'); }
            public function sendNow($n, $x, $c = null) { throw new RuntimeException('down'); }
        });

        $this->assign($landlord, $room, $tenant)->assertExactJson(['status' => 'success']);
        $this->assertSame(1, Tenancy::count());
        $this->assertSame('Available', $room->fresh()->status);
    }

    public function test_dashboard_lists_notices_and_mark_all_read(): void
    {
        [$landlord, $room, $tenant] = $this->setUpRoom();
        $this->assign($landlord, $room, $tenant);
        $c = Tenancy::first();
        $this->travel(1)->minutes();
        $this->edit($landlord, $c);

        $page = $this->actingAs($tenant)->get('/tenant/dashboard')->assertOk()
            ->assertSee('Notifications')
            ->assertSeeInOrder(['Your rental contract was updated', 'You have a new rental contract'])   // newest first
            ->assertSee('Mang Jose changed your contract for Room 7.')
            ->assertSee('href="' . route('contracts.show', $c->tenant_id) . '"', false)
            ->assertSee('Mark all as read');

        $this->post(route('tenant.notifications.read'))->assertRedirect('/tenant/dashboard')->assertSessionHas('msg', 'All notifications were marked as read.');
        $this->assertSame(0, $tenant->unreadNotifications()->count());
        // Read notices stay on the dashboard (no longer marked new), so the tenant can look back at them.
        $this->get('/tenant/dashboard')->assertDontSee('Mark all as read')->assertDontSee('class="new-tag"', false)
            ->assertSee('You have a new rental contract')->assertSee('Your rental contract was updated');
    }

    public function test_dashboard_shows_at_most_ten(): void
    {
        [$landlord, $room, $tenant] = $this->setUpRoom();
        $c = Tenancy::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-12-01', 'end_date' => '2027-06-01', 'status' => 'Active']);
        for ($i = 0; $i < 12; $i++) {
            $tenant->notifyNow(ContractNotice::for($c, ContractNotice::UPDATED), ['database']);
        }

        $page = $this->actingAs($tenant)->get('/tenant/dashboard')->getContent();
        $this->assertSame(10, substr_count($page, 'Your rental contract was updated'));
    }

    public function test_notices_belong_to_their_tenant_only(): void
    {
        [$landlord, $room, $tenant] = $this->setUpRoom();
        $other = $this->user('o@gmail.com', 'tenant');
        $this->assign($landlord, $room, $tenant);

        $this->actingAs($other)->get('/tenant/dashboard')->assertDontSee('You have a new rental contract');
        $this->post(route('tenant.notifications.read'));
        $this->assertSame(1, $tenant->unreadNotifications()->count());   // only my own are marked
    }

    public function test_only_tenants_can_mark_read(): void
    {
        [$landlord] = $this->setUpRoom();

        $this->actingAs($landlord)->post(route('tenant.notifications.read'))->assertRedirect(route('login'));
        auth()->logout();
        $this->post(route('tenant.notifications.read'))->assertRedirect(route('login'));
    }

    public function test_opening_the_contract_marks_its_notices_read(): void
    {
        [$landlord, $room, $tenant] = $this->setUpRoom();
        $this->assign($landlord, $room, $tenant);
        $c = Tenancy::first();

        $this->actingAs($landlord)->get(route('contracts.show', $c->tenant_id))->assertOk();
        $this->assertSame(1, $tenant->unreadNotifications()->count());   // the landlord looking doesn't count

        $this->actingAs($tenant)->get(route('contracts.show', $c->tenant_id))->assertOk();
        $this->assertSame(0, $tenant->unreadNotifications()->count());
    }
}
