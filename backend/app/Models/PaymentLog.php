<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    protected $fillable = [
        'ma_dh',
        'provider',
        'event_type',
        'raw_payload',
        'verified',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'verified' => 'boolean',
    ];

    public function order()
    {
        return $this->belongsTo(DonHang::class, 'ma_dh', 'ma_dh');
    }
}
