<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Profile pictures (everyone), business permits (landlords / sublessors), the landlord's
 * My Tenants page, and sublessors using the landlord dashboard.
 */
class ProfilePhotoPermitTenantsTest extends TestCase
{
    use RefreshDatabase;

    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Pictures go to a temporary folder and permits to a fake disk, never to the real folders.
        $this->publicDir = sys_get_temp_dir() . '/rsync-test-public-' . uniqid();
        File::makeDirectory($this->publicDir . '/images', 0755, true);
        $this->app->usePublicPath($this->publicDir);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicDir);
        parent::tearDown();
    }

    private function user(string $email, string $role = 'tenant', string $name = 'Kobe Bryant'): User
    {
        $user = User::create(['name' => $name, 'age' => 21, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        $user->markEmailAsVerified();

        return $user;
    }

    // ---------- profile picture ----------

    public function test_upload_and_replace_profile_picture(): void
    {
        $user = $this->user('t@gmail.com');

        $this->actingAs($user)->from('/account')->post('/account/photo', ['photo' => UploadedFile::fake()->image('me.jpg')])
            ->assertRedirect('/account')->assertSessionHas('msg', 'Your profile picture was updated.');
        $first = $user->fresh()->profile_photo;
        $this->assertMatchesRegularExpression('/^avatar_\d+_[0-9a-f]{13}\.jpg$/', $first);
        $this->assertFileExists($this->publicDir . '/images/' . $first);

        $this->post('/account/photo', ['photo' => UploadedFile::fake()->image('new.png')]);
        $second = $user->fresh()->profile_photo;
        $this->assertNotSame($first, $second);
        $this->assertFileDoesNotExist($this->publicDir . '/images/' . $first);   // old one removed

        $this->get('/account')->assertSee('images/' . $second, false);
    }

    public function test_profile_picture_must_be_an_image(): void
    {
        $user = $this->user('t@gmail.com');

        $this->actingAs($user)->from('/account')->post('/account/photo', ['photo' => UploadedFile::fake()->create('virus.php', 1, 'text/x-php')])
            ->assertSessionHasErrorsIn('photo', ['photo']);

        $this->assertNull($user->fresh()->profile_photo);
    }

    public function test_remove_profile_picture(): void
    {
        $user = $this->user('t@gmail.com');
        $this->actingAs($user)->post('/account/photo', ['photo' => UploadedFile::fake()->image('me.jpg')]);
        $file = $user->fresh()->profile_photo;

        $this->post('/account/photo/remove');

        $this->assertNull($user->fresh()->profile_photo);
        $this->assertFileDoesNotExist($this->publicDir . '/images/' . $file);
        $this->get('/account')->assertSee('KB');   // initials instead of a picture
    }

    // ---------- business permit ----------

    public function test_tenants_do_not_get_the_permit_section(): void
    {
        $tenant = $this->user('t@gmail.com');

        $this->actingAs($tenant)->get('/account')->assertDontSee('Business permit');
        $this->post('/account/permit', ['permit' => UploadedFile::fake()->image('p.jpg')])->assertForbidden();
    }

    public function test_landlord_uploads_permit_and_it_waits_for_review(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $landlord->forceFill(['verification_rejected_at' => now()])->save();   // was rejected before

        $this->actingAs($landlord)->get('/account')->assertSee('Business permit')->assertSee('Not uploaded');

        $this->from('/account')->post('/account/permit', ['permit' => UploadedFile::fake()->create('permit.pdf', 200, 'application/pdf')])
            ->assertSessionHas('msg', 'Business permit uploaded. An admin will review it.');

        $landlord->refresh();
        $this->assertNotNull($landlord->business_permit);
        $this->assertNull($landlord->verification_rejected_at);   // back in the review queue
        $this->assertSame('pending', $landlord->permitStatus());
        Storage::disk('local')->assertExists('permits/' . $landlord->business_permit);
        $this->get('/account')->assertSee('Waiting for review');
    }

    public function test_sublessor_can_upload_a_permit_too(): void
    {
        $sub = $this->user('s@gmail.com', 'sublessor');

        $this->actingAs($sub)->post('/account/permit', ['permit' => UploadedFile::fake()->image('permit.jpg')]);

        $this->assertNotNull($sub->fresh()->business_permit);
    }

    public function test_only_the_owner_and_admins_can_open_a_permit(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $this->actingAs($landlord)->post('/account/permit', ['permit' => UploadedFile::fake()->image('permit.jpg')]);
        $url = '/account/permit/' . $landlord->user_id;

        $this->get($url)->assertOk();
        $this->actingAs($this->user('a@gmail.com', 'admin'))->get($url)->assertOk();
        $this->actingAs($this->user('t@gmail.com'))->get($url)->assertForbidden();
        $this->actingAs($this->user('l2@gmail.com', 'landlord'))->get($url)->assertForbidden();
    }

    public function test_admin_sees_landlords_with_a_permit_in_verify_users(): void
    {
        $withPermit = $this->user('l@gmail.com', 'landlord', 'Permit Landlord');
        $this->user('l2@gmail.com', 'landlord', 'No Permit Landlord');
        $this->actingAs($withPermit)->post('/account/permit', ['permit' => UploadedFile::fake()->image('permit.jpg')]);

        $admin = $this->user('a@gmail.com', 'admin');
        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertSee('Permit Landlord')
            ->assertSee(route('account.permit.show', $withPermit->user_id))
            ->assertDontSee('No Permit Landlord');

        $this->post(route('admin.users.approve', $withPermit->user_id));
        $this->assertSame('approved', $withPermit->fresh()->permitStatus());
    }

    public function test_on_render_permits_go_to_the_private_supabase_bucket(): void
    {
        config(['services.photos.driver' => 'supabase', 'services.photos.supabase_url' => 'https://abcd1234.supabase.co',
            'services.photos.supabase_key' => 'sb_secret_test', 'services.photos.private_bucket' => 'documents']);
        Http::fake(['https://abcd1234.supabase.co/*' => Http::sequence()
            ->push(['Key' => 'ok'], 200)                                      // upload
            ->push('PDFDATA', 200, ['Content-Type' => 'application/pdf'])]);  // view
        $landlord = $this->user('l@gmail.com', 'landlord');

        $this->actingAs($landlord)->post('/account/permit', ['permit' => UploadedFile::fake()->create('permit.pdf', 10, 'application/pdf')]);
        $name = $landlord->fresh()->business_permit;

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST'
            && $r->url() === 'https://abcd1234.supabase.co/storage/v1/object/documents/' . $name
            && $r->hasHeader('apikey', 'sb_secret_test'));

        $this->get('/account/permit/' . $landlord->user_id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    // ---------- My Tenants page ----------

    public function test_landlord_sees_only_current_tenants_of_own_rooms(): void
    {
        $me = $this->user('l@gmail.com', 'landlord', 'Me Landlord');
        $other = $this->user('o@gmail.com', 'landlord', 'Other Landlord');
        $mine = Room::create(['owner_id' => $me->user_id, 'room_name' => 'Room 201', 'price' => 4500, 'max_occupants' => 3]);
        $theirs = Room::create(['owner_id' => $other->user_id, 'room_name' => 'Room 999', 'price' => 4500, 'max_occupants' => 3]);

        $rent = fn (string $name, Room $room, string $status) => Tenancy::create([
            'user_id' => $this->user(strtolower(str_replace(' ', '', $name)) . '@gmail.com', 'tenant', $name)->user_id,
            'room_id' => $room->room_id, 'start_date' => '2026-05-01', 'end_date' => '2027-05-01', 'status' => $status,
        ]);
        $rent('Current Tenant', $mine, 'Active');
        $rent('Former Tenant', $mine, 'Left');
        $rent('Not Mine', $theirs, 'Active');

        $this->actingAs($me)->get('/landlord/tenants')
            ->assertOk()
            ->assertSee('Current Tenant')
            ->assertSee('Room 201')
            ->assertSee('May 1, 2026')
            ->assertSee('May 1, 2027')
            ->assertSee('Active')
            ->assertDontSee('Former Tenant')
            ->assertDontSee('Not Mine');
    }

    public function test_tenants_page_is_for_landlords_and_sublessors_only(): void
    {
        $this->actingAs($this->user('t@gmail.com'))->get('/landlord/tenants')->assertRedirect(route('login'));
        $this->actingAs($this->user('s@gmail.com', 'sublessor'))->get('/landlord/tenants')->assertOk();
    }

    public function test_dashboard_nav_has_account_then_tenants(): void
    {
        $this->actingAs($this->user('l@gmail.com', 'landlord'))->get('/landlord/dashboard')
            ->assertSeeInOrder(['Account', 'Tenants', 'Maintenance', 'Logout']);
    }

    public function test_sublessor_uses_the_landlord_dashboard(): void
    {
        $sub = $this->user('s@gmail.com', 'sublessor');

        $this->actingAs($sub)->get('/landlord/dashboard')->assertOk()->assertSee('Room Management');
    }
}
