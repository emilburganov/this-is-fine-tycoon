<template>
    <svg class="dog-svg" viewBox="0 0 280 240" role="img" aria-label="Собака пьёт чай на горящей кухне">
        <ellipse class="dog-shadow" cx="140" cy="214" rx="72" ry="10" />

        <g class="dog-body" :class="{ sipping }">
            <ellipse cx="142" cy="168" rx="58" ry="42" fill="#f2c14e" stroke="#2a150c" stroke-width="4" />
            <ellipse cx="142" cy="176" rx="36" ry="22" fill="#e8a93a" />

            <g class="dog-head">
                <ellipse cx="96" cy="118" rx="18" ry="28" fill="#c47a2c" stroke="#2a150c" stroke-width="3.5" />
                <ellipse cx="188" cy="118" rx="18" ry="28" fill="#c47a2c" stroke="#2a150c" stroke-width="3.5" />
                <ellipse cx="142" cy="118" rx="62" ry="52" fill="#f4c85a" stroke="#2a150c" stroke-width="4" />
                <ellipse cx="118" cy="128" rx="18" ry="12" fill="#ffe7a3" opacity=".7" />

                <g v-if="mood === 'panic'">
                    <ellipse cx="122" cy="112" rx="9" ry="11" fill="#2a150c" />
                    <ellipse cx="162" cy="112" rx="9" ry="11" fill="#2a150c" />
                    <circle cx="124" cy="109" r="3" fill="#fff" />
                    <circle cx="164" cy="109" r="3" fill="#fff" />
                </g>
                <g v-else>
                    <path d="M112 112 q10 8 20 0" fill="none" stroke="#2a150c" stroke-width="4" stroke-linecap="round" />
                    <path d="M152 112 q10 8 20 0" fill="none" stroke="#2a150c" stroke-width="4" stroke-linecap="round" />
                </g>

                <ellipse cx="142" cy="132" rx="10" ry="7" fill="#2a150c" />
                <path
                    :d="mood === 'panic' ? 'M128 148 q14 2 28 0' : 'M128 146 q14 10 28 0'"
                    fill="none"
                    stroke="#2a150c"
                    stroke-width="3.5"
                    stroke-linecap="round"
                />

                <g v-if="sweat">
                    <path d="M92 96 l4 10" stroke="#9fd7ff" stroke-width="3" stroke-linecap="round" />
                    <path d="M196 92 l-3 9" stroke="#9fd7ff" stroke-width="3" stroke-linecap="round" />
                </g>
            </g>

            <g v-if="hasPass" class="hat">
                <ellipse cx="142" cy="72" rx="38" ry="10" fill="#f7f1e4" stroke="#2a150c" stroke-width="3" />
                <path d="M118 72 v-18 a24 14 0 0 1 48 0 v18" fill="#f7f1e4" stroke="#2a150c" stroke-width="3" />
                <rect x="118" y="68" width="48" height="6" fill="#2a150c" />
            </g>

            <g class="mug" :class="{ gold: goldMug }">
                <rect x="188" y="148" width="46" height="38" rx="8" :fill="goldMug ? '#e8c547' : '#f4efe4'" stroke="#2a150c" stroke-width="3.5" />
                <path d="M234 156 h10 a10 12 0 0 1 0 24 h-10" fill="none" stroke="#2a150c" stroke-width="3.5" />
                <rect x="194" y="154" width="34" height="10" rx="4" :fill="goldMug ? '#8b4a16' : '#5b3116'" />
                <path class="steam" d="M202 146 c0 -8 8 -8 8 -16" fill="none" stroke="#f7f1e4" stroke-width="2.5" stroke-linecap="round" />
                <path class="steam delay" d="M216 146 c0 -8 8 -8 8 -16" fill="none" stroke="#f7f1e4" stroke-width="2.5" stroke-linecap="round" />
            </g>

            <path d="M188 176 q18 8 28 2" fill="none" stroke="#2a150c" stroke-width="4" stroke-linecap="round" />
        </g>
    </svg>
</template>

<script setup>
defineProps({
    sipping: { type: Boolean, default: false },
    goldMug: { type: Boolean, default: false },
    hasPass: { type: Boolean, default: false },
    sweat: { type: Boolean, default: false },
    mood: { type: String, default: 'fine' },
});
</script>
