<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row of `transactions`. Its key is text like "202605-RM01", not an auto-number. */
class Transaction extends Model
{
    protected $table = 'transactions';

    protected $primaryKey = 'transaction_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'type',
        'amount',
        'date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date:Y-m-d',
        ];
    }
}