<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameLoopTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_sips_award_fine_and_persist_after_reload(): void
    {
        $uuid = $this->getJson('/api/game')->assertOk()->assertJsonPath('player.fine', 0)->json('player.uuid');

        $sip = $this->withHeader('X-Fine-Save', $uuid)
            ->postJson('/api/game/sip', ['count' => 12, 'combo' => 1])
            ->assertOk();

        $fine = $sip->json('state.player.fine');
        $this->assertGreaterThan(10, $fine);

        $this->withHeader('X-Fine-Save', $uuid)
            ->getJson('/api/game')
            ->assertOk()
            ->assertJsonPath('player.fine', $fine);

        $this->assertSame(1, Game::count());
        $this->assertSame(12, Game::query()->where('uuid', $uuid)->first()->sips_total);
    }

    public function test_mug_upgrade_increases_sip_power(): void
    {
        $uuid = $this->getJson('/api/game')->json('player.uuid');
        $this->withHeader('X-Fine-Save', $uuid)
            ->postJson('/api/game/sip', ['count' => 20, 'combo' => 1])
            ->assertOk();

        $before = $this->withHeader('X-Fine-Save', $uuid)->getJson('/api/game')->json('player.sip_power');
        $this->withHeader('X-Fine-Save', $uuid)->postJson('/api/game/upgrade', ['id' => 'mug'])->assertOk();
        $after = $this->withHeader('X-Fine-Save', $uuid)->getJson('/api/game')->json('player.sip_power');

        $this->assertGreaterThan($before, $after);
        $this->assertDatabaseHas('game_purchases', [
            'sku' => 'mug',
            'kind' => 'upgrade',
        ]);
    }

    public function test_coffee_machine_unlocks_idle_income(): void
    {
        $uuid = $this->getJson('/api/game')->json('player.uuid');

        $this->withHeader('X-Fine-Save', $uuid)
            ->postJson('/api/game/monetize', ['sku' => 'starter'])
            ->assertOk();

        $this->withHeader('X-Fine-Save', $uuid)
            ->postJson('/api/game/upgrade', ['id' => 'machine'])
            ->assertOk();

        $state = $this->withHeader('X-Fine-Save', $uuid)->getJson('/api/game')->json();
        $this->assertTrue($state['player']['idle_unlocked']);
        $this->assertGreaterThan(0, $state['player']['idle_per_sec']);
    }

    public function test_demo_starter_pack_grants_fine_without_real_payment(): void
    {
        $uuid = $this->getJson('/api/game')->json('player.uuid');

        $this->withHeader('X-Fine-Save', $uuid)
            ->postJson('/api/game/monetize', ['sku' => 'starter'])
            ->assertOk()
            ->assertJsonPath('player.fine', 150)
            ->assertJsonFragment(['golden_mug']);

        $this->assertDatabaseHas('game_purchases', [
            'sku' => 'starter',
            'kind' => 'monetization',
            'note' => 'demo_iap',
        ]);
    }
}
