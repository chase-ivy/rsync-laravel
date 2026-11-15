<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Models\Room;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature 5: the landlord moves maintenance requests of their own rooms along
 * (Pending → In Progress → Completed) and writes a short reply the tenant sees.
 */
class LandlordMaintenanceReviewTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role, bool $verified = true): User
    {
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'age' => 30, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        if ($verified) {
            $u->markEmailAsVerified();
        }

        return $u;
    }

    private function request(User $owner, string $status = 'Pending', ?User $tenant = null): MaintenanceRequest
    {
        $tenant ??= $this->user('t' . uniqid() . '@gmail.com', 'tenant');
        $room = Room::create(['owner_id' => $owner->user_id, 'room_name' => 'Room ' . uniqid(), 'price' => 1, 'max_occupants' => 1]);

        return MaintenanceRequest::create(['tenant_id' => $tenant->user_id, 'room_id' => $room->room_id, 'description' => 'Leaking faucet', 'status' => $status]);
    }

    public function test_landlord_moves_a_request_along_with_a_reply(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        $req = $this->request($me);

        $this->actingAs($me)->post('/landlord/maintenance/status', ['request_id' => $req->request_id, 'status' => 'In Progress', 'landlord_note' => '  Plumber comes Monday. '])
            ->assertRedirect(route('landlord.maintenance'))
            ->assertSessionHas('msg', 'MR-' . str_pad((string) $req->request_id, 3, '0', STR_PAD_LEFT) . ' marked In Progress.');

        $req->refresh();
        $this->assertSame('In Progress', $req->status);
        $this->assertSame('Plumber comes Monday.', $req->landlord_note);
        $this->assertNull($req->resolved_at);

        $this->post('/landlord/maintenance/status', ['request_id' => $req->request_id, 'status' => 'Completed', 'landlord_note' => 'Fixed.']);
        $req->refresh();
        $this->assertSame('Completed', $req->status);
        $this->assertNotNull($req->resolved_at);
        $this->assertSame('Fixed.', $req->landlord_note);
    }

    public function test_completed_stays_completed_but_the_reply_can_change(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        $req = $this->request($me, 'Completed');
        $req->forceFill(['resolved_at' => '2026-05-01 10:00:00'])->save();

        $this->actingAs($me)->post('/landlord/maintenance/status', ['request_id' => $req->request_id, 'status' => 'In Progress'])
            ->assertSessionHas('msg', 'err:MR-' . str_pad((string) $req->request_id, 3, '0', STR_PAD_LEFT) . ' is already completed.');
        $this->assertSame('Completed', $req->fresh()->status);

        $this->post('/landlord/maintenance/status', ['request_id' => $req->request_id, 'status' => 'Completed', 'landlord_note' => 'Thanks!'])
            ->assertSessionHas('msg', 'Reply on MR-' . str_pad((string) $req->request_id, 3, '0', STR_PAD_LEFT) . ' saved.');
        $req->refresh();
        $this->assertSame('Thanks!', $req->landlord_note);
        $this->assertSame('2026-05-01 10:00:00', $req->resolved_at->format('Y-m-d H:i:s'));   // first completion time kept
    }

    public function test_input_is_validated(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        $req = $this->request($me);
        $this->actingAs($me);

        $this->post('/landlord/maintenance/status', ['request_id' => $req->request_id, 'status' => 'Pending'])->assertSessionHas('msg', 'err:Unknown status.');
        $this->post('/landlord/maintenance/status', ['request_id' => $req->request_id, 'status' => 'Done'])->assertSessionHas('msg', 'err:Unknown status.');
        $this->post('/landlord/maintenance/status', ['request_id' => $req->request_id, 'status' => 'Completed', 'landlord_note' => str_repeat('a', 501)])
            ->assertSessionHas('msg', 'err:The reply must be 500 characters or fewer.');
        $this->post('/landlord/maintenance/status', ['request_id' => 99999, 'status' => 'Completed'])->assertSessionHas('msg', 'err:Request not found.');
        $this->post('/landlord/maintenance/status', ['request_id' => 'abc', 'status' => 'Completed'])->assertSessionHas('msg', 'err:Request not found.');

        $this->assertSame('Pending', $req->fresh()->status);
    }

    public function test_landlord_cannot_touch_another_landlords_request(): void
    {
        $theirs = $this->request($this->user('other@gmail.com', 'landlord'));

        $this->actingAs($this->user('me@gmail.com', 'landlord'))
            ->post('/landlord/maintenance/status', ['request_id' => $theirs->request_id, 'status' => 'Completed', 'landlord_note' => 'Hacked'])
            ->assertForbidden();

        $theirs->refresh();
        $this->assertSame('Pending', $theirs->status);
        $this->assertNull($theirs->landlord_note);
    }

    public function test_only_verified_landlords_and_sublessors_can_update(): void
    {
        $owner = $this->user('me@gmail.com', 'landlord');
        $req = $this->request($owner);

        $this->post('/landlord/maintenance/status', ['request_id' => $req->request_id, 'status' => 'Completed'])->assertRedirect(route('login'));
        $this->actingAs($this->user('t@gmail.com', 'tenant'))->post('/landlord/maintenance/status', ['request_id' => $req->request_id, 'status' => 'Completed'])->assertRedirect(route('login'));
        $this->actingAs($this->user('u@gmail.com', 'landlord', false))->post('/landlord/maintenance/status', ['request_id' => $req->request_id, 'status' => 'Completed'])->assertRedirect(route('verification.notice'));
        $this->assertSame('Pending', $req->fresh()->status);

        $sub = $this->user('s@gmail.com', 'sublessor');
        $subReq = $this->request($sub);
        $this->actingAs($sub)->post('/landlord/maintenance/status', ['request_id' => $subReq->request_id, 'status' => 'In Progress']);
        $this->assertSame('In Progress', $subReq->fresh()->status);
    }

    public function test_status_filter_and_review_form_on_the_page(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        $tenant = $this->user('tina@gmail.com', 'tenant');
        $pending = $this->request($me, 'Pending', $tenant);
        $pending->forceFill(['description' => 'Broken lock'])->save();
        $done = $this->request($me, 'Completed', $tenant);
        $done->forceFill(['description' => 'Fixed light'])->save();

        $this->actingAs($me)->get('/landlord/maintenance')->assertOk()
            ->assertSee('Broken lock')->assertSee('Fixed light')
            ->assertSee('Tina')
            ->assertSee(route('landlord.maintenance.status'), false)
            ->assertSee('href="' . route('landlord.maintenance', ['status' => 'In Progress']) . '"', false);

        $this->get('/landlord/maintenance?status=Pending')->assertSee('Broken lock')->assertDontSee('Fixed light')
            ->assertSee('name="filter" value="Pending"', false);
        $this->get('/landlord/maintenance?status=Completed')->assertSee('Fixed light')->assertDontSee('Broken lock')->assertSee('Save reply');
        $this->get('/landlord/maintenance?status=bogus')->assertSee('Broken lock')->assertSee('Fixed light');   // unknown = All

        // After saving from a filtered list, the landlord comes back to the same filter.
        $this->post('/landlord/maintenance/status', ['request_id' => $pending->request_id, 'status' => 'In Progress', 'filter' => 'Pending'])
            ->assertRedirect(route('landlord.maintenance', ['status' => 'Pending']));
        $this->post('/landlord/maintenance/status', ['request_id' => $pending->request_id, 'status' => 'Completed', 'filter' => 'https://evil.example'])
            ->assertRedirect(route('landlord.maintenance'));
    }

    public function test_tenant_sees_the_landlords_reply(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        $tenant = $this->user('tina@gmail.com', 'tenant');
        $req = $this->request($me, 'Pending', $tenant);
        Tenancy::create(['user_id' => $tenant->user_id, 'room_id' => $req->room_id, 'start_date' => '2026-06-01', 'end_date' => '2027-06-01', 'status' => 'Active']);

        $this->actingAs($me)->post('/landlord/maintenance/status', ['request_id' => $req->request_id, 'status' => 'In Progress', 'landlord_note' => 'Plumber <comes> Monday']);

        $this->actingAs($tenant)->get('/tenant/dashboard')->assertOk()
            ->assertSee("Landlord's reply:", false)
            ->assertSee('Plumber &lt;comes&gt; Monday', false)
            ->assertSee('In Progress');
    }

    public function test_admin_can_still_update(): void
    {
        $req = $this->request($this->user('me@gmail.com', 'landlord'));

        $this->actingAs($this->user('a@gmail.com', 'admin'))->post('/admin/maintenance/status', ['request_id' => $req->request_id, 'status' => 'Completed']);

        $this->assertSame('Completed', $req->fresh()->status);
    }
}
