<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Registrar Pago
                </h2>
                <Link
                    :href="route('payments.index')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    Volver al listado
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <!-- Información del Pago -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Información del Pago</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="customer_id" value="Cliente" />
                                    <select
                                        id="customer_id"
                                        v-model="form.customer_id"
                                        @change="loadUnpaidDocuments"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                        required
                                    >
                                        <option value="">Seleccionar cliente</option>
                                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                            {{ customer.name }} - {{ customer.rut }}
                                        </option>
                                    </select>
                                    <InputError :message="form.errors.customer_id" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="payment_date" value="Fecha del Pago" />
                                    <TextInput
                                        id="payment_date"
                                        v-model="form.payment_date"
                                        type="date"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                    <InputError :message="form.errors.payment_date" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="amount" value="Monto" />
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">$</span>
                                        </div>
                                        <input
                                            type="number"
                                            id="amount"
                                            v-model="form.amount"
                                            class="block w-full pl-7 pr-12 border-gray-300 rounded-md"
                                            placeholder="0"
                                            min="1"
                                            step="1"
                                            required
                                        />
                                    </div>
                                    <InputError :message="form.errors.amount" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="payment_method" value="Método de Pago" />
                                    <select
                                        id="payment_method"
                                        v-model="form.payment_method"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                        required
                                    >
                                        <option value="cash">Efectivo</option>
                                        <option value="bank_transfer">Transferencia Bancaria</option>
                                        <option value="check">Cheque</option>
                                        <option value="credit_card">Tarjeta de Crédito</option>
                                        <option value="debit_card">Tarjeta de Débito</option>
                                        <option value="electronic">Pago Electrónico</option>
                                        <option value="other">Otro</option>
                                    </select>
                                    <InputError :message="form.errors.payment_method" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="reference" value="Referencia" />
                                    <TextInput
                                        id="reference"
                                        v-model="form.reference"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="Número de transferencia, cheque, etc."
                                    />
                                    <InputError :message="form.errors.reference" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="bank" value="Banco" />
                                    <TextInput
                                        id="bank"
                                        v-model="form.bank"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="Banco emisor"
                                    />
                                    <InputError :message="form.errors.bank" class="mt-2" />
                                </div>

                                <div class="md:col-span-2">
                                    <InputLabel for="description" value="Descripción" />
                                    <textarea
                                        id="description"
                                        v-model="form.description"
                                        rows="3"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                        placeholder="Descripción del pago..."
                                    ></textarea>
                                    <InputError :message="form.errors.description" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="status" value="Estado" />
                                    <select
                                        id="status"
                                        v-model="form.status"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                        required
                                    >
                                        <option value="confirmed">Confirmado</option>
                                        <option value="pending">Pendiente</option>
                                    </select>
                                    <InputError :message="form.errors.status" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <!-- Asignación a Facturas -->
                        <div v-if="unpaidDocuments.length > 0" class="border-t pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Asignar a Facturas</h3>
                            
                            <div class="mb-4 p-4 bg-blue-50 rounded-lg">
                                <p class="text-sm text-blue-700">
                                    <strong>Opcional:</strong> Puedes asignar este pago a facturas específicas. 
                                    Si no asignas el pago completo, el monto restante quedará disponible para futuras asignaciones.
                                </p>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Asignar
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Documento
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Fecha Emisión
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Fecha Venc.
                                            </th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Saldo
                                            </th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Monto a Pagar
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="document in unpaidDocuments" :key="document.id">
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <input
                                                    type="checkbox"
                                                    v-model="selectedDocuments[document.id]"
                                                    @change="toggleDocumentSelection(document)"
                                                    class="rounded border-gray-300 text-indigo-600 shadow-sm"
                                                />
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ document.formatted_number }}</div>
                                                <div class="text-sm text-gray-500">{{ getDocumentTypeLabel(document.document_type) }}</div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ formatDate(document.issue_date) }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm" :class="isOverdue(document.due_date) ? 'text-red-600 font-medium' : 'text-gray-900'">
                                                {{ formatDate(document.due_date) }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                {{ formatCurrency(document.balance) }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-right">
                                                <input
                                                    v-if="selectedDocuments[document.id]"
                                                    type="number"
                                                    v-model="allocationAmounts[document.id]"
                                                    :max="Math.min(document.balance, form.amount || 0)"
                                                    min="0.01"
                                                    step="0.01"
                                                    class="w-24 border-gray-300 rounded-md text-sm text-right"
                                                    placeholder="0"
                                                />
                                                <span v-else class="text-sm text-gray-400">-</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4 flex justify-between items-center">
                                <div class="text-sm text-gray-600">
                                    Total asignado: <span class="font-medium">{{ formatCurrency(totalAllocated) }}</span>
                                </div>
                                <div class="text-sm text-gray-600">
                                    Monto disponible: <span class="font-medium" :class="remainingAmount < 0 ? 'text-red-600' : 'text-green-600'">
                                        {{ formatCurrency(remainingAmount) }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="remainingAmount < 0" class="mt-2 p-3 bg-red-50 border border-red-200 rounded-md">
                                <p class="text-sm text-red-700">
                                    <strong>Error:</strong> La suma de asignaciones excede el monto del pago.
                                </p>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="flex items-center justify-end space-x-3 pt-6 border-t">
                            <Link
                                :href="route('payments.index')"
                                class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50"
                            >
                                Cancelar
                            </Link>
                            <PrimaryButton
                                :class="{ 'opacity-25': form.processing || remainingAmount < 0 }"
                                :disabled="form.processing || remainingAmount < 0"
                            >
                                Registrar Pago
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import axios from 'axios';

const props = defineProps({
    customers: Array
});

const form = useForm({
    customer_id: '',
    payment_date: new Date().toISOString().split('T')[0],
    amount: '',
    payment_method: 'cash',
    reference: '',
    bank: '',
    description: '',
    status: 'confirmed',
    allocations: []
});

const unpaidDocuments = ref([]);
const selectedDocuments = ref({});
const allocationAmounts = ref({});

const totalAllocated = computed(() => {
    return Object.values(allocationAmounts.value).reduce((sum, amount) => {
        return sum + (parseFloat(amount) || 0);
    }, 0);
});

const remainingAmount = computed(() => {
    return (parseFloat(form.amount) || 0) - totalAllocated.value;
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0
    }).format(amount || 0);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-CL');
};

const getDocumentTypeLabel = (type) => {
    const labels = {
        'invoice': 'Factura',
        'debit_note': 'Nota de Débito'
    };
    return labels[type] || type;
};

const isOverdue = (dueDate) => {
    return new Date(dueDate) < new Date();
};

const loadUnpaidDocuments = async () => {
    if (!form.customer_id) {
        unpaidDocuments.value = [];
        return;
    }

    try {
        const response = await axios.get(route('payments.unpaid-documents', form.customer_id));
        unpaidDocuments.value = response.data;
        selectedDocuments.value = {};
        allocationAmounts.value = {};
    } catch (error) {
        console.error('Error loading unpaid documents:', error);
    }
};

const toggleDocumentSelection = (document) => {
    if (selectedDocuments.value[document.id]) {
        // Si se selecciona, asignar el mínimo entre el saldo y el monto disponible
        const availableAmount = remainingAmount.value + (parseFloat(allocationAmounts.value[document.id]) || 0);
        allocationAmounts.value[document.id] = Math.min(document.balance, availableAmount).toFixed(2);
    } else {
        // Si se deselecciona, limpiar el monto
        allocationAmounts.value[document.id] = '';
    }
};

const submit = () => {
    // Preparar asignaciones
    const allocations = [];
    for (const [documentId, isSelected] of Object.entries(selectedDocuments.value)) {
        if (isSelected && allocationAmounts.value[documentId] > 0) {
            allocations.push({
                tax_document_id: parseInt(documentId),
                amount: parseFloat(allocationAmounts.value[documentId])
            });
        }
    }

    form.allocations = allocations;
    form.post(route('payments.store'));
};

// Watch para actualizar montos cuando cambia el monto total del pago
watch(() => form.amount, () => {
    // Recalcular asignaciones si es necesario
    for (const [documentId, isSelected] of Object.entries(selectedDocuments.value)) {
        if (isSelected) {
            const document = unpaidDocuments.value.find(d => d.id == documentId);
            if (document) {
                const currentAmount = parseFloat(allocationAmounts.value[documentId]) || 0;
                const maxAllowed = Math.min(document.balance, parseFloat(form.amount) || 0);
                if (currentAmount > maxAllowed) {
                    allocationAmounts.value[documentId] = maxAllowed.toFixed(2);
                }
            }
        }
    }
});
</script>