<template>
    <div class="shop">
        <div class="tabs">
            <button :class="{ on: tab === 'upgrades' }" type="button" @click="$emit('tab', 'upgrades')">Улучшения</button>
            <button :class="{ on: tab === 'cash' }" type="button" @click="$emit('tab', 'cash')">Монетизация</button>
            <button :class="{ on: tab === 'log' }" type="button" @click="$emit('tab', 'log')">Дневник</button>
        </div>

        <div v-if="tab === 'upgrades'" class="list">
            <article
                v-for="item in shop"
                :key="item.id"
                class="card"
                :class="{ locked: !item.unlocked, maxed: item.maxed, mechanic: item.mechanic }"
            >
                <div class="card-top">
                    <strong>{{ item.name }}</strong>
                    <span v-if="item.mechanic" class="tag">механика</span>
                    <span class="lvl">{{ item.level }}/{{ item.max }}</span>
                </div>
                <p>{{ item.unlocked ? item.blurb : 'Ещё не открыто — копи файн и жги этапы.' }}</p>
                <button
                    type="button"
                    class="buy"
                    :disabled="!item.affordable"
                    @click="$emit('buy', item.id)"
                >
                    <template v-if="item.maxed">Максимум. Это fine.</template>
                    <template v-else-if="!item.unlocked">Закрыто</template>
                    <template v-else>Купить · {{ format(item.cost) }}</template>
                </button>
            </article>
        </div>

        <div v-else-if="tab === 'cash'" class="list">
            <p class="cash-note">Оплата не списывается. Это витрина того, как кликер зарабатывает деньги.</p>
            <article v-for="pack in monetization" :key="pack.sku" class="card cash">
                <div class="card-top">
                    <strong>{{ pack.name }}</strong>
                    <span class="price">{{ pack.price_label }}</span>
                </div>
                <p>{{ pack.blurb }}</p>
                <p v-if="pack.sku === 'insurance'" class="meta">Зарядов страховки: {{ pack.meta.insurance }}</p>
                <button
                    type="button"
                    class="buy gold"
                    :disabled="pack.owned"
                    @click="$emit('iap', pack.sku)"
                >
                    {{ pack.owned ? 'Уже куплено' : pack.type === 'ad' ? 'Смотреть рекламу' : 'Купить (демо)' }}
                </button>
            </article>
        </div>

        <div v-else class="list log">
            <p v-if="!log.length" class="cash-note">Пока тихо. Кликай — события сами найдут тебя.</p>
            <article v-for="(row, i) in log" :key="i" class="card log-card" :class="row.kind">
                <div class="card-top">
                    <strong>{{ row.title }}</strong>
                    <span class="tag">{{ kindLabel(row.kind) }}</span>
                </div>
            </article>
        </div>
    </div>
</template>

<script setup>
defineProps({
    tab: { type: String, required: true },
    shop: { type: Array, default: () => [] },
    monetization: { type: Array, default: () => [] },
    log: { type: Array, default: () => [] },
    format: { type: Function, required: true },
});

defineEmits(['tab', 'buy', 'iap']);

function kindLabel(kind) {
    return { bonus: 'бонус', crisis: 'кризис', penalty: 'штраф', twist: 'поворот' }[kind] ?? kind;
}
</script>
