<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Gestión de Backups
                </h2>
                <div class="flex space-x-3">
                    <Button @click="showCreateModal = true" variant="primary">
                        <CloudArrowUpIcon class="w-5 h-5 mr-2" />
                        Crear Backup
                    </Button>
                    <Button @click="$inertia.visit(route('backups.schedules'))" variant="secondary">
                        <ClockIcon class="w-5 h-5 mr-2" />
                        Programaciones
                    </Button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Estadísticas -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Stats
                        label="Total Backups"
                        :value="statistics.total"
                        icon="document-duplicate"
                        color="blue"
                    />
                    <Stats
                        label="Tasa de Éxito"
                        :value="`${statistics.success_rate}%`"
                        icon="check-circle"
                        color="green"
                    />
                    <Stats
                        label="Tamaño Total"
                        :value="formatFileSize(statistics.total_size)"
                        icon="server"
                        color="purple"
                    />
                    <Stats
                        label="Programados"
                        :value="statistics.scheduled_count"
                        icon="clock"
                        color="yellow"
                    />
                </div>

                <!-- Información de Almacenamiento -->
                <Card>
                    <template #header>
                        <h3 class="text-lg font-medium text-gray-900">
                            Estado del Almacenamiento
                        </h3>
                    </template>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">Uso del disco</span>
                            <span class="text-sm text-gray-600">
                                {{ formatFileSize(storageInfo.used) }} / {{ formatFileSize(storageInfo.total) }}
                            </span>
                        </div>
                        
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div 
                                class="h-2 rounded-full transition-all duration-300"
                                :class="getStorageColorClass(storageInfo.usage_percentage)"
                                :style="`width: ${storageInfo.usage_percentage}%`"
                            ></div>
                        </div>
                        
                        <div class="flex justify-between text-xs text-gray-500">
                            <span>{{ storageInfo.usage_percentage }}% utilizado</span>
                            <span>{{ formatFileSize(storageInfo.free) }} disponible</span>
                        </div>
                    </div>
                </Card>

                <!-- Backups Programados Activos -->
                <Card v-if="schedules.length > 0">
                    <template #header>
                        <h3 class="text-lg font-medium text-gray-900">
                            Próximos Backups Programados
                        </h3>
                    </template>
                    
                    <div class="space-y-3">
                        <div v-for="schedule in schedules" :key="schedule.id" 
                             class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <div class="font-medium text-gray-900">{{ schedule.name }}</div>
                                <div class="text-sm text-gray-600">
                                    {{ schedule.type }} - {{ schedule.frequency }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ formatDate(schedule.next_run) }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ getTimeFromNow(schedule.next_run) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </Card>

                <!-- Lista de Backups -->
                <Card>
                    <template #header>
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900">
                                Historial de Backups
                            </h3>
                            <div class="flex space-x-2">
                                <Button @click="showCleanupModal = true" variant="secondary" size="sm">
                                    <TrashIcon class="w-4 h-4 mr-1" />
                                    Limpiar
                                </Button>
                                <Button @click="$inertia.visit(route('backups.logs'))" variant="secondary" size="sm">
                                    <DocumentTextIcon class="w-4 h-4 mr-1" />
                                    Ver Logs
                                </Button>
                            </div>
                        </div>
                    </template>
                    
                    <div v-if="backups.data.length === 0" class="text-center py-8">
                        <ServerIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Sin backups</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            No hay backups creados aún. Crea tu primer backup.
                        </p>
                    </div>
                    
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Backup
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Tipo
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
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
                                <tr v-for="backup in backups.data" :key="backup.id">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ backup.filename }}
                                            </div>
                                            <div v-if="backup.description" class="text-sm text-gray-500">
                                                {{ backup.description }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <Badge :variant="getTypeVariant(backup.type)">
                                            {{ backup.type }}
                                        </Badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <Badge :variant="getStatusVariant(backup.status)">
                                            {{ backup.status }}
                                        </Badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatFileSize(backup.file_size) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ formatDate(backup.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <Button 
                                            v-if="backup.status === 'completed'"
                                            @click="downloadBackup(backup)" 
                                            variant="secondary" 
                                            size="sm"
                                        >
                                            <ArrowDownTrayIcon class="w-4 h-4" />
                                        </Button>
                                        <Button 
                                            v-if="backup.status === 'completed'"
                                            @click="confirmRestore(backup)" 
                                            variant="warning" 
                                            size="sm"
                                        >
                                            <ArrowPathIcon class="w-4 h-4" />
                                        </Button>
                                        <Button 
                                            @click="confirmDelete(backup)" 
                                            variant="danger" 
                                            size="sm"
                                        >
                                            <TrashIcon class="w-4 h-4" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginación -->
                    <div v-if="backups.links" class="mt-6">
                        <Pagination :links="backups.links" />
                    </div>
                </Card>
            </div>
        </div>

        <!-- Modal Crear Backup -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <template #title>Crear Nuevo Backup</template>
            
            <form @submit.prevent="createBackup">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Tipo de Backup
                        </label>
                        <select v-model="createForm.type" required 
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="full">Completo (Base de datos + Archivos)</option>
                            <option value="database">Solo Base de Datos</option>
                            <option value="files">Solo Archivos</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Descripción (opcional)
                        </label>
                        <textarea v-model="createForm.description" rows="3"
                                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Describe este backup..."></textarea>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <Button @click="showCreateModal = false" variant="secondary">
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="creating" variant="primary">
                        <span v-if="creating">Creando...</span>
                        <span v-else>Crear Backup</span>
                    </Button>
                </div>
            </form>
        </Modal>

        <!-- Modal Limpiar Backups -->
        <Modal :show="showCleanupModal" @close="showCleanupModal = false">
            <template #title>Limpiar Backups Antiguos</template>
            
            <form @submit.prevent="cleanupBackups">
                <div class="space-y-4">
                    <p class="text-sm text-gray-600">
                        Esta acción eliminará todos los backups más antiguos que el número de días especificado.
                    </p>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Eliminar backups anteriores a (días)
                        </label>
                        <input v-model.number="cleanupForm.days" type="number" min="1" max="365" required
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    
                    <div class="bg-yellow-50 p-4 rounded-md">
                        <div class="flex">
                            <ExclamationTriangleIcon class="h-5 w-5 text-yellow-400" />
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800">
                                    Advertencia
                                </h3>
                                <div class="mt-2 text-sm text-yellow-700">
                                    Esta acción no se puede deshacer. Los backups eliminados no podrán recuperarse.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <Button @click="showCleanupModal = false" variant="secondary">
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="cleaning" variant="danger">
                        <span v-if="cleaning">Limpiando...</span>
                        <span v-else>Eliminar Backups</span>
                    </Button>
                </div>
            </form>
        </Modal>

        <!-- Modal Confirmar Restauración -->
        <Modal :show="showRestoreModal" @close="showRestoreModal = false">
            <template #title>Confirmar Restauración</template>
            
            <form @submit.prevent="restoreBackup">
                <div class="space-y-4">
                    <div class="bg-red-50 p-4 rounded-md">
                        <div class="flex">
                            <ExclamationTriangleIcon class="h-5 w-5 text-red-400" />
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">
                                    ⚠️ Acción Crítica
                                </h3>
                                <div class="mt-2 text-sm text-red-700">
                                    Esta acción reemplazará todos los datos actuales con los del backup seleccionado.
                                    <strong>Esta operación no se puede deshacer.</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="selectedBackup">
                        <p class="text-sm text-gray-600">
                            <strong>Backup a restaurar:</strong> {{ selectedBackup.filename }}<br>
                            <strong>Fecha:</strong> {{ formatDate(selectedBackup.created_at) }}<br>
                            <strong>Tipo:</strong> {{ selectedBackup.type }}
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Para confirmar, escriba: <strong>CONFIRMAR_RESTAURACION</strong>
                        </label>
                        <input v-model="restoreForm.confirmation" type="text" required
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="CONFIRMAR_RESTAURACION">
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <Button @click="showRestoreModal = false" variant="secondary">
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="restoring || restoreForm.confirmation !== 'CONFIRMAR_RESTAURACION'" variant="danger">
                        <span v-if="restoring">Restaurando...</span>
                        <span v-else>Restaurar Backup</span>
                    </Button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Card from '@/Components/UI/Card.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import Stats from '@/Components/UI/Stats.vue'
import Modal from '@/Components/Modal.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import { 
    CloudArrowUpIcon,
    ClockIcon,
    ServerIcon,
    TrashIcon,
    DocumentTextIcon,
    ArrowDownTrayIcon,
    ArrowPathIcon,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'
import { useFormatters } from '@/composables/useFormatters'

const props = defineProps({
    backups: Object,
    schedules: Array,
    statistics: Object,
    storageInfo: Object
})

const { formatDate, formatFileSize } = useFormatters()

// Estado reactivo
const showCreateModal = ref(false)
const showCleanupModal = ref(false)
const showRestoreModal = ref(false)
const selectedBackup = ref(null)
const creating = ref(false)
const cleaning = ref(false)
const restoring = ref(false)

const createForm = ref({
    type: 'full',
    description: ''
})

const cleanupForm = ref({
    days: 30
})

const restoreForm = ref({
    confirmation: ''
})

// Métodos
const createBackup = async () => {
    creating.value = true
    try {
        router.post(route('backups.create'), createForm.value, {
            onFinish: () => {
                creating.value = false
                showCreateModal.value = false
                createForm.value = { type: 'full', description: '' }
            }
        })
    } catch (error) {
        creating.value = false
    }
}

const cleanupBackups = async () => {
    cleaning.value = true
    try {
        router.post(route('backups.cleanup'), cleanupForm.value, {
            onFinish: () => {
                cleaning.value = false
                showCleanupModal.value = false
                cleanupForm.value = { days: 30 }
            }
        })
    } catch (error) {
        cleaning.value = false
    }
}

const confirmRestore = (backup) => {
    selectedBackup.value = backup
    showRestoreModal.value = true
}

const restoreBackup = async () => {
    if (!selectedBackup.value) return
    
    restoring.value = true
    try {
        router.post(route('backups.restore', selectedBackup.value.id), restoreForm.value, {
            onFinish: () => {
                restoring.value = false
                showRestoreModal.value = false
                restoreForm.value = { confirmation: '' }
                selectedBackup.value = null
            }
        })
    } catch (error) {
        restoring.value = false
    }
}

const downloadBackup = (backup) => {
    window.open(route('backups.download', backup.id), '_blank')
}

const confirmDelete = (backup) => {
    if (confirm('¿Estás seguro de que quieres eliminar este backup?')) {
        router.delete(route('backups.destroy', backup.id))
    }
}

const getTypeVariant = (type) => {
    const variants = {
        full: 'blue',
        database: 'green',
        files: 'purple'
    }
    return variants[type] || 'gray'
}

const getStatusVariant = (status) => {
    const variants = {
        completed: 'green',
        failed: 'red',
        in_progress: 'yellow',
        pending: 'gray'
    }
    return variants[status] || 'gray'
}

const getStorageColorClass = (percentage) => {
    if (percentage >= 90) return 'bg-red-500'
    if (percentage >= 75) return 'bg-yellow-500'
    return 'bg-green-500'
}

const getTimeFromNow = (date) => {
    // Implementar lógica para mostrar tiempo relativo
    const now = new Date()
    const target = new Date(date)
    const diff = target - now
    
    if (diff < 0) return 'Vencido'
    
    const days = Math.floor(diff / (1000 * 60 * 60 * 24))
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
    
    if (days > 0) return `en ${days} día${days > 1 ? 's' : ''}`
    if (hours > 0) return `en ${hours} hora${hours > 1 ? 's' : ''}`
    return 'muy pronto'
}
</script>