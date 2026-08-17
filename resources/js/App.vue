<template>
    <div v-if="error && !state" class="boot-error">
        <p>{{ error }}</p>
        <button type="button" @click="boot">Ещё раз. Это fine.</button>
    </div>

    <div v-else-if="!state" class="boot">Завариваем кофе…</div>

    <div v-else class="game" :class="[state.player.stage, { unlit: !state.player.fire_lit, panic: panicOn }]">
        <header class="hud">
            <div>
                <p class="kicker">This is Fine Tycoon</p>
                <h1>{{ state.stage.title }}</h1>
                <p class="caption">{{ state.stage.caption }}</p>
            </div>
            <div class="meters">
                <div class="fine-box">
                    <span>Файн</span>
                    <strong>{{ format(displayFine) }}</strong>
                    <small>{{ perSecLabel }}</small>
                </div>
                <div class="heat">
                    <span>Жар {{ Math.round(state.player.heat) }}</span>
                    <div class="bar"><i :style="{ width: state.player.heat + '%' }" /></div>
                    <small>чем жарче — тем больше файна</small>
                </div>
            </div>
        </header>

        <div v-if="state.next_stage" class="stage-bar">
            <span>{{ state.next_stage.title }}</span>
            <div class="bar"><i :style="{ width: state.next_stage.progress * 100 + '%' }" /></div>
            <em>ещё {{ format(state.next_stage.remaining) }}</em>
        </div>
        <div v-else class="stage-bar done">Вселенная в огне. Дальше только fine.</div>

        <div class="layout">
            <section class="scene" @pointerdown.prevent="sip">
                <div class="sky" />
                <div class="props" aria-hidden="true" />
                <div class="fire" :class="{ out: !state.player.fire_lit }">
                    <span v-for="n in 7" :key="n" :style="{ '--i': n }" />
                </div>

                <div class="speech">
                    {{ speech }}
                </div>

                <FineDog
                    :sipping="sipping"
                    :gold-mug="cosmetics.includes('golden_mug')"
                    :has-pass="cosmetics.includes('pass_badge')"
                    :sweat="state.player.heat > 62"
                    :mood="panicOn ? 'panic' : 'fine'"
                />

                <div class="floaters">
                    <span v-for="f in floaters" :key="f.id" :class="{ crit: f.crit }" :style="f.style">
                        {{ f.text }}
                    </span>
                </div>

                <p class="hint">Кликай по собаке / кружке — пей кофе, копи файн</p>

                <div class="combo" v-if="state.player.combo_unlocked">
                    комбо ×{{ combo }}
                    <div class="bar"><i :style="{ width: comboPulse + '%' }" /></div>
                </div>

                <div class="buffs">
                    <span v-if="state.player.buffs.viral_ms">вирус ×3</span>
                    <span v-if="state.player.buffs.livestream_ms">эфир ×3</span>
                    <span v-if="state.player.buffs.ad_ms">реклама ×2</span>
                    <span v-if="panicOn" class="bad">не fine ×0.5</span>
                    <span v-if="!state.player.fire_lit" class="bad">без огня</span>
                    <span v-if="state.player.fine_pass">Fine Pass</span>
                </div>

                <div class="actions" @pointerdown.stop>
                    <button
                        v-if="state.player.upgrades.stream"
                        type="button"
                        class="live"
                        :disabled="liveDisabled"
                        @click="goLive"
                    >
                        {{ liveLabel }}
                    </button>
                    <button v-if="!state.player.fire_lit" type="button" class="live" @click="relight">
                        Поджечь заново
                    </button>
                </div>
            </section>

            <aside class="side">
                <ShopPanel
                    :tab="tab"
                    :shop="state.shop"
                    :monetization="state.monetization"
                    :log="state.log"
                    :format="format"
                    @tab="tab = $event"
                    @buy="buy"
                    @iap="iap"
                />
                <button type="button" class="reset" @click="reset">Новая кухня</button>
            </aside>
        </div>

        <EventModal
            :event="state.active_event"
            :format="format"
            @dismiss="dismiss"
            @relight="relight"
            @copium="copium"
        />

        <div v-if="adOpen" class="overlay ad">
            <div class="modal">
                <p class="kind">Реклама партнёра · демо</p>
                <h2>Бургер тоже в огне</h2>
                <p class="body">Представьте здесь ролик на 4 секунды. Игрок терпит — игра платит.</p>
                <div class="ad-bar"><i :style="{ width: adProgress + '%' }" /></div>
                <p v-if="adProgress < 100">Ещё {{ Math.ceil((100 - adProgress) / 25) }} сек.</p>
                <button v-else type="button" class="cta" @click="finishAd">Забрать ×2 на минуту</button>
            </div>
        </div>

        <p v-if="toast" class="toast">{{ toast }}</p>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { api } from './api';
import FineDog from './components/FineDog.vue';
import ShopPanel from './components/ShopPanel.vue';
import EventModal from './components/EventModal.vue';

const state = ref(null);
const error = ref('');
const tab = ref('upgrades');
const combo = ref(1);
const pending = ref(0);
const sipping = ref(false);
const floaters = ref([]);
const toast = ref('');
const displayFine = ref(0);
const adOpen = ref(false);
const adProgress = ref(0);
const comboPulse = ref(0);

let flushTimer;
let syncTimer;
let idleTimer;
let comboTimer;
let adTimer;
let floaterId = 0;
let lastSipAt = 0;
let toastTimer;

const cosmetics = computed(() => state.value?.player.cosmetics ?? []);
const panicOn = computed(() => (state.value?.player.buffs.panic_ms ?? 0) > 0);

const perSecLabel = computed(() => {
    const idle = state.value?.player.idle_per_sec ?? 0;
    return idle > 0 ? `+${format(idle)}/сек` : 'кликай, чтобы заработать';
});

const speech = computed(() => {
    if (!state.value.player.fire_lit) return 'Это… мокро. Но тоже fine.';
    if (panicOn.value) return 'Ладно, может, не fine.';
    if (state.value.player.buffs.livestream_ms) return 'Всем привет, у нас небольшой уют.';
    if (state.value.player.heat > 75) return 'Жарковато. Кофе как раз.';
    return 'This is fine.';
});

const liveDisabled = computed(() => {
    const b = state.value.player.buffs;
    return b.livestream_ms > 0 || b.livestream_cooldown_ms > 0;
});

const liveLabel = computed(() => {
    const b = state.value.player.buffs;
    if (b.livestream_ms > 0) return 'В эфире…';
    if (b.livestream_cooldown_ms > 0) return `Кулдаун ${Math.ceil(b.livestream_cooldown_ms / 1000)}с`;
    return 'В эфир ×3';
});

onMounted(boot);
onBeforeUnmount(() => {
    clearInterval(flushTimer);
    clearInterval(syncTimer);
    clearInterval(idleTimer);
    clearInterval(adTimer);
    clearTimeout(comboTimer);
    clearTimeout(toastTimer);
});

async function boot() {
    try {
        error.value = '';
        apply(await api.load());
        flushTimer = setInterval(flush, 380);
        syncTimer = setInterval(sync, 2800);
        idleTimer = setInterval(localIdle, 250);
    } catch (e) {
        error.value = e.message;
    }
}

function apply(payload) {
    const next = payload.state ?? payload;
    state.value = next;
    displayFine.value = next.player.fine;
}

function format(n) {
    const v = Number(n) || 0;
    if (v >= 1_000_000) return `${(v / 1_000_000).toFixed(2)}M`;
    if (v >= 10_000) return `${(v / 1000).toFixed(1)}K`;
    return v.toFixed(v >= 100 ? 0 : 1);
}

function sip(event) {
    if (!state.value) return;

    const now = Date.now();
    const cap = state.value.player.combo_cap ?? 1;
    if (state.value.player.combo_unlocked && now - lastSipAt < 1200) {
        combo.value = Math.min(cap, combo.value + 1);
    } else {
        combo.value = 1;
    }
    lastSipAt = now;
    comboPulse.value = 100;
    clearTimeout(comboTimer);
    comboTimer = setTimeout(() => {
        combo.value = 1;
        comboPulse.value = 0;
    }, 1200);

    pending.value += 1;
    sipping.value = true;
    setTimeout(() => {
        sipping.value = false;
    }, 140);

    const preview = state.value.player.sip_power * combo.value * state.value.player.output_multiplier;
    displayFine.value += preview;
    spawnFloater(event, preview, false);
}

function spawnFloater(event, amount, crit) {
    const id = ++floaterId;
    const x = event?.clientX ? event.clientX : window.innerWidth / 2;
    const y = event?.clientY ? event.clientY : window.innerHeight / 3;
    floaters.value.push({
        id,
        crit,
        text: `+${format(amount)}`,
        style: {
            left: `${x}px`,
            top: `${y}px`,
        },
    });
    setTimeout(() => {
        floaters.value = floaters.value.filter((f) => f.id !== id);
    }, 700);
}

async function flush() {
    if (!pending.value) {
        comboPulse.value = Math.max(0, comboPulse.value - 18);
        return;
    }

    const count = pending.value;
    pending.value = 0;
    try {
        const result = await api.sip(count, combo.value);
        apply(result);
        if (result.crits > 0) {
            spawnFloater(null, result.gained, true);
        }
    } catch (e) {
        say(e.message);
    }
}

async function sync() {
    if (pending.value) return;
    try {
        apply(await api.load());
    } catch {
        /* offline tick подождёт */
    }
}

function localIdle() {
    if (!state.value || pending.value) return;
    const idle = state.value.player.idle_per_sec * 0.25 * (state.value.player.fire_lit ? 1 : 0);
    if (idle > 0) {
        displayFine.value += idle;
    }
    decayBuffs(250);
}

function decayBuffs(ms) {
    const b = state.value.player.buffs;
    for (const key of Object.keys(b)) {
        b[key] = Math.max(0, b[key] - ms);
    }
}

async function buy(id) {
    await flush();
    try {
        apply(await api.upgrade(id));
        say('Куплено. Комната всё ещё в огне.');
    } catch (e) {
        say(e.message);
    }
}

async function iap(sku) {
    if (sku === 'ad_boost') {
        startAd();
        return;
    }
    try {
        apply(await api.monetize(sku));
        say('Демо-покупка прошла. Деньги не списывались.');
    } catch (e) {
        say(e.message);
    }
}

function startAd() {
    adOpen.value = true;
    adProgress.value = 0;
    clearInterval(adTimer);
    adTimer = setInterval(() => {
        adProgress.value += 2.5;
        if (adProgress.value >= 100) {
            adProgress.value = 100;
            clearInterval(adTimer);
        }
    }, 100);
}

async function finishAd() {
    adOpen.value = false;
    try {
        apply(await api.monetize('ad_boost'));
        say('×2 на минуту. Спасибо, что посмотрели «рекламу».');
    } catch (e) {
        say(e.message);
    }
}

async function goLive() {
    try {
        apply(await api.action('go_live'));
        say('Камера смотрит в огонь. Зрители в восторге.');
    } catch (e) {
        say(e.message);
    }
}

async function relight() {
    apply(await api.action('relight'));
    say('Спичка. Мем спасён.');
}

async function dismiss() {
    apply(await api.action('dismiss'));
}

async function copium() {
    apply(await api.action('copium'));
    say('Копиум подействовал. Снова fine.');
}

async function reset() {
    if (!confirm('Начать с чистой кухни? Прогресс в этой вкладке сбросится.')) return;
    apply(await api.reset());
    combo.value = 1;
    pending.value = 0;
    say('Новая кухня. Старый огонь тоже был fine.');
}

function say(text) {
    toast.value = text;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toast.value = '';
    }, 2400);
}
</script>
