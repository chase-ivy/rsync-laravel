<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature 3: verification documents for landlords and sublessors (business permit, FSIC,
 * sanitary permit, barangay clearance), stored as private files.
 */
class OwnerDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');   // private files go to a fake disk
    }

    private function user(string $email, string $role = 'landlord', string $name = 'Lando Owner', bool $verified = true): User
    {
        $u = User::create(['name' => $name, 'age' => 30, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        if ($verified) {
            $u->markEmailAsVerified();
        }

        return $u;
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->create('doc.pdf', 200, 'application/pdf');
    }

    public function test_account_page_lists_every_document_with_its_status(): void
    {
        $landlord = $this->user('l@gmail.com');

        $this->actingAs($landlord)->get('/account')->assertOk()
            ->assertSee('Verification documents')
            ->assertSeeInOrder(['Business permit', 'Fire Safety Inspection Certificate (FSIC)', 'Sanitary Permit', 'Barangay Business Clearance'])
            ->assertSee('Required')
            ->assertSee('Optional')
            ->assertSee('Still needed:')
            ->assertSee(route('account.documents.upload', 'fire_safety_certificate'), false);
    }

    public function test_tenants_do_not_see_the_documents_card_or_upload(): void
    {
        $tenant = $this->user('t@gmail.com', 'tenant');

        $this->actingAs($tenant)->get('/account')->assertDontSee('Verification documents');
        $this->post('/account/documents/sanitary_permit', ['document' => $this->pdf()])->assertForbidden();
        $this->assertNull($tenant->fresh()->sanitary_permit);
    }

    public function test_landlord_uploads_each_document(): void
    {
        $landlord = $this->user('l@gmail.com');
        $landlord->forceFill(['verified' => true])->save();   // was approved before

        $this->actingAs($landlord)->from('/account')->post('/account/documents/fire_safety_certificate', ['document' => $this->pdf()])
            ->assertRedirect('/account')
            ->assertSessionHas('msg', 'Fire Safety Inspection Certificate (FSIC) uploaded. An admin will review it.');

        $landlord->refresh();
        $this->assertNotNull($landlord->fire_safety_certificate);
        $this->assertNotNull($landlord->fire_safety_certificate_uploaded_at);
        $this->assertFalse($landlord->verified);   // a new document goes back for review
        Storage::disk('local')->assertExists('permits/' . $landlord->fire_safety_certificate);

        $this->post('/account/documents/sanitary_permit', ['document' => UploadedFile::fake()->image('s.jpg')]);
        $this->post('/account/documents/barangay_clearance', ['document' => UploadedFile::fake()->image('b.png')]);
        $this->post('/account/documents/business_permit', ['document' => $this->pdf()])
            ->assertSessionHas('msg', 'Business permit uploaded. An admin will review it.');

        $landlord->refresh();
        $this->assertNotNull($landlord->sanitary_permit);
        $this->assertNotNull($landlord->barangay_clearance);
        $this->assertNotNull($landlord->business_permit);
        $this->assertSame([], $landlord->missingRequiredDocuments());
        $this->assertSame('pending', $landlord->permitStatus());
        $this->get('/account')->assertDontSee('Still needed:')->assertSee('Waiting for review');
    }

    public function test_sublessor_can_upload_too(): void
    {
        $sub = $this->user('s@gmail.com', 'sublessor');

        $this->actingAs($sub)->post('/account/documents/sanitary_permit', ['document' => $this->pdf()]);

        $this->assertNotNull($sub->fresh()->sanitary_permit);
    }

    public function test_new_copy_replaces_the_old_file(): void
    {
        $landlord = $this->user('l@gmail.com');
        $this->actingAs($landlord)->post('/account/documents/sanitary_permit', ['document' => $this->pdf()]);
        $first = $landlord->fresh()->sanitary_permit;

        $this->post('/account/documents/sanitary_permit', ['document' => $this->pdf()]);
        $second = $landlord->fresh()->sanitary_permit;

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing('permits/' . $first);
        Storage::disk('local')->assertExists('permits/' . $second);
    }

    public function test_upload_is_validated(): void
    {
        $landlord = $this->user('l@gmail.com');
        $this->actingAs($landlord)->from('/account');

        $this->post('/account/documents/sanitary_permit', [])
            ->assertSessionHasErrors(['document' => 'Please choose the file of your Sanitary Permit.'], null, 'doc_sanitary_permit');
        $this->post('/account/documents/sanitary_permit', ['document' => UploadedFile::fake()->create('evil.php', 1, 'text/x-php')])
            ->assertSessionHasErrors(['document' => 'The document must be a picture (JPG, PNG, WEBP) or a PDF.'], null, 'doc_sanitary_permit');
        $this->post('/account/documents/sanitary_permit', ['document' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')])
            ->assertSessionHasErrors(['document' => 'The file must be 5 MB or smaller.'], null, 'doc_sanitary_permit');
        $this->post('/account/documents/drivers_license', ['document' => $this->pdf()])->assertNotFound();

        $this->assertNull($landlord->fresh()->sanitary_permit);
    }

    public function test_upload_needs_a_verified_email(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord', 'X', false);

        $this->actingAs($landlord)->post('/account/documents/sanitary_permit', ['document' => $this->pdf()])
            ->assertRedirect(route('verification.notice'));
    }

    public function test_only_the_owner_and_admins_can_view_a_document(): void
    {
        $landlord = $this->user('l@gmail.com');
        $this->actingAs($landlord)->post('/account/documents/fire_safety_certificate', ['document' => $this->pdf()]);
        $url = route('account.documents.show', [$landlord->user_id, 'fire_safety_certificate']);

        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($this->user('a@gmail.com', 'admin'))->get($url)->assertOk();
        $this->actingAs($this->user('t@gmail.com', 'tenant'))->get($url)->assertForbidden();
        $this->actingAs($this->user('l2@gmail.com'))->get($url)->assertForbidden();
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));

        // Not uploaded / unknown type.
        $this->actingAs($landlord)->get(route('account.documents.show', [$landlord->user_id, 'sanitary_permit']))->assertNotFound();
        $this->get('/account/documents/' . $landlord->user_id . '/nope')->assertNotFound();
    }

    public function test_old_permit_route_still_works_and_shows_in_the_new_viewer(): void
    {
        $landlord = $this->user('l@gmail.com');
        $this->actingAs($landlord)->post('/account/permit', ['permit' => UploadedFile::fake()->image('permit.jpg')])
            ->assertSessionHas('msg', 'Business permit uploaded. An admin will review it.');

        $this->get('/account/permit/' . $landlord->user_id)->assertOk();
        $this->get(route('account.documents.show', [$landlord->user_id, 'business_permit']))->assertOk();
    }

    public function test_admin_sees_document_links_and_missing_required_ones(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord', 'Permit Landlord');
        $this->actingAs($landlord)->post('/account/documents/business_permit', ['document' => $this->pdf()]);
        $this->post('/account/documents/barangay_clearance', ['document' => $this->pdf()]);

        // Only an FSIC, no business permit: not in the list yet (same rule as before).
        $this->actingAs($this->user('l2@gmail.com', 'landlord', 'No Permit Landlord'))->post('/account/documents/fire_safety_certificate', ['document' => $this->pdf()]);

        $this->actingAs($this->user('a@gmail.com', 'admin'))->get('/admin/dashboard')->assertOk()
            ->assertSee('Permit Landlord')
            ->assertSee(route('account.permit.show', $landlord->user_id))
            ->assertSee(route('account.documents.show', [$landlord->user_id, 'barangay_clearance']))
            ->assertDontSee(route('account.documents.show', [$landlord->user_id, 'sanitary_permit']))
            ->assertSee('Missing: Fire Safety Inspection Certificate (FSIC), Sanitary Permit')
            ->assertDontSee('No Permit Landlord');
    }

    public function test_admin_sees_complete_note_when_all_required_documents_are_uploaded(): void
    {
        $landlord = $this->user('l@gmail.com');
        $this->actingAs($landlord);
        foreach (['business_permit', 'fire_safety_certificate', 'sanitary_permit'] as $type) {
            $this->post('/account/documents/' . $type, ['document' => $this->pdf()]);
        }

        $this->actingAs($this->user('a@gmail.com', 'admin'))->get('/admin/dashboard')
            ->assertSee('All required documents uploaded')
            ->assertDontSee('Missing:');

        $this->post(route('admin.users.approve', $landlord->user_id));
        $this->assertSame('approved', $landlord->fresh()->permitStatus());
    }
}
