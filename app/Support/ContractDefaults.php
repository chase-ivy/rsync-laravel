<?php

namespace App\Support;

/**
 * The "standard format" of a rental contract: the starting terms and house rules every landlord
 * gets before they write their own, the choices for "who pays for repairs", and small helpers
 * to turn a textarea (one item per line) into a list and back.
 */
class ContractDefaults
{
    /** Who pays for repairs and damages: key saved in the database => label in the dropdown. */
    public const REPAIRS = [
        'landlord' => 'Landlord pays for repairs (except damage caused by the tenant)',
        'tenant' => 'Tenant pays for repairs and damages in the room',
        'shared' => 'Shared — explained in the terms below',
    ];

    /** The sentence printed on the contract for each choice above. */
    public const REPAIRS_SENTENCES = [
        'landlord' => 'The landlord pays for repairs in the room and the building, except damage caused by the tenant or their guests, which the tenant pays for.',
        'tenant' => 'The tenant pays for repairs and for any damage in the room during the stay.',
        'shared' => 'The landlord and the tenant share the cost of repairs and damages as explained in the terms and conditions below.',
    ];

    public const DEFAULT_REPAIRS = 'landlord';

    /** Limits for the terms and house-rules boxes (checked when saving). */
    public const MAX_LINES = 30;

    public const MAX_CHARS = 5000;

    public const TERMS = [
        'Rent is paid in advance on or before the same day of each month as the start date. Payments are recorded in RSYNC and confirmed by the landlord or admin.',
        'The room may only be used as a residence by the tenant. The number of occupants must not go over the maximum stated above.',
        'The tenant keeps the room clean and in good condition and pays for damage caused by the tenant or their guests, except normal wear and tear.',
        'Repairs are requested through the Maintenance section of RSYNC. The landlord attends to them within a reasonable time.',
        'The tenant may not sublet the room or transfer this agreement without the landlord\'s written consent.',
        'Either party who wants to end the agreement before the end date gives at least 30 days\' written notice.',
        'On leaving, the tenant returns all keys and leaves the room in the condition it was received.',
    ];

    public const HOUSE_RULES = [
        'Quiet hours are from 10:00 PM to 6:00 AM. Be considerate of other boarders.',
        'Visitors are received only in common areas and must leave by 9:00 PM unless the landlord agrees otherwise.',
        'Smoking, illegal drugs, gambling and weapons are not allowed on the premises.',
        'Cooking is allowed only in designated areas. Unplug appliances when not in use and follow fire safety rules.',
        'Save water and electricity. Report leaks and electrical problems right away.',
        'Throw away garbage properly and follow the barangay\'s waste segregation schedule.',
    ];

    /** The standard set of contract terms (no deposit, advance or utilities note). */
    public static function terms(): array
    {
        return [
            'repairs_policy' => self::DEFAULT_REPAIRS,
            'deposit' => null,
            'advance_months' => null,
            'utilities_note' => null,
            'terms' => self::TERMS,
            'house_rules' => self::HOUSE_RULES,
        ];
    }

    /** Sentence for a repairs key; unknown keys fall back to the standard one. */
    public static function repairsSentence(?string $key): string
    {
        return self::REPAIRS_SENTENCES[$key] ?? self::REPAIRS_SENTENCES[self::DEFAULT_REPAIRS];
    }

    /** Textarea → list: one item per line, each trimmed, blank lines removed. */
    public static function toLines(?string $text): array
    {
        $lines = array_map('trim', preg_split('/\r\n|\r|\n/', (string) $text));

        return array_values(array_filter($lines, fn ($line) => $line !== ''));
    }

    /** List → textarea text (one item per line). */
    public static function toText(?array $lines): string
    {
        return implode("\n", $lines ?? []);
    }
}
