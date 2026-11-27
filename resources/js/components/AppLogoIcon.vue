<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- Friendly Friend: an opening quote mark with a highlighter swipe. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 40"
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <rect
            x="5"
            y="13"
            width="30"
            height="20"
            rx="4"
            transform="rotate(-6 20 23)"
            fill="var(--highlight)"
        />
        <path
            fill="#000"
            d="M11 30c-2.2 0-3.6-1.7-3.6-4.3 0-5 3.3-9.4 8-11.2l1 1.8c-2.6 1.4-4.2 3.5-4.5 5.6.4-.2.9-.3 1.4-.3 2.3 0 4 1.6 4 4s-1.9 4.4-4.3 4.4Zm13 0c-2.2 0-3.6-1.7-3.6-4.3 0-5 3.3-9.4 8-11.2l1 1.8c-2.6 1.4-4.2 3.5-4.5 5.6.4-.2.9-.3 1.4-.3 2.3 0 4 1.6 4 4S26.4 30 24 30Z"
        />
    </svg>
</template>
