<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = [
        'uuid',
        'fine',
        'lifetime_fine',
        'sips_total',
        'heat',
        'stage',
        'upgrades',
        'cosmetics',
        'combo_unlocked',
        'crit_unlocked',
        'idle_unlocked',
        'stream_unlocked',
        'fire_lit',
        'fine_pass',
        'insurance_charges',
        'viral_until',
        'panic_until',
        'livestream_until',
        'livestream_cooldown_until',
        'ad_mult_until',
        'active_event',
        'active_event_payload',
        'last_event_at',
        'sips_since_event',
        'last_tick_at',
    ];

    protected $attributes = [
        'fine' => 0,
        'lifetime_fine' => 0,
        'sips_total' => 0,
        'heat' => 18,
        'stage' => 'kitchen',
        'combo_unlocked' => false,
        'crit_unlocked' => false,
        'idle_unlocked' => false,
        'stream_unlocked' => false,
        'fire_lit' => true,
        'fine_pass' => false,
        'insurance_charges' => 0,
        'sips_since_event' => 0,
    ];

    protected function casts(): array
    {
        return [
            'fine' => 'float',
            'lifetime_fine' => 'float',
            'heat' => 'float',
            'upgrades' => 'array',
            'cosmetics' => 'array',
            'active_event_payload' => 'array',
            'combo_unlocked' => 'boolean',
            'crit_unlocked' => 'boolean',
            'idle_unlocked' => 'boolean',
            'stream_unlocked' => 'boolean',
            'fire_lit' => 'boolean',
            'fine_pass' => 'boolean',
            'viral_until' => 'datetime',
            'panic_until' => 'datetime',
            'livestream_until' => 'datetime',
            'livestream_cooldown_until' => 'datetime',
            'ad_mult_until' => 'datetime',
            'last_event_at' => 'datetime',
            'last_tick_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(GamePurchase::class);
    }

    public function upgradeLevel(string $id): int
    {
        return (int) (($this->upgrades ?? [])[$id] ?? 0);
    }
}
