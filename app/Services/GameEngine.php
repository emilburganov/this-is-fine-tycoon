<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GamePurchase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GameEngine
{
    public function createSave(): Game
    {
        return Game::create([
            'uuid' => (string) Str::uuid(),
            'fine' => 0,
            'lifetime_fine' => 0,
            'sips_total' => 0,
            'heat' => 18,
            'stage' => 'kitchen',
            'upgrades' => [],
            'cosmetics' => [],
            'combo_unlocked' => false,
            'crit_unlocked' => false,
            'idle_unlocked' => false,
            'stream_unlocked' => false,
            'fire_lit' => true,
            'fine_pass' => false,
            'insurance_charges' => 0,
            'last_tick_at' => now(),
        ]);
    }

    public function snapshot(Game $game): array
    {
        $this->applyTick($game);
        $game->save();

        return $this->present($game);
    }

    public function sip(Game $game, int $count, int $combo): array
    {
        $this->applyTick($game);

        $max = (int) config('game.sip.max_per_request');
        $count = max(1, min($max, $count));
        $comboCap = $game->combo_unlocked ? (int) config('game.sip.combo_cap') : 1;
        $combo = max(1, min($comboCap, $combo));

        $gained = 0.0;
        $crits = 0;

        for ($i = 0; $i < $count; $i++) {
            $amount = $this->sipPower($game) * $combo * $this->outputMultiplier($game);

            if ($this->rollCrit($game)) {
                $amount *= (float) config('game.sip.crit_multiplier');
                $crits++;
            }

            $gained += $amount;
            $game->heat = min(
                (float) config('game.heat.max'),
                $game->heat + (float) config('game.sip.heat_per_sip')
            );
        }

        $this->addFine($game, $gained);
        $game->sips_total += $count;
        $game->sips_since_event += $count;

        $event = $this->maybeTriggerEvent($game);
        $this->maybeAdvanceStage($game);
        $game->last_tick_at = now();
        $game->save();

        return [
            'gained' => round($gained, 2),
            'crits' => $crits,
            'combo' => $combo,
            'state' => $this->present($game),
        ];
    }

    public function buyUpgrade(Game $game, string $id): array
    {
        $this->applyTick($game);

        $catalog = config('game.upgrades.'.$id);
        if (! is_array($catalog)) {
            abort(404, 'Нет такого улучшения');
        }

        $level = $game->upgradeLevel($id);
        if ($level >= (int) $catalog['max']) {
            abort(422, 'Уже максимум');
        }

        if (! $this->upgradeUnlocked($game, $id, $catalog)) {
            abort(422, 'Ещё не открыто');
        }

        $cost = $this->upgradeCost($catalog, $level);
        if ($game->fine + 0.0001 < $cost) {
            abort(422, 'Не хватает файна');
        }

        $game->fine = round($game->fine - $cost, 2);
        $upgrades = $game->upgrades ?? [];
        $upgrades[$id] = $level + 1;
        $game->upgrades = $upgrades;

        match ($id) {
            'machine' => $game->idle_unlocked = true,
            'nervous' => $game->combo_unlocked = true,
            'espresso' => $game->crit_unlocked = true,
            'stream' => $game->stream_unlocked = true,
            default => null,
        };

        GamePurchase::create([
            'game_id' => $game->id,
            'sku' => $id,
            'kind' => 'upgrade',
            'level' => $level + 1,
            'fine_spent' => $cost,
        ]);

        $this->maybeAdvanceStage($game);
        $game->last_tick_at = now();
        $game->save();

        return $this->present($game);
    }

    public function goLive(Game $game): array
    {
        $this->applyTick($game);

        if (! $game->stream_unlocked) {
            abort(422, 'Сначала купи стрим');
        }

        if ($game->livestream_until && $game->livestream_until->isFuture()) {
            abort(422, 'Уже в эфире');
        }

        if ($game->livestream_cooldown_until && $game->livestream_cooldown_until->isFuture()) {
            abort(422, 'Кулдаун стрима');
        }

        $seconds = (int) config('game.buffs.livestream_seconds');
        $cooldown = (int) config('game.buffs.livestream_cooldown');
        $game->livestream_until = now()->addSeconds($seconds);
        $game->livestream_cooldown_until = now()->addSeconds($seconds + $cooldown);
        $game->last_tick_at = now();
        $game->save();

        return $this->present($game);
    }

    public function relight(Game $game): array
    {
        $this->applyTick($game);
        $game->fire_lit = true;
        $game->heat = max($game->heat, 24);
        $game->active_event = null;
        $game->active_event_payload = null;
        $game->last_tick_at = now();
        $game->save();

        return $this->present($game);
    }

    public function dismissEvent(Game $game, ?string $choice = null): array
    {
        $this->applyTick($game);

        $key = $game->active_event;
        if ($key === 'rain' && $choice === 'relight') {
            return $this->relight($game);
        }

        if ($key === 'panic' && $choice === 'copium') {
            $fee = max(5, round($game->fine * 0.05, 2));
            $game->fine = max(0, round($game->fine - $fee, 2));
            $game->panic_until = null;
        }

        $game->active_event = null;
        $game->active_event_payload = null;
        $game->last_tick_at = now();
        $game->save();

        return $this->present($game);
    }

    public function buyMonetization(Game $game, string $sku): array
    {
        $this->applyTick($game);

        $item = config('game.monetization.'.$sku);
        if (! is_array($item)) {
            abort(404, 'Нет такого пакета');
        }

        match ($sku) {
            'fine_pass' => $this->grantFinePass($game),
            'insurance' => $game->insurance_charges = min(9, $game->insurance_charges + 3),
            'starter' => $this->grantStarter($game),
            'ad_boost' => $game->ad_mult_until = now()->addSeconds((int) config('game.buffs.ad_seconds')),
            default => abort(404, 'Нет такого пакета'),
        };

        GamePurchase::create([
            'game_id' => $game->id,
            'sku' => $sku,
            'kind' => 'monetization',
            'level' => 1,
            'fine_spent' => 0,
            'note' => 'demo_iap',
        ]);

        $game->last_tick_at = now();
        $game->save();

        return $this->present($game);
    }

    public function present(Game $game): array
    {
        $stage = $this->currentStage($game);
        $next = $this->nextStage($game);

        return [
            'player' => [
                'uuid' => $game->uuid,
                'fine' => round($game->fine, 2),
                'lifetime_fine' => round($game->lifetime_fine, 2),
                'sips_total' => $game->sips_total,
                'heat' => round($game->heat, 1),
                'stage' => $game->stage,
                'sip_power' => round($this->sipPower($game), 2),
                'idle_per_sec' => round($this->idlePerSec($game), 2),
                'combo_unlocked' => $game->combo_unlocked,
                'idle_unlocked' => $game->idle_unlocked,
                'stream_unlocked' => $game->stream_unlocked,
                'combo_cap' => $game->combo_unlocked ? (int) config('game.sip.combo_cap') : 1,
                'crit_chance' => $this->critChance($game),
                'fire_lit' => $game->fire_lit,
                'fine_pass' => $game->fine_pass,
                'insurance_charges' => $game->insurance_charges,
                'cosmetics' => $game->cosmetics ?? [],
                'upgrades' => $game->upgrades ?? [],
                'buffs' => $this->buffs($game),
                'output_multiplier' => round($this->outputMultiplier($game), 2),
            ],
            'stage' => $stage,
            'next_stage' => $next,
            'shop' => $this->shop($game),
            'monetization' => $this->monetization($game),
            'active_event' => $this->activeEventView($game),
            'log' => $game->events()->latest()->limit(8)->get()->map(fn (GameEvent $event) => [
                'key' => $event->key,
                'kind' => $event->kind,
                'title' => config('game.events.'.$event->key.'.title', $event->key),
                'at' => $event->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    public function applyTick(Game $game): void
    {
        $from = $game->last_tick_at ?? $game->updated_at ?? now();
        $seconds = min(
            (int) config('game.idle.max_offline_seconds'),
            max(0, (int) now()->diffInSeconds($from))
        );

        if ($seconds > 0) {
            $idle = $this->idlePerSec($game) * $seconds * $this->outputMultiplier($game, withTimed: false);
            if ($game->fire_lit && $idle > 0) {
                $this->addFine($game, $idle);
            }

            $game->heat = max(
                0,
                $game->heat - $seconds * (float) config('game.heat.decay_per_sec')
            );
        }

        $this->maybeAdvanceStage($game);
        $game->last_tick_at = now();
    }

    private function addFine(Game $game, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $game->fine = round($game->fine + $amount, 2);
        $game->lifetime_fine = round($game->lifetime_fine + $amount, 2);
    }

    private function sipPower(Game $game): float
    {
        return (float) config('game.sip.base_power') + $game->upgradeLevel('mug');
    }

    private function idlePerSec(Game $game): float
    {
        if (! $game->fire_lit) {
            return 0;
        }

        return $game->upgradeLevel('machine') * (float) config('game.idle.machine_per_level')
            + $game->upgradeLevel('merch') * (float) config('game.idle.merch_per_level');
    }

    private function outputMultiplier(Game $game, bool $withTimed = true): float
    {
        $mult = 1.0;
        $mult *= 1 + ($game->heat / (float) config('game.heat.bonus_divisor'));

        if (! $game->fire_lit) {
            $mult *= (float) config('game.sip.unlit_multiplier');
        }

        if ($game->fine_pass) {
            $mult *= (float) config('game.buffs.fine_pass_multiplier');
        }

        if ($withTimed) {
            if ($game->panic_until && $game->panic_until->isFuture()) {
                $mult *= (float) config('game.sip.panic_multiplier');
            }
            if ($game->viral_until && $game->viral_until->isFuture()) {
                $mult *= (float) config('game.buffs.viral_multiplier');
            }
            if ($game->livestream_until && $game->livestream_until->isFuture()) {
                $mult *= (float) config('game.buffs.livestream_multiplier');
            }
            if ($game->ad_mult_until && $game->ad_mult_until->isFuture()) {
                $mult *= (float) config('game.buffs.ad_multiplier');
            }
        }

        return $mult;
    }

    private function critChance(Game $game): float
    {
        if (! $game->crit_unlocked) {
            return 0;
        }

        $level = max(1, $game->upgradeLevel('espresso'));

        return min(
            0.4,
            (float) config('game.sip.crit_base_chance')
            + ($level - 1) * (float) config('game.sip.crit_chance_per_level')
        );
    }

    private function rollCrit(Game $game): bool
    {
        $chance = $this->critChance($game);
        if ($chance <= 0) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) < $chance;
    }

    private function upgradeCost(array $catalog, int $level): float
    {
        return round($catalog['base_cost'] * ($catalog['cost_mult'] ** $level), 2);
    }

    private function upgradeUnlocked(Game $game, string $id, array $catalog): bool
    {
        if ($game->lifetime_fine + 0.0001 < (float) ($catalog['unlock_lifetime'] ?? 0)) {
            return false;
        }

        $needStage = $catalog['unlock_stage'] ?? null;
        if (! $needStage) {
            return true;
        }

        $order = array_column(config('game.stages'), 'id');
        $have = array_search($game->stage, $order, true);
        $need = array_search($needStage, $order, true);

        return $have !== false && $need !== false && $have >= $need;
    }

    private function shop(Game $game): array
    {
        $items = [];

        foreach (config('game.upgrades') as $id => $catalog) {
            $level = $game->upgradeLevel($id);
            $unlocked = $this->upgradeUnlocked($game, $id, $catalog);
            $maxed = $level >= (int) $catalog['max'];
            $cost = $maxed ? null : $this->upgradeCost($catalog, $level);

            $items[] = [
                'id' => $id,
                'name' => $catalog['name'],
                'blurb' => $catalog['blurb'],
                'icon' => $catalog['icon'],
                'mechanic' => $catalog['mechanic'],
                'level' => $level,
                'max' => $catalog['max'],
                'cost' => $cost,
                'unlocked' => $unlocked,
                'maxed' => $maxed,
                'affordable' => $unlocked && ! $maxed && $game->fine + 0.0001 >= $cost,
            ];
        }

        return $items;
    }

    private function monetization(Game $game): array
    {
        $owned = [
            'fine_pass' => $game->fine_pass,
            'starter' => in_array('golden_mug', $game->cosmetics ?? [], true),
        ];

        return collect(config('game.monetization'))->map(function (array $item, string $sku) use ($game, $owned) {
            return [
                'sku' => $sku,
                'name' => $item['name'],
                'price_label' => $item['price_label'],
                'blurb' => $item['blurb'],
                'type' => $item['type'],
                'owned' => (bool) ($owned[$sku] ?? false),
                'meta' => [
                    'insurance' => $game->insurance_charges,
                ],
            ];
        })->values()->all();
    }

    private function currentStage(Game $game): array
    {
        foreach (array_reverse(config('game.stages')) as $stage) {
            if ($game->stage === $stage['id']) {
                return $stage;
            }
        }

        return config('game.stages')[0];
    }

    private function nextStage(Game $game): ?array
    {
        $stages = config('game.stages');
        foreach ($stages as $index => $stage) {
            if ($stage['id'] === $game->stage) {
                $next = $stages[$index + 1] ?? null;
                if (! $next) {
                    return null;
                }

                return [
                    ...$next,
                    'progress' => min(1, $game->lifetime_fine / max(1, $next['threshold'])),
                    'remaining' => max(0, round($next['threshold'] - $game->lifetime_fine, 2)),
                ];
            }
        }

        return null;
    }

    private function maybeAdvanceStage(Game $game): void
    {
        foreach (config('game.stages') as $stage) {
            if ($game->lifetime_fine + 0.0001 >= $stage['threshold']) {
                $game->stage = $stage['id'];
            }
        }
    }

    private function maybeTriggerEvent(Game $game): ?array
    {
        if ($game->active_event) {
            return $this->activeEventView($game);
        }

        $since = $game->last_event_at;
        $waited = ! $since || $since->lt(now()->subSeconds(18));
        if (! $waited || $game->sips_since_event < 18) {
            return null;
        }

        $roll = mt_rand(1, 100);
        if ($roll > 22) {
            return null;
        }

        $pool = ['viral', 'inspection', 'rain', 'panic', 'cat'];
        if (! $game->fire_lit) {
            $pool = ['viral', 'cat'];
        }

        $key = $pool[array_rand($pool)];
        $this->applyEvent($game, $key);

        return $this->activeEventView($game);
    }

    private function applyEvent(Game $game, string $key): void
    {
        $def = config('game.events.'.$key);
        $payload = ['applied' => true];

        match ($key) {
            'viral' => $game->viral_until = now()->addSeconds((int) config('game.buffs.viral_seconds')),
            'inspection' => $payload['lost'] = $this->applyInspection($game),
            'rain' => $game->fire_lit = false,
            'panic' => $game->panic_until = now()->addSeconds((int) config('game.buffs.panic_seconds')),
            'cat' => $payload['gained'] = $this->applyCat($game),
            default => null,
        };

        $game->active_event = $key;
        $game->active_event_payload = $payload;
        $game->last_event_at = now();
        $game->sips_since_event = 0;

        GameEvent::create([
            'game_id' => $game->id,
            'key' => $key,
            'kind' => $def['kind'],
            'payload' => $payload,
        ]);
    }

    private function applyInspection(Game $game): float
    {
        if ($game->insurance_charges > 0) {
            $game->insurance_charges--;

            return 0;
        }

        $rate = 0.12 - min(0.08, $game->upgradeLevel('extinguisher') * 0.015);
        $lost = round($game->fine * $rate, 2);
        $game->fine = max(0, round($game->fine - $lost, 2));

        return $lost;
    }

    private function applyCat(Game $game): float
    {
        $gift = max(12, round($this->sipPower($game) * 18, 2));
        $this->addFine($game, $gift);

        return $gift;
    }

    private function activeEventView(Game $game): ?array
    {
        if (! $game->active_event) {
            return null;
        }

        $def = config('game.events.'.$game->active_event);
        if (! $def) {
            return null;
        }

        return [
            'key' => $game->active_event,
            'kind' => $def['kind'],
            'title' => $def['title'],
            'body' => $def['body'],
            'cta' => $def['cta'],
            'payload' => $game->active_event_payload,
        ];
    }

    private function buffs(Game $game): array
    {
        $now = now();

        return [
            'viral_ms' => $this->remainingMs($game->viral_until, $now),
            'panic_ms' => $this->remainingMs($game->panic_until, $now),
            'livestream_ms' => $this->remainingMs($game->livestream_until, $now),
            'livestream_cooldown_ms' => $this->remainingMs($game->livestream_cooldown_until, $now),
            'ad_ms' => $this->remainingMs($game->ad_mult_until, $now),
        ];
    }

    private function remainingMs(?Carbon $until, Carbon $now): int
    {
        if (! $until || $until->lte($now)) {
            return 0;
        }

        return (int) $now->diffInMilliseconds($until);
    }

    private function grantFinePass(Game $game): void
    {
        $game->fine_pass = true;
        $cosmetics = $game->cosmetics ?? [];
        if (! in_array('pass_badge', $cosmetics, true)) {
            $cosmetics[] = 'pass_badge';
        }
        $game->cosmetics = $cosmetics;
    }

    private function grantStarter(Game $game): void
    {
        $this->addFine($game, 150);
        $cosmetics = $game->cosmetics ?? [];
        if (! in_array('golden_mug', $cosmetics, true)) {
            $cosmetics[] = 'golden_mug';
        }
        $game->cosmetics = $cosmetics;
    }
}
