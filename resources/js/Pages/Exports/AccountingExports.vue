<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Exportaciones Contables
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Selector de Período -->
                <Card>
                    <template #header>
                        <h3 class="text-lg font-medium text-gray-900">
                            Período de Exportación
                        </h3>
                    </template>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Año
                            </label>
                            <select v-model="selectedYear" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="year in availableYears" :key="year" :value="year">
                                    {{ year }}
                                </option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Mes
                            </label>
                            <select v-model="selectedMonth" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="(name, index) in monthNames" :key="index" :value="index + 1">
                                    {{ name }}
                                </option>
                            </select>
                        </div>
                        
                        <div class="flex items-end">
                            <Button @click="loadPreview" :disabled="loading" class="w-full">
                                <span v-if="loading">Cargando...</span>
                                <span v-else>Vista Previa</span>
                            </Button>
                        </div>
                    </div>
                </Card>

                <!-- Vista Previa de Datos -->
                <Card v-if="previewData">
                    <template #header>
                        <h3 class="text-lg font-medium text-gray-900">
                            Vista Previa - {{ monthNames[selectedMonth - 1] }} {{ selectedYear }}
                        </h3>
                    </template>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Estadísticas de Ventas -->
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="font-medium text-green-800 mb-2">Ventas</h4>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span>Documentos:</span>
                                    <span class="font-medium">{{ previewData.sales_count }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Total:</span>
                                    <span class="font-medium">${{ formatCurrency(previewData.total_sales) }}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estadísticas de Compras -->
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="font-medium text-blue-800 mb-2">Compras</h4>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span>Documentos:</span>
                                    <span class="font-medium">{{ previewData.purchases_count }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Total:</span>
                                    <span class="font-medium">${{ formatCurrency(previewData.total_purchases) }}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estadísticas de Pagos -->
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <h4 class="font-medium text-purple-800 mb-2">Pagos</h4>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span>Documentos:</span>
                                    <span class="font-medium">{{ previewData.payments_count }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Total:</span>
                                    <span class="font-medium">${{ formatCurrency(previewData.total_payments) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Muestra de Registros -->
                    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Muestra de Ventas -->
                        <div>
                            <h5 class="font-medium text-gray-900 mb-3">Últimas Ventas</h5>
                            <div class="space-y-2">
                                <div v-for="sale in previewData.sample_records.sales" :key="sale.number" 
                                     class="flex justify-between items-center p-2 bg-gray-50 rounded">
                                    <div>
                                        <div class="font-medium">{{ sale.customer }}</div>
                                        <div class="text-sm text-gray-600">{{ sale.date }} - Doc. {{ sale.number }}</div>
                                    </div>
                                    <div class="font-medium">${{ formatCurrency(sale.amount) }}</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Muestra de Compras -->
                        <div>
                            <h5 class="font-medium text-gray-900 mb-3">Últimas Compras</h5>
                            <div class="space-y-2">
                                <div v-for="purchase in previewData.sample_records.purchases" :key="purchase.number" 
                                     class="flex justify-between items-center p-2 bg-gray-50 rounded">
                                    <div>
                                        <div class="font-medium">{{ purchase.supplier }}</div>
                                        <div class="text-sm text-gray-600">{{ purchase.date }} - Doc. {{ purchase.number }}</div>
                                    </div>
                                    <div class="font-medium">${{ formatCurrency(purchase.amount) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </Card>

                <!-- Formatos de Exportación -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card v-for="(format, key) in formats" :key="key" class="relative">
                        <template #header>
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                    <DocumentArrowDownIcon class="w-6 h-6 text-indigo-600" />
                                </div>
                                <div>
                                    <h3 class="font-medium text-gray-900">{{ format.name }}</h3>
                                    <p class="text-sm text-gray-500">.{{ format.extension }}</p>
                                </div>
                            </div>
                        </template>
                        
                        <div class="space-y-4">
                            <p class="text-sm text-gray-600">{{ format.description }}</p>
                            
                            <div class="space-y-2">
                                <Button 
                                    @click="exportFormat(key)" 
                                    :disabled="!previewData || exporting[key]"
                                    class="w-full"
                                    variant="primary"
                                >
                                    <span v-if="exporting[key]">Exportando...</span>
                                    <span v-else>Exportar</span>
                                </Button>
                                
                                <!-- SII tiene opciones especiales -->
                                <div v-if="key === 'sii'" class="space-y-2">
                                    <select v-model="siiType" class="w-full text-sm rounded-md border-gray-300">
                                        <option value="both">Ventas y Compras</option>
                                        <option value="sales">Solo Ventas</option>
                                        <option value="purchases">Solo Compras</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>

                <!-- Historial de Exportaciones -->
                <Card>
                    <template #header>
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900">
                                Historial de Exportaciones
                            </h3>
                            <Button @click="loadHistory" variant="secondary" size="sm">
                                <ArrowPathIcon class="w-4 h-4 mr-2" />
                                Actualizar
                            </Button>
                        </div>
                    </template>
                    
                    <div v-if="exportHistory.length === 0" class="text-center py-8">
                        <DocumentIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Sin exportaciones</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            No hay exportaciones previas para mostrar.
                        </p>
                    </div>
                    
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Formato
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Período
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Tamaño
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="exportItem in exportHistory" :key="exportItem.filename">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <Badge :variant="getFormatVariant(exportItem.format)">
                                                {{ exportItem.format.toUpperCase() }}
                                            </Badge>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ monthNames[exportItem.month - 1] }} {{ exportItem.year }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ formatFileSize(exportItem.size) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ formatDate(exportItem.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <Button @click="downloadExport(exportItem.filename)" variant="secondary" size="sm">
                                            <ArrowDownTrayIcon class="w-4 h-4 mr-1" />
                                            Descargar
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Card from '@/Components/UI/Card.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import { 
    DocumentArrowDownIcon, 
    DocumentIcon, 
    ArrowPathIcon,
    ArrowDownTrayIcon 
} from '@heroicons/vue/24/outline'
import { useFormatters } from '@/composables/useFormatters'

const props = defineProps({
    formats: Object,
    currentYear: Number,
    currentMonth: Number
})

const { formatCurrency, formatDate } = useFormatters()

// Estado reactivo
const selectedYear = ref(props.currentYear)
const selectedMonth = ref(props.currentMonth)
const loading = ref(false)
const exporting = ref({})
const siiType = ref('both')
const previewData = ref(null)
const exportHistory = ref([])

// Datos computados
const availableYears = computed(() => {
    const years = []
    for (let year = 2020; year <= props.currentYear + 1; year++) {
        years.push(year)
    }
    return years.reverse()
})

const monthNames = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
]

// Métodos
const loadPreview = async () => {
    loading.value = true
    try {
        const response = await fetch('/exports/accounting/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                year: selectedYear.value,
                month: selectedMonth.value,
                format: 'contpaq' // Default para preview
            })
        })
        
        const data = await response.json()
        if (response.ok) {
            previewData.value = data.preview
        } else {
            console.error('Error loading preview:', data.error)
        }
    } catch (error) {
        console.error('Error loading preview:', error)
    } finally {
        loading.value = false
    }
}

const exportFormat = async (format) => {
    exporting.value[format] = true
    
    try {
        let url = `/exports/accounting/${format}`
        let params = {
            year: selectedYear.value,
            month: selectedMonth.value
        }
        
        if (format === 'sii') {
            params.type = siiType.value
        }
        
        // Crear formulario para descarga
        const form = document.createElement('form')
        form.method = 'POST'
        form.action = url
        form.style.display = 'none'
        
        // Token CSRF
        const csrfInput = document.createElement('input')
        csrfInput.type = 'hidden'
        csrfInput.name = '_token'
        csrfInput.value = document.querySelector('meta[name="csrf-token"]').content
        form.appendChild(csrfInput)
        
        // Parámetros
        Object.entries(params).forEach(([key, value]) => {
            const input = document.createElement('input')
            input.type = 'hidden'
            input.name = key
            input.value = value
            form.appendChild(input)
        })
        
        document.body.appendChild(form)
        form.submit()
        document.body.removeChild(form)
        
        // Recargar historial después de un momento
        setTimeout(() => {
            loadHistory()
        }, 2000)
        
    } catch (error) {
        console.error('Error exporting:', error)
    } finally {
        exporting.value[format] = false
    }
}

const loadHistory = async () => {
    try {
        const response = await fetch('/exports/accounting/history')
        const data = await response.json()
        exportHistory.value = data.exports || []
    } catch (error) {
        console.error('Error loading history:', error)
    }
}

const downloadExport = (filename) => {
    window.open(`/exports/accounting/download/${filename}`, '_blank')
}

const getFormatVariant = (format) => {
    const variants = {
        contpaq: 'blue',
        monica: 'green',
        tango: 'purple',
        sii: 'yellow'
    }
    return variants[format] || 'gray'
}

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

// Cargar datos iniciales
onMounted(() => {
    loadHistory()
})
</script>