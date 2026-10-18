<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** One row of `payments`. */
class Payment extends Model
{
    protected $table = 'payments';

    protected $primaryKey = 'payment_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'amount',
        'date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'amount' => 'decimal:2',
            'date' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Verified payments per month of this year, e.g. [['month' => 'May', 'amount' => 15500.0], …].
     * Grouped in PHP so it works the same on PostgreSQL and SQLite. Used by the admin charts.
     */
    public static function monthlyVerifiedTotals(): Collection
    {
        $now = Carbon::now();

        return static::where('status', 'Verified')
            ->whereBetween('date', [$now->copy()->startOfYear()->toDateString(), $now->copy()->endOfYear()->toDateString()])
            ->get(['amount', 'date'])
            ->groupBy(fn ($p) => $p->date->format('n'))
            ->sortKeys()
            ->map(fn ($rows, $month) => [
                'month' => Carbon::create(null, (int) $month, 1)->format('F'),
                'amount' => round((float) $rows->sum('amount'), 2),
            ])
            ->values();
    }
}