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

onMounted(() => {
    initChart()
})

watch(() => props.data, () => {
    if (chartRef.value) {
        updateChart()
    }
}, { deep: true })

const initChart = () => {
    if (!chartRef.value || !props.data) return

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

    // Get max value for scaling
    const allValues = props.data.flatMap(item => [item.active || 0, item.trial || 0])
    const maxValue = Math.max(...allValues, 1)

    const barWidth = chartWidth / props.data.length / 2 - 5
    const barSpacing = 5

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

    // Draw bars
    props.data.forEach((item, index) => {
        const x = padding + (chartWidth / props.data.length) * index + 10
        
        // Active tenants bar (blue)
        const activeHeight = (item.active / maxValue) * chartHeight
        ctx.fillStyle = '#3b82f6'
        ctx.fillRect(x, padding + chartHeight - activeHeight, barWidth, activeHeight)
        
        // Trial tenants bar (orange)
        const trialHeight = (item.trial / maxValue) * chartHeight
        ctx.fillStyle = '#f59e0b'
        ctx.fillRect(x + barWidth + barSpacing, padding + chartHeight - trialHeight, barWidth, trialHeight)
    })

    // Draw labels
    ctx.fillStyle = '#6b7280'
    ctx.font = '12px sans-serif'
    ctx.textAlign = 'center'

    props.data.forEach((item, index) => {
        const x = padding + (chartWidth / props.data.length) * index + (chartWidth / props.data.length) / 2
        
        if (index % 2 === 0) { // Show every other label
            ctx.fillText(item.month, x, height - 10)
        }
    })

    // Draw legend
    ctx.fillStyle = '#3b82f6'
    ctx.fillRect(width - 120, 20, 15, 15)
    ctx.fillStyle = '#374151'
    ctx.font = '12px sans-serif'
    ctx.textAlign = 'left'
    ctx.fillText('Active', width - 100, 32)

    ctx.fillStyle = '#f59e0b'
    ctx.fillRect(width - 120, 40, 15, 15)
    ctx.fillStyle = '#374151'
    ctx.fillText('Trial', width - 100, 52)
}

const updateChart = () => {
    if (!chartRef.value) return
    
    const canvas = chartRef.value
    const ctx = canvas.getContext('2d')
    drawChart(ctx, canvas.offsetWidth, canvas.offsetHeight)
}
</script>