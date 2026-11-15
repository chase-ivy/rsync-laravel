<?php

namespace Tests\Feature;

use App\Models\ContractTemplate;
use App\Models\User;
use App\Support\ContractDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Landlords / sublessors write their own contract format (repairs rule, deposit, terms, house rules).
 */
class LandlordContractTemplateTest extends TestCase
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

    private function form(array $overrides = []): array
    {
        return array_merge([
            'repairs_policy' => 'tenant',
            'deposit' => '3000',
            'advance_months' => '1',
            'utilities_note' => 'Electricity is sub-metered and paid monthly.',
            'terms' => "  Rent is due every 5th.  \n\n\nNo subletting.\n   \n",
            'house_rules' => "Quiet after 10 PM.\r\nNo pets.",
        ], $overrides);
    }

    public function test_page_is_prefilled_with_the_standard_format(): void
    {
        $this->actingAs($this->user('l@gmail.com', 'landlord'))->get('/landlord/contract')->assertOk()
            ->assertSee('My Contract Format')
            ->assertSee('You are using the standard format')
            ->assertSee(ContractDefaults::TERMS[0])
            ->assertSee(ContractDefaults::HOUSE_RULES[0])
            ->assertSee(ContractDefaults::REPAIRS['landlord'])
            ->assertSee(ContractDefaults::REPAIRS['tenant'])
            ->assertSee('Reset to the standard format')
            ->assertSee(route('landlord.contract.preview'));
    }

    public function test_nav_links_on_landlord_pages(): void
    {
        $me = $this->user('l@gmail.com', 'landlord');
        foreach (['/landlord/dashboard', '/landlord/tenants', '/landlord/maintenance'] as $page) {
            $this->actingAs($me)->get($page)->assertOk()->assertSee(route('landlord.contract'));
        }
    }

    public function test_only_landlords_and_sublessors_can_open_and_save(): void
    {
        $this->get('/landlord/contract')->assertRedirect(route('login'));
        $this->post('/landlord/contract', $this->form())->assertRedirect(route('login'));

        foreach (['tenant', 'admin'] as $role) {
            $u = $this->user($role . '@gmail.com', $role);
            $this->actingAs($u)->get('/landlord/contract')->assertRedirect(route('login'));
            $this->actingAs($u)->post('/landlord/contract', $this->form())->assertRedirect(route('login'));
        }

        $sub = $this->user('s@gmail.com', 'sublessor');
        $this->actingAs($sub)->get('/landlord/contract')->assertOk();
        $this->actingAs($sub)->post('/landlord/contract', $this->form())->assertRedirect(route('landlord.contract'));
        $this->assertDatabaseHas('contract_templates', ['user_id' => $sub->user_id, 'repairs_policy' => 'tenant']);
        $this->assertSame(1, ContractTemplate::count());
    }

    public function test_saving_needs_a_verified_email(): void
    {
        $this->actingAs($this->user('l@gmail.com', 'landlord', false))->post('/landlord/contract', $this->form())
            ->assertRedirect(route('verification.notice'));
        $this->assertSame(0, ContractTemplate::count());
    }

    public function test_save_cleans_lines_and_shows_them_again(): void
    {
        $me = $this->user('l@gmail.com', 'landlord');

        $this->actingAs($me)->post('/landlord/contract', $this->form())
            ->assertRedirect(route('landlord.contract'))->assertSessionHas('msg');

        $t = ContractTemplate::where('user_id', $me->user_id)->first();
        $this->assertSame('tenant', $t->repairs_policy);
        $this->assertSame(3000.0, $t->deposit);
        $this->assertSame(1, $t->advance_months);
        $this->assertSame(['Rent is due every 5th.', 'No subletting.'], $t->terms);
        $this->assertSame(['Quiet after 10 PM.', 'No pets.'], $t->house_rules);

        $this->actingAs($me)->get('/landlord/contract')->assertOk()
            ->assertSee('Your contract format was saved')
            ->assertSee("Rent is due every 5th.\nNo subletting.", false)
            ->assertDontSee(ContractDefaults::TERMS[0])
            ->assertDontSee('You are using the standard format');

        // Saving again updates the same row.
        $this->actingAs($me)->post('/landlord/contract', $this->form(['repairs_policy' => 'shared', 'deposit' => '', 'advance_months' => '', 'utilities_note' => '  ']));
        $t->refresh();
        $this->assertSame(1, ContractTemplate::count());
        $this->assertSame('shared', $t->repairs_policy);
        $this->assertNull($t->deposit);
        $this->assertNull($t->advance_months);
        $this->assertNull($t->utilities_note);
    }

    public function test_reset_shows_the_standard_format_without_saving(): void
    {
        $me = $this->user('l@gmail.com', 'landlord');
        $this->actingAs($me)->post('/landlord/contract', $this->form());

        $this->actingAs($me)->get('/landlord/contract?standard=1')->assertOk()
            ->assertSee('The boxes now show the standard format')
            ->assertSee(ContractDefaults::TERMS[0]);
        $this->assertSame(['Rent is due every 5th.', 'No subletting.'], ContractTemplate::first()->terms);
    }

    public function test_validation_messages(): void
    {
        $me = $this->user('l@gmail.com', 'landlord');
        $post = fn (array $o) => $this->actingAs($me)->from('/landlord/contract')->post('/landlord/contract', $this->form($o));

        $post(['repairs_policy' => 'nobody'])->assertRedirect('/landlord/contract')
            ->assertSessionHasErrors(['repairs_policy' => 'Please choose one of the options for repairs and damages.']);
        $post(['repairs_policy' => ''])->assertSessionHasErrors(['repairs_policy' => 'Please choose who pays for repairs and damages.']);
        $post(['deposit' => '-1'])->assertSessionHasErrors(['deposit' => 'The security deposit cannot be negative.']);
        $post(['deposit' => 'abc'])->assertSessionHasErrors(['deposit' => 'The security deposit must be a number.']);
        $post(['advance_months' => '13'])->assertSessionHasErrors(['advance_months' => 'Advance months can be at most 12.']);
        $post(['advance_months' => '1.5'])->assertSessionHasErrors('advance_months');
        $post(['utilities_note' => str_repeat('a', 256)])->assertSessionHasErrors('utilities_note');
        $post(['terms' => implode("\n", array_fill(0, 31, 'Line'))])
            ->assertSessionHasErrors(['terms' => 'Please keep it to 30 lines or fewer (one item per line).']);
        $post(['house_rules' => implode("\n", array_fill(0, 31, 'Rule'))])->assertSessionHasErrors('house_rules');
        $post(['house_rules' => str_repeat('a', 5001)])->assertSessionHasErrors('house_rules');

        // 30 lines plus blank lines is fine (blank lines don't count).
        $post(['terms' => implode("\n\n", array_fill(0, 30, 'Line'))])->assertSessionHasNoErrors();

        $this->assertSame(30, count(ContractTemplate::first()->terms));
    }

    public function test_errors_are_shown_on_the_page_with_what_was_typed(): void
    {
        $me = $this->user('l@gmail.com', 'landlord');

        $this->actingAs($me)->from('/landlord/contract')->followingRedirects()
            ->post('/landlord/contract', $this->form(['deposit' => '-5', 'utilities_note' => 'Typed note']))
            ->assertSee('The security deposit cannot be negative.')
            ->assertSee('Typed note');
    }

    public function test_preview_uses_my_template(): void
    {
        $me = $this->user('l@gmail.com', 'landlord');
        $this->actingAs($me)->post('/landlord/contract', $this->form());

        $this->actingAs($me)->get('/landlord/contract/preview')->assertOk()
            ->assertSee('Sample preview')
            ->assertSee('Rent is due every 5th.')
            ->assertSee(ContractDefaults::REPAIRS_SENTENCES['tenant'])
            ->assertSee('₱3,000.00')
            ->assertSee('Electricity is sub-metered and paid monthly.');

        $this->actingAs($this->user('t@gmail.com', 'tenant'))->get('/landlord/contract/preview')->assertRedirect(route('login'));
    }

    public function test_typed_text_is_escaped(): void
    {
        $me = $this->user('l@gmail.com', 'landlord');
        $this->actingAs($me)->post('/landlord/contract', $this->form(['terms' => '<script>alert(1)</script>']));

        $this->actingAs($me)->get('/landlord/contract')->assertDontSee('<script>alert(1)</script>', false);
        $this->actingAs($me)->get('/landlord/contract/preview')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
