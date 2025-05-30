<template>
    <div class="w-full h-full">
        <canvas ref="chartRef" class="w-full h-full"></canvas>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'

const props = defineProps({
    data: Array,
})

const chartRef = ref(null)
let chart = null

onMounted(() => {
    initChart()
})

watch(() => props.data, () => {
    if (chart) {
        updateChart()
    }
}, { deep: true })

const initChart = () => {
    if (!chartRef.value || !props.data) return

    // Simple canvas-based chart implementation
    const canvas = chartRef.value
    const ctx = canvas.getContext('2d')
    
    // Set canvas size
    canvas.width = canvas.offsetWidth * 2
    canvas.height = canvas.offsetHeight * 2
    ctx.scale(2, 2)
    
    drawChart(ctx, canvas.offsetWidth, canvas.offsetHeight)
}

const drawChart = (ctx, width, height) => {
    if (!props.data || props.data.length === 0) return

    const padding = 40
    const chartWidth = width - padding * 2
    const chartHeight = height - padding * 2

    // Clear canvas
    ctx.clearRect(0, 0, width, height)

    // Get data points
    const values = props.data.map(item => item.revenue || 0)
    const maxValue = Math.max(...values, 1)
    const minValue = Math.min(...values, 0)
    const range = maxValue - minValue || 1

    // Draw grid
    ctx.strokeStyle = '#e5e7eb'
    ctx.lineWidth = 1
    
    // Horizontal grid lines
    for (let i = 0; i <= 4; i++) {
        const y = padding + (chartHeight / 4) * i
        ctx.beginPath()
        ctx.moveTo(padding, y)
        ctx.lineTo(width - padding, y)
        ctx.stroke()
    }

    // Draw line
    ctx.strokeStyle = '#ef4444'
    ctx.lineWidth = 2
    ctx.beginPath()

    props.data.forEach((item, index) => {
        const x = padding + (chartWidth / (props.data.length - 1)) * index
        const y = padding + chartHeight - ((item.revenue - minValue) / range) * chartHeight

        if (index === 0) {
            ctx.moveTo(x, y)
        } else {
            ctx.lineTo(x, y)
        }
    })

    ctx.stroke()

    // Draw points
    ctx.fillStyle = '#ef4444'
    props.data.forEach((item, index) => {
        const x = padding + (chartWidth / (props.data.length - 1)) * index
        const y = padding + chartHeight - ((item.revenue - minValue) / range) * chartHeight

        ctx.beginPath()
        ctx.arc(x, y, 3, 0, 2 * Math.PI)
        ctx.fill()
    })

    // Draw labels
    ctx.fillStyle = '#6b7280'
    ctx.font = '12px sans-serif'
    ctx.textAlign = 'center'

    props.data.forEach((item, index) => {
        const x = padding + (chartWidth / (props.data.length - 1)) * index
        
        if (index % 2 === 0) { // Show every other label to avoid crowding
            ctx.fillText(item.month, x, height - 10)
        }
    })
}

const updateChart = () => {
    if (!chartRef.value) return
    
    const canvas = chartRef.value
    const ctx = canvas.getContext('2d')
    drawChart(ctx, canvas.offsetWidth, canvas.offsetHeight)
}
</script>