<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamePurchase extends Model
{
    protected $fillable = [
        'game_id',
        'sku',
        'kind',
        'level',
        'fine_spent',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'fine_spent' => 'float',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
