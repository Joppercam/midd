<template>
    <Head title="Gestión de Respaldos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Gestión de Respaldos
                </h2>
                <button
                    @click="showCreateModal = true"
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                >
                    Crear Backup
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Estadísticas -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h6l2 2h6a2 2 0 012 2v4a2 2 0 01-2 2"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Backups</p>
                                <p class="text-2xl font-bold text-gray-900">{{ statistics.total }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Exitosos</p>
                                <p class="text-2xl font-bold text-gray-900">{{ statistics.successful }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100">
                                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Programados</p>
                                <p class="text-2xl font-bold text-gray-900">{{ statistics.scheduled_count }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Tamaño Total</p>
                                <p class="text-2xl font-bold text-gray-900">{{ formatFileSize(statistics.total_size) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de Almacenamiento -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6 p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Almacenamiento</h3>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                <span>Espacio Usado</span>
                                <span>{{ storageInfo.usage_percentage }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div 
                                    class="h-2 rounded-full transition-all duration-300"
                                    :class="getStorageBarColor(storageInfo.usage_percentage)"
                                    :style="{ width: storageInfo.usage_percentage + '%' }"
                                ></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">Total:</span>
                                <span class="font-medium ml-1">{{ formatFileSize(storageInfo.total) }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Usado:</span>
                                <span class="font-medium ml-1">{{ formatFileSize(storageInfo.used) }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Libre:</span>
                                <span class="font-medium ml-1">{{ formatFileSize(storageInfo.free) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de Programación -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6 p-6" v-if="schedules.length > 0">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Backups Programados</h3>
                        <Link href="/backups/schedules" class="text-blue-600 hover:text-blue-800">
                            Gestionar Programaciones
                        </Link>
                    </div>
                    <div class="space-y-2">
                        <div v-for="schedule in schedules" :key="schedule.id" class="flex justify-between items-center py-2 border-b border-gray-100 last:border-b-0">
                            <div>
                                <span class="font-medium">{{ schedule.name }}</span>
                                <span class="text-gray-600 ml-2">({{ schedule.frequency }})</span>
                            </div>
                            <div class="text-sm text-gray-500">
                                Próximo: {{ formatDate(schedule.next_run) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Backups -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Historial de Backups</h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Archivo
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
                                <tr v-for="backup in backups.data" :key="backup.id" class="hover:bg-gray-50">
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
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span 
                                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                            :class="getTypeClass(backup.type)"
                                        >
                                            {{ getTypeLabel(backup.type) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span 
                                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                            :class="getStatusClass(backup.status)"
                                        >
                                            {{ getStatusLabel(backup.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatFileSize(backup.file_size) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ formatDate(backup.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <Link
                                            v-if="backup.status === 'completed'"
                                            :href="`/backups/${backup.id}/download`"
                                            class="text-blue-600 hover:text-blue-900"
                                        >
                                            Descargar
                                        </Link>
                                        
                                        <button
                                            v-if="backup.status === 'completed'"
                                            @click="confirmRestore(backup)"
                                            class="text-green-600 hover:text-green-900"
                                        >
                                            Restaurar
                                        </button>
                                        
                                        <button
                                            @click="confirmDelete(backup)"
                                            class="text-red-600 hover:text-red-900"
                                        >
                                            Eliminar
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div class="px-6 py-3 border-t border-gray-200">
                        <Pagination :links="backups.links" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Crear Backup -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Crear Nuevo Backup</h3>
                
                <form @submit.prevent="createBackup">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Tipo de Backup
                            </label>
                            <select 
                                v-model="createForm.type" 
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="full">Completo (Base de datos + Archivos)</option>
                                <option value="database">Solo Base de Datos</option>
                                <option value="files">Solo Archivos</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Descripción (Opcional)
                            </label>
                            <textarea 
                                v-model="createForm.description"
                                rows="3"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Descripción del backup..."
                            ></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="isCreating"
                            class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50"
                        >
                            {{ isCreating ? 'Creando...' : 'Crear Backup' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    backups: Object,
    schedules: Array,
    statistics: Object,
    storageInfo: Object
});

const showCreateModal = ref(false);
const isCreating = ref(false);

const createForm = reactive({
    type: 'full',
    description: ''
});

const createBackup = () => {
    if (confirm('¿Estás seguro de que quieres crear este backup?')) {
        isCreating.value = true;
        
        router.post('/backups/create', createForm, {
            onFinish: () => {
                isCreating.value = false;
                showCreateModal.value = false;
                createForm.type = 'full';
                createForm.description = '';
            }
        });
    }
};

const confirmDelete = (backup) => {
    if (confirm('¿Estás seguro de que quieres eliminar este backup? Esta acción no se puede deshacer.')) {
        router.delete(`/backups/${backup.id}`);
    }
};

const confirmRestore = (backup) => {
    const confirmation = prompt('ADVERTENCIA: Restaurar un backup sobrescribirá todos los datos actuales.\n\nPara confirmar, escribe "CONFIRMAR_RESTAURACION":');
    
    if (confirmation === 'CONFIRMAR_RESTAURACION') {
        router.post(`/backups/${backup.id}/restore`, {
            confirmation: confirmation
        });
    } else if (confirmation !== null) {
        alert('Confirmación incorrecta. La restauración ha sido cancelada.');
    }
};

const getStatusClass = (status) => {
    const classes = {
        'completed': 'bg-green-100 text-green-800',
        'failed': 'bg-red-100 text-red-800',
        'in_progress': 'bg-yellow-100 text-yellow-800',
        'pending': 'bg-gray-100 text-gray-800'
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const getStatusLabel = (status) => {
    const labels = {
        'completed': 'Completado',
        'failed': 'Fallido',
        'in_progress': 'En Progreso',
        'pending': 'Pendiente'
    };
    return labels[status] || status;
};

const getTypeClass = (type) => {
    const classes = {
        'full': 'bg-blue-100 text-blue-800',
        'database': 'bg-purple-100 text-purple-800',
        'files': 'bg-orange-100 text-orange-800'
    };
    return classes[type] || 'bg-gray-100 text-gray-800';
};

const getTypeLabel = (type) => {
    const labels = {
        'full': 'Completo',
        'database': 'Base de Datos',
        'files': 'Archivos'
    };
    return labels[type] || type;
};

const getStorageBarColor = (percentage) => {
    if (percentage < 70) return 'bg-green-500';
    if (percentage < 85) return 'bg-yellow-500';
    return 'bg-red-500';
};

const formatFileSize = (bytes) => {
    if (!bytes || bytes === 0) return '0 B';
    
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    
    return new Date(date).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>