<template>
    <div v-if="event" class="overlay" @click.self="$emit('dismiss')">
        <div class="modal" :class="event.kind">
            <p class="kind">{{ label }}</p>
            <h2>{{ event.title }}</h2>
            <p class="body">{{ event.body }}</p>
            <p v-if="event.payload?.lost > 0" class="payload">Потеряно {{ format(event.payload.lost) }} файна.</p>
            <p v-else-if="event.payload?.lost === 0" class="payload">Страховка It's Fine+ покрыла кризис. 0 урона.</p>
            <p v-if="event.payload?.gained" class="payload">+{{ format(event.payload.gained) }} файна от кота.</p>
            <div class="row">
                <button v-if="event.key === 'rain'" type="button" class="cta" @click="$emit('relight')">
                    Поджечь заново
                </button>
                <button v-else-if="event.key === 'panic'" type="button" class="cta" @click="$emit('copium')">
                    Выпить копиум
                </button>
                <button type="button" class="cta ghost" @click="$emit('dismiss')">
                    {{ event.cta }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    event: { type: Object, default: null },
    format: { type: Function, required: true },
});

defineEmits(['dismiss', 'relight', 'copium']);

const label = computed(() => {
    return {
        bonus: 'Бонус',
        crisis: 'Кризис',
        penalty: 'Штраф',
        twist: 'Мемный поворот',
    }[props.event?.kind] ?? 'Событие';
});
</script>
