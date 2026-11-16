<?php

namespace App\Support;

use App\Models\Room;
use App\Support\RoomPhotos;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The room filters of the public pages (home page and /rooms): keyword, location, budget and room type.
 *
 * Everything comes from a plain GET form, so a filtered list is a normal link that can be shared.
 * Bad values in the address bar (letters in the price, unknown areas, arrays...) are ignored, never an error.
 *
 *   ?name=loft            keyword: room name or address contains the text (same as the old rooms.php?name=)
 *   ?area=Lourdes         location: the barangay/area written in the room's address (see areaOf())
 *   ?min=2000&max=4000    budget per person per month, in pesos (whole-room prices are split, see Room::perPersonRent())
 *   ?type=solo            room type, from the room's capacity: solo (1), two (2), small (3–4), big (5+)
 */
class RoomFilters
{
    /** Room types: key in the address bar => [label in the select, smallest capacity, largest capacity or null]. */
    public const TYPES = [
        'solo' => ['Solo room (just me)', 1, 1],
        'two' => ['Shared by 2', 2, 2],
        'small' => ['Shared by 3–4', 3, 4],
        'big' => ['Bed space (5 or more)', 5, null],
    ];

    /** Highest budget we accept; anything above is treated as "no limit". */
    private const MAX_PESOS = 1_000_000;

    public string $name = '';

    public ?string $area = null;

    public ?int $min = null;

    public ?int $max = null;

    public ?string $type = null;

    /**
     * Everything a public room list needs, shared by the home page and /rooms:
     *   filters  = the filters read from the address bar
     *   areas    = [area => room count] for the Location select (from all listed rooms)
     *   rooms    = the listed rooms that match the filters (full rooms are never listed), newest first
     *   hasPins  = true when at least one listed room has a map pin (otherwise there is no map at all)
     */
    public static function browse(Request $request): array
    {
        $listed = Room::withListingInfo()->withOpenSlots()->orderByDesc('created_at')->orderByDesc('room_id')->get();
        $areas = self::areas($listed);
        $filters = self::fromRequest($request, array_keys($areas));

        $rooms = $filters->name === ''
            ? $listed
            : $filters->applyKeyword(Room::withListingInfo()->withOpenSlots())->orderByDesc('created_at')->orderByDesc('room_id')->get();

        return [
            'filters' => $filters,
            'areas' => $areas,
            'rooms' => $filters->apply($rooms),
            'hasPins' => $listed->contains(fn (Room $r) => $r->hasLocation()),
        ];
    }

    /**
     * Search suggestions while typing in the keyword box (like the old PHP "live search"):
     * rooms, areas (barangays) and streets of the listed rooms that contain the typed text.
     * Those that START with the text come first. At most $limit, no duplicates.
     *
     * @return array<int, array{type: string, label: string, sub: ?string, url: ?string}>
     */
    public static function suggest(string $text, int $limit = 8): array
    {
        $text = mb_strtolower(trim(mb_substr($text, 0, 50)));
        if ($text === '') {
            return [];
        }

        $found = [];   // lowercase label => [rank, item]
        $add = function (string $type, string $label, ?string $sub = null, ?string $url = null) use ($text, &$found) {
            $label = trim(preg_replace('/\s+/u', ' ', $label));
            $lower = mb_strtolower($label);
            $pos = mb_strpos($lower, $text);
            if ($label === '' || $pos === false || isset($found[$type . '|' . $lower])) {
                return;
            }
            // 0 = starts with the text, 1 = a word starts with it, 2 = somewhere inside.
            $rank = $pos === 0 ? 0 : (preg_match('/(^|[\s\-.,\/])' . preg_quote($text, '/') . '/u', $lower) ? 1 : 2);
            $found[$type . '|' . $lower] = [$rank, ['type' => $type, 'label' => $label, 'sub' => $sub, 'url' => $url]];
        };

        foreach (Room::withOpenSlots()->orderBy('room_name')->get(['room_id', 'room_name', 'address']) as $room) {
            $area = self::areaOf($room->address);
            $add('room', (string) $room->room_name, $area, route('rooms.show', $room->room_id));
            if ($area) {
                $add('area', $area);
            }
            // The street: the first part of the address without the house number. "12 Lower Magsaysay Ave, ..." => "Lower Magsaysay Ave"
            $street = trim(preg_replace('/^\s*(?:#|no\.?\s*)?\d+[a-z]?\b[\s,.-]*/iu', '', explode(',', (string) $room->address)[0]));
            if ($street !== '' && ! preg_match('/^(?:brgy|bgy|barangay)\b/iu', $street)) {
                $add('street', $street, $area);
            }
        }

        uasort($found, fn ($a, $b) => [$a[0], $a[1]['label']] <=> [$b[0], $b[1]['label']]);

        return array_values(array_map(fn ($f) => $f[1], array_slice($found, 0, $limit)));
    }

    /** Reads the filters from the address bar. $areas = the areas that exist (an unknown area is ignored). */
    public static function fromRequest(Request $request, array $areas): self
    {
        $f = new self;
        $f->name = mb_substr(trim(self::text($request->query('name'))), 0, 100);

        $area = trim(self::text($request->query('area')));
        foreach ($areas as $known) {
            if ($area !== '' && mb_strtolower($known) === mb_strtolower($area)) {
                $f->area = $known;
            }
        }

        $f->min = self::pesos($request->query('min'));
        $f->max = self::pesos($request->query('max'));
        if ($f->min !== null && $f->max !== null && $f->min > $f->max) {
            [$f->min, $f->max] = [$f->max, $f->min];   // typed the wrong way round: swap instead of showing nothing
        }

        $type = self::text($request->query('type'));
        $f->type = isset(self::TYPES[$type]) ? $type : null;

        return $f;
    }

    /** A query-string value as text; arrays and other junk become ''. */
    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /** "3,500", "₱3500" or "3500.50" => 3500; empty, junk, 0 or negative => null (no limit). */
    private static function pesos(mixed $value): ?int
    {
        $clean = str_replace([',', '₱', 'P', 'p', ' '], '', self::text($value));
        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }
        $f = (float) $clean;
        // Check the size before turning it into a whole number: values like "1e999" become INF,
        // and PHP 8.5 refuses to cast INF/NAN or too-big floats to int. Treat those as junk (no limit).
        if (! is_finite($f) || $f < 1) {
            return null;
        }
        if ($f >= self::MAX_PESOS) {
            return self::MAX_PESOS;
        }

        return (int) floor($f);
    }

    /**
     * The barangay/area of an address, or null when we can't tell.
     * "12 Lower Magsaysay Ave, Brgy. Lourdes" => "Lourdes"; "Bonifacio St, Aurora Hill" => "Aurora Hill";
     * "Bonifacio St" (no comma, no Brgy.) => null.
     */
    public static function areaOf(?string $address): ?string
    {
        $address = trim((string) $address);
        if ($address === '') {
            return null;
        }
        if (preg_match('/\b(?:brgy|bgy|barangay)\b\.?\s*([^,]+)/iu', $address, $m)) {
            $area = $m[1];
        } elseif (str_contains($address, ',')) {
            // The last part that is not the city, the province or a ZIP code: "21 Kisad Rd, Engineers Hill, Baguio City" => "Engineers Hill".
            $area = '';
            foreach (array_slice(array_reverse(explode(',', $address)), 0, -1) as $part) {
                $part = trim(preg_replace('/\b(baguio( city)?|benguet|philippines|ph|\d{4})\b/iu', '', $part), " .,-\t");
                if ($part !== '') {
                    $area = $part;
                    break;
                }
            }
        } else {
            return null;
        }
        $area = trim(preg_replace('/\s+/u', ' ', (string) $area), " .,-\t");
        if ($area === mb_strtolower($area)) {
            $area = mb_convert_case($area, MB_CASE_TITLE);   // "lourdes" => "Lourdes"
        }

        return $area !== '' && mb_strlen($area) <= 60 ? $area : null;
    }

    /**
     * The three numbers on the login page: listed rooms with a free slot, the lowest rent one person pays
     * (whole-room prices split by capacity, like the homepage), and how many barangays have such rooms.
     *
     * @return array{rooms: int, lowest: ?float, areas: int}
     */
    public static function stats(): array
    {
        $rooms = Room::withOpenSlots()->get();

        return [
            'rooms' => $rooms->count(),
            'lowest' => $rooms->isEmpty() ? null : (float) $rooms->min(fn (Room $r) => $r->perPersonRent()),
            'areas' => count(self::areas($rooms)),
        ];
    }

    /** The areas of the given rooms for the Location select: [area => number of rooms], A–Z. */
    public static function areas(Collection $rooms): array
    {
        $areas = [];
        foreach ($rooms as $room) {
            $area = self::areaOf($room->address);
            if ($area === null) {
                continue;
            }
            // "lourdes" and "Lourdes" are the same place; keep the first spelling.
            $key = collect(array_keys($areas))->first(fn ($a) => mb_strtolower($a) === mb_strtolower($area)) ?? $area;
            $areas[$key] = ($areas[$key] ?? 0) + 1;
        }
        ksort($areas, SORT_NATURAL | SORT_FLAG_CASE);

        return $areas;
    }

    /** Room type key of a room (from its capacity). */
    public static function typeOf(Room $room): string
    {
        $cap = max(1, (int) ($room->max_occupants ?? 1));
        foreach (self::TYPES as $key => [, $from, $to]) {
            if ($cap >= $from && ($to === null || $cap <= $to)) {
                return $key;
            }
        }

        return 'big';
    }

    /** Short words for a card, without numbers: "Solo room", "Shared room" or "Bed space". */
    public static function typeWord(Room $room): string
    {
        return match (self::typeOf($room)) {
            'solo' => 'Solo room',
            'big' => 'Bed space',
            default => 'Shared room',
        };
    }

    /** Keeps the rooms that match the location, budget and type (the keyword is done in SQL by the controllers). */
    public function apply(Collection $rooms): Collection
    {
        return $rooms->filter(function (Room $room) {
            if ($this->area !== null && mb_strtolower((string) self::areaOf($room->address)) !== mb_strtolower($this->area)) {
                return false;
            }
            if ($this->type !== null && self::typeOf($room) !== $this->type) {
                return false;
            }
            // What one tenant actually pays a month (whole-room prices are split by capacity).
            $rent = $room->perPersonRent();
            if ($this->min !== null && $rent < $this->min) {
                return false;
            }
            if ($this->max !== null && $rent > $this->max) {
                return false;
            }

            return true;
        })->values();
    }

    /** Adds the keyword part to a room query (name or address contains the text, any capitals). */
    public function applyKeyword($query)
    {
        if ($this->name === '') {
            return $query;
        }
        $like = '%' . mb_strtolower($this->name) . '%';

        return $query->where(fn ($w) => $w->whereRaw('LOWER(room_name) LIKE ?', [$like])->orWhereRaw('LOWER(address) LIKE ?', [$like]));
    }

    public function isActive(): bool
    {
        return $this->name !== '' || $this->area !== null || $this->min !== null || $this->max !== null || $this->type !== null;
    }

    /** The filters as query-string values (empty ones left out), e.g. for "See all" links. */
    public function query(): array
    {
        return array_filter([
            'name' => $this->name,
            'area' => $this->area,
            'min' => $this->min,
            'max' => $this->max,
            'type' => $this->type,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * The active filters as chips: [label, query without this filter].
     * Each chip's × link removes just that filter.
     */
    public function chips(): array
    {
        $q = $this->query();
        $chips = [];
        if ($this->name !== '') {
            $chips[] = ['“' . $this->name . '”', array_diff_key($q, ['name' => 1])];
        }
        if ($this->area !== null) {
            $chips[] = [$this->area, array_diff_key($q, ['area' => 1])];
        }
        if ($this->min !== null || $this->max !== null) {
            $label = match (true) {
                $this->min !== null && $this->max !== null => '₱' . number_format($this->min) . ' – ₱' . number_format($this->max),
                $this->min !== null => 'From ₱' . number_format($this->min),
                default => 'Up to ₱' . number_format($this->max),
            };
            $chips[] = [$label, array_diff_key($q, ['min' => 1, 'max' => 1])];
        }
        if ($this->type !== null) {
            $chips[] = [self::TYPES[$this->type][0], array_diff_key($q, ['type' => 1])];
        }

        return $chips;
    }

    /** Map pins for the rooms that have one: rooms without a pin are simply left out. */
    public static function mapPins(Collection $rooms): Collection
    {
        return $rooms->filter(fn (Room $r) => $r->hasLocation())
            ->map(fn (Room $r) => [
                'id' => $r->room_id,
                'name' => $r->room_name,
                'price' => $r->price,
                'price_label' => $r->priceLabel(),
                'rent' => $r->perPersonRent(),
                'area' => self::areaOf($r->address),
                'image' => $r->cover_image ? RoomPhotos::url($r->cover_image) : null,
                'lat' => $r->latitude,
                'lng' => $r->longitude,
            ])
            ->values();
    }
}
