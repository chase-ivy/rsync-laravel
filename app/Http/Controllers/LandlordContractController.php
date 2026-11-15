<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\AdminTenantController;
use App\Models\ContractTemplate;
use App\Models\Room;
use App\Models\Tenancy;
use App\Notifications\ContractNotice;
use App\Support\ContractNotifier;
use App\Support\ContractDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Landlords write their own contract terms:
 * - the Contract page = their template, copied onto every new contract when a tenant is assigned;
 * - "Edit contract" on My Tenants = change the terms of one current contract.
 * Routes are only open to landlords / sublessors (role middleware); saving needs a verified email.
 */
class LandlordContractController extends Controller
{
    /** GET /landlord/contract — my contract template (the standard format until I save my own). */
    public function template(Request $request): View
    {
        $user = $request->user();
        $template = $user->contractTemplate;

        // "Reset to the standard format" reloads the page with ?standard=1 (nothing is saved yet).
        $standard = $request->boolean('standard');
        $terms = ($template && ! $standard) ? $template->toTerms() : ContractDefaults::terms();

        return view('landlord.contract', [
            'terms' => $terms,
            'hasTemplate' => (bool) $template,
            'standard' => $standard,
            'savedAt' => $template?->updated_at,
            ...$this->flash($request),
        ]);
    }

    /** POST /landlord/contract — save my template. Contracts already made keep their own copy. */
    public function saveTemplate(Request $request): RedirectResponse
    {
        $data = $this->validateTerms($request);

        ContractTemplate::updateOrCreate(['user_id' => $request->user()->user_id], $data);

        return redirect()->route('landlord.contract')
            ->with('msg', 'Your contract format was saved. New contracts will use it.');
    }

    /** GET /landlord/contract/preview — a sample contract built from my saved template. */
    public function preview(Request $request): View
    {
        $user = $request->user();
        $room = Room::where('owner_id', $user->user_id)->orderBy('room_id')->first();

        // A pretend contract that is never saved; only used to fill the page.
        $sample = new Tenancy(['status' => 'Sample', 'start_date' => now()->startOfMonth()->addMonth(), 'end_date' => now()->startOfMonth()->addMonths(7)]);
        $sample->setRelation('room', $room);
        $terms = ContractTemplate::termsFor($user);
        $terms['monthly_rent'] = (float) ($room?->price ?? 0);

        return view('contracts.show', [
            'contract' => $sample,
            'terms' => $terms,
            'code' => 'SAMPLE',
            'tenant' => null,
            'room' => $room,
            'landlord' => $user,
            'months' => 6,
            'backRoute' => 'landlord.contract',
            'backLabel' => 'Back to Contract format',
            'preview' => true,
        ]);
    }

    /** GET /landlord/contracts/{tenancy}/edit — change the terms of one current contract. */
    public function edit(Request $request, Tenancy $tenancy): View|RedirectResponse
    {
        if ($refused = $this->refuse($request, $tenancy)) {
            return $refused;
        }

        $terms = $tenancy->contractTerms();
        if ($request->boolean('standard')) {
            // Refill the terms with the standard format; rent and dates stay as they are.
            $terms = ContractDefaults::terms() + ['monthly_rent' => $terms['monthly_rent']];
        }

        return view('landlord.contract-edit', [
            'contract' => $tenancy,
            'code' => AdminTenantController::code($tenancy->tenant_id),
            'terms' => $terms,
            'standard' => $request->boolean('standard'),
        ]);
    }

    /** POST /landlord/contracts/{tenancy}/edit */
    public function update(Request $request, Tenancy $tenancy): RedirectResponse
    {
        if ($refused = $this->refuse($request, $tenancy)) {
            return $refused;
        }

        $start = $tenancy->start_date->format('Y-m-d');
        $extra = $request->validate([
            'monthly_rent' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:' . $start],
        ], [
            'monthly_rent.required' => 'Please enter the monthly rent.',
            'monthly_rent.numeric' => 'The monthly rent must be a number.',
            'monthly_rent.min' => 'The monthly rent cannot be negative.',
            'monthly_rent.max' => 'The monthly rent looks too high. Please check it.',
            'end_date.required' => 'Please enter the end date.',
            'end_date.date_format' => 'Please enter a valid end date.',
            'end_date.after' => 'The end date must be after the start date (' . Carbon::parse($start)->format('M j, Y') . ').',
        ]);
        $data = $this->validateTerms($request);
        $new = $data + [
            'monthly_rent' => round((float) $extra['monthly_rent'], 2),
            'end_date' => $extra['end_date'],
        ];

        // Did anything in the agreement really change (compared with what the tenant sees now)?
        $changed = $this->agreementOf($tenancy->contractTerms() + ['end_date' => $tenancy->end_date->format('Y-m-d')])
            !== $this->agreementOf($new);
        $wasAccepted = $tenancy->isAccepted();

        $tenancy->update($new + ['terms_updated_at' => now()]
            // A changed contract must be accepted again by the tenant.
            + ($changed ? ['accepted_at' => null, 'accepted_ip' => null] : []));

        // Optional: use these terms for my future contracts too.
        if ($request->boolean('save_as_default')) {
            ContractTemplate::updateOrCreate(['user_id' => $request->user()->user_id], $data);
        }

        $code = AdminTenantController::code($tenancy->tenant_id);
        if (! $changed) {
            return redirect()->route('landlord.tenants')
                ->with('msg', "Contract {$code} was saved. Nothing in the agreement changed, so the tenant was not notified.");
        }

        // Tell the tenant (dashboard notice + email); a failed email never undoes the change.
        ContractNotifier::send($tenancy, ContractNotice::UPDATED);

        return redirect()->route('landlord.tenants')
            ->with('msg', "Contract {$code} was updated. The tenant sees the new terms on their contract"
                . ($wasAccepted ? ' and has to accept the contract again.' : ' and was asked to accept it.'));
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Checks and cleans the fields shared by the template and one contract.
     * The two text boxes become lists (one item per line, blank lines removed).
     */
    private function validateTerms(Request $request): array
    {
        $lineLimit = function (string $attribute, mixed $value, \Closure $fail) {
            if (count(ContractDefaults::toLines((string) $value)) > ContractDefaults::MAX_LINES) {
                $fail('Please keep it to ' . ContractDefaults::MAX_LINES . ' lines or fewer (one item per line).');
            }
        };

        $data = $request->validate([
            'repairs_policy' => ['required', 'string', Rule::in(array_keys(ContractDefaults::REPAIRS))],
            'deposit' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'advance_months' => ['nullable', 'integer', 'min:0', 'max:12'],
            'utilities_note' => ['nullable', 'string', 'max:255'],
            'terms' => ['nullable', 'string', 'max:' . ContractDefaults::MAX_CHARS, $lineLimit],
            'house_rules' => ['nullable', 'string', 'max:' . ContractDefaults::MAX_CHARS, $lineLimit],
        ], [
            'repairs_policy.required' => 'Please choose who pays for repairs and damages.',
            'repairs_policy.in' => 'Please choose one of the options for repairs and damages.',
            'deposit.numeric' => 'The security deposit must be a number.',
            'deposit.min' => 'The security deposit cannot be negative.',
            'deposit.max' => 'The security deposit looks too high. Please check it.',
            'advance_months.integer' => 'Advance months must be a whole number.',
            'advance_months.min' => 'Advance months cannot be negative.',
            'advance_months.max' => 'Advance months can be at most 12.',
            'utilities_note.max' => 'Please keep the utilities note to 255 characters or fewer.',
            'terms.max' => 'Please keep the terms to ' . ContractDefaults::MAX_CHARS . ' characters or fewer.',
            'house_rules.max' => 'Please keep the house rules to ' . ContractDefaults::MAX_CHARS . ' characters or fewer.',
        ]);

        $note = trim((string) ($data['utilities_note'] ?? ''));

        return [
            'repairs_policy' => $data['repairs_policy'],
            'deposit' => isset($data['deposit']) ? round((float) $data['deposit'], 2) : null,
            'advance_months' => isset($data['advance_months']) ? (int) $data['advance_months'] : null,
            'utilities_note' => $note !== '' ? $note : null,
            'terms' => ContractDefaults::toLines($data['terms'] ?? ''),
            'house_rules' => ContractDefaults::toLines($data['house_rules'] ?? ''),
        ];
    }

    /** The parts of a contract the tenant agrees to, in a form that can be compared with ===. */
    private function agreementOf(array $t): array
    {
        $money = fn ($v) => $v === null ? null : number_format((float) $v, 2, '.', '');

        return [
            'monthly_rent' => $money($t['monthly_rent'] ?? null),
            'end_date' => (string) ($t['end_date'] ?? ''),
            'repairs_policy' => (string) ($t['repairs_policy'] ?? ''),
            'deposit' => $money($t['deposit'] ?? null),
            'advance_months' => isset($t['advance_months']) ? (int) $t['advance_months'] : null,
            'utilities_note' => filled($t['utilities_note'] ?? null) ? (string) $t['utilities_note'] : null,
            'terms' => array_values((array) ($t['terms'] ?? [])),
            'house_rules' => array_values((array) ($t['house_rules'] ?? [])),
        ];
    }

    /** Only the landlord who owns the room (403), and only Active contracts (back with a message). */
    private function refuse(Request $request, Tenancy $tenancy): ?RedirectResponse
    {
        $ownerId = Room::whereKey($tenancy->room_id)->value('owner_id');
        abort_unless($ownerId !== null && (int) $ownerId === (int) $request->user()->user_id, 403);

        if ($tenancy->status !== 'Active') {
            return redirect()->route('landlord.tenants')
                ->with('msg', 'err:Only current (Active) contracts can be edited.');
        }

        return null;
    }

    /** Pulls the one-time message; "err:" at the start means it is an error. */
    private function flash(Request $request): array
    {
        $msg = $request->session()->pull('msg');
        $isErr = $msg && str_starts_with($msg, 'err:');

        return ['msg' => $isErr ? substr($msg, 4) : $msg, 'isErr' => $isErr];
    }
}
