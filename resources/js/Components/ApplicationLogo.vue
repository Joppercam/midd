<template>
    <svg
        :class="className"
        :viewBox="iconOnly ? '0 0 50 50' : '0 0 200 50'"
        xmlns="http://www.w3.org/2000/svg"
        fill="currentColor"
    >
        <g v-if="!iconOnly">
            <!-- Icono/Símbolo -->
            <g class="logo-icon">
                <!-- Gráfico de crecimiento estilizado -->
                <path
                    d="M 10 35 L 10 40 L 15 40 L 15 30 L 20 30 L 20 40 L 25 40 L 25 25 L 30 25 L 30 40 L 35 40 L 35 20 L 40 20 L 40 40 L 45 40 L 45 15"
                    stroke="currentColor"
                    stroke-width="2"
                    fill="none"
                    class="chart-bars"
                />
                <!-- Flecha de crecimiento -->
                <path
                    d="M 10 35 Q 25 25, 40 15 L 36 15 M 40 15 L 40 19"
                    stroke="currentColor"
                    stroke-width="2.5"
                    fill="none"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="growth-arrow"
                />
            </g>

            <!-- Texto del logo -->
            <g class="logo-text">
                <!-- MIDD -->
                <text
                    x="60"
                    y="30"
                    font-family="system-ui, -apple-system, sans-serif"
                    font-size="24"
                    font-weight="700"
                    fill="currentColor"
                >
                    <tspan fill="currentColor">MIDD</tspan>
                </text>
                
                <!-- Tagline opcional -->
                <text
                    v-if="showTagline"
                    x="60"
                    y="42"
                    font-family="system-ui, -apple-system, sans-serif"
                    font-size="10"
                    font-weight="400"
                    fill="currentColor"
                    opacity="0.7"
                >
                    Sistema de Gestión Empresarial
                </text>
            </g>
        </g>

        <!-- Versión alternativa: Solo icono (para móvil) -->
        <g v-else class="icon-only">
            <circle
                cx="25"
                cy="25"
                r="20"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                opacity="0.2"
            />
            <path
                d="M 15 30 L 15 32 L 18 32 L 18 28 L 21 28 L 21 32 L 24 32 L 24 24 L 27 24 L 27 32 L 30 32 L 30 20 L 33 20 L 33 32 L 35 32 L 35 18"
                stroke="currentColor"
                stroke-width="1.5"
                fill="none"
            />
            <path
                d="M 15 30 Q 25 24, 33 18 L 31 18 M 33 18 L 33 20"
                stroke="currentColor"
                stroke-width="2"
                fill="none"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        </g>
    </svg>
</template>

<script setup>
defineProps({
    className: {
        type: String,
        default: ''
    },
    showTagline: {
        type: Boolean,
        default: false
    },
    iconOnly: {
        type: Boolean,
        default: false
    }
})
</script>

<style scoped>
/* Animaciones sutiles */
.logo-icon .chart-bars {
    transition: all 0.3s ease;
}

.logo-icon .growth-arrow {
    transition: all 0.3s ease;
}

svg:hover .chart-bars {
    opacity: 0.8;
}

svg:hover .growth-arrow {
    transform: translate(-1px, -1px);
}
</style>