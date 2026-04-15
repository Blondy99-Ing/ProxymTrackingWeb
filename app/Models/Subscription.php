<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected $table = 'subscriptions';

    protected $fillable = [
        'vehicle_id',
        'user_id',
        'plan_id',
        'payment_id',
        'start_date',
        'end_date',
        'status',
        'auto_renew',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Voiture::class, 'vehicle_id');
    }


      public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function scopeActiveNow($query, ?CarbonInterface $at = null)
    {
        $at = $at ?: now();

        return $query
            ->where('status', 'ACTIVE')
            ->where('start_date', '<=', $at)
            ->where('end_date', '>', $at);
    }

    public function scopeInactiveNow($query, ?CarbonInterface $at = null)
    {
        $at = $at ?: now();

        return $query->where(function ($q) use ($at) {
            $q->where('status', '!=', 'ACTIVE')
              ->orWhere('start_date', '>', $at)
              ->orWhere('end_date', '<=', $at);
        });
    }

    public function getIsActiveNowAttribute(): bool
    {
        return $this->status === 'ACTIVE'
            && $this->start_date !== null
            && $this->end_date !== null
            && $this->start_date <= now()
            && $this->end_date > now();
    }
}