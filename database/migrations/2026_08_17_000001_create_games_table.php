<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->decimal('fine', 20, 2)->default(0);
            $table->decimal('lifetime_fine', 20, 2)->default(0);
            $table->unsignedInteger('sips_total')->default(0);
            $table->decimal('heat', 8, 2)->default(12);
            $table->string('stage')->default('kitchen');
            $table->json('upgrades')->nullable();
            $table->json('cosmetics')->nullable();
            $table->boolean('combo_unlocked')->default(false);
            $table->boolean('crit_unlocked')->default(false);
            $table->boolean('idle_unlocked')->default(false);
            $table->boolean('stream_unlocked')->default(false);
            $table->boolean('fire_lit')->default(true);
            $table->boolean('fine_pass')->default(false);
            $table->unsignedTinyInteger('insurance_charges')->default(0);
            $table->timestamp('viral_until')->nullable();
            $table->timestamp('panic_until')->nullable();
            $table->timestamp('livestream_until')->nullable();
            $table->timestamp('livestream_cooldown_until')->nullable();
            $table->timestamp('ad_mult_until')->nullable();
            $table->string('active_event')->nullable();
            $table->json('active_event_payload')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->unsignedInteger('sips_since_event')->default(0);
            $table->timestamp('last_tick_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
