<template>
    <Head :title="`Editar Cotización #${quote.quote_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Editar Cotización #{{ quote.quote_number }}
                </h2>
                <Link :href="route('quotes.show', quote.id)" class="text-sm text-gray-600 hover:text-gray-900">
                    Ver cotización
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit">
                    <Card>
                        <template #content>
                            <!-- Información del Cliente -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <InputLabel for="customer_id" value="Cliente" />
                                    <select
                                        id="customer_id"
                                        v-model="form.customer_id"
                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        required
                                    >
                                        <option value="">Seleccionar cliente</option>
                                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                            {{ customer.name }} - {{ customer.rut }}
                                        </option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.customer_id" />
                                </div>

                                <div>
                                    <InputLabel for="quote_date" value="Fecha" />
                                    <TextInput
                                        id="quote_date"
                                        type="date"
                                        class="mt-1 block w-full"
                                        v-model="form.quote_date"
                                        required
                                    />
                                    <InputError class="mt-2" :message="form.errors.quote_date" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <InputLabel for="valid_until" value="Válida hasta" />
                                    <TextInput
                                        id="valid_until"
                                        type="date"
                                        class="mt-1 block w-full"
                                        v-model="form.valid_until"
                                        required
                                    />
                                    <InputError class="mt-2" :message="form.errors.valid_until" />
                                </div>

                                <div>
                                    <InputLabel for="payment_terms" value="Términos de pago" />
                                    <TextInput
                                        id="payment_terms"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.payment_terms"
                                        placeholder="Ej: 30 días"
                                    />
                                    <InputError class="mt-2" :message="form.errors.payment_terms" />
                                </div>
                            </div>

                            <!-- Items de la cotización -->
                            <div class="mb-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-medium text-gray-900">Items</h3>
                                    <button
                                        type="button"
                                        @click="addItem"
                                        class="text-sm bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700"
                                    >
                                        Agregar item
                                    </button>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Producto/Servicio
                                                </th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Descripción
                                                </th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Cantidad
                                                </th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Precio Unit.
                                                </th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Total
                                                </th>
                                                <th class="px-4 py-3"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="(item, index) in form.items" :key="index">
                                                <td class="px-4 py-3">
                                                    <select
                                                        v-model="item.product_id"
                                                        @change="selectProduct(index)"
                                                        class="block w-full border-gray-300 rounded-md shadow-sm text-sm"
                                                    >
                                                        <option value="">Seleccionar</option>
                                                        <option v-for="product in products" :key="product.id" :value="product.id">
                                                            {{ product.name }}
                                                        </option>
                                                    </select>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input
                                                        type="text"
                                                        v-model="item.description"
                                                        class="block w-full border-gray-300 rounded-md shadow-sm text-sm"
                                                        placeholder="Descripción opcional"
                                                    />
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input
                                                        type="number"
                                                        v-model="item.quantity"
                                                        @input="calculateItemTotal(index)"
                                                        class="block w-full border-gray-300 rounded-md shadow-sm text-sm text-right"
                                                        min="1"
                                                        step="0.01"
                                                        required
                                                    />
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input
                                                        type="number"
                                                        v-model="item.unit_price"
                                                        @input="calculateItemTotal(index)"
                                                        class="block w-full border-gray-300 rounded-md shadow-sm text-sm text-right"
                                                        min="0"
                                                        step="0.01"
                                                        required
                                                    />
                                                </td>
                                                <td class="px-4 py-3 text-right font-medium">
                                                    ${{ formatCurrency(item.total_amount) }}
                                                </td>
                                                <td class="px-4 py-3">
                                                    <button
                                                        type="button"
                                                        @click="removeItem(index)"
                                                        class="text-red-600 hover:text-red-900"
                                                    >
                                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Totales -->
                            <div class="border-t pt-4">
                                <div class="flex justify-end">
                                    <div class="w-64">
                                        <div class="flex justify-between py-2">
                                            <span>Subtotal:</span>
                                            <span class="font-medium">${{ formatCurrency(totals.subtotal) }}</span>
                                        </div>
                                        <div class="flex justify-between py-2">
                                            <span>IVA (19%):</span>
                                            <span class="font-medium">${{ formatCurrency(totals.tax) }}</span>
                                        </div>
                                        <div class="flex justify-between py-2 text-lg font-bold border-t">
                                            <span>Total:</span>
                                            <span>${{ formatCurrency(totals.total) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notas -->
                            <div class="mt-6">
                                <InputLabel for="notes" value="Notas (opcional)" />
                                <textarea
                                    id="notes"
                                    v-model="form.notes"
                                    rows="3"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                ></textarea>
                                <InputError class="mt-2" :message="form.errors.notes" />
                            </div>
                        </template>
                        <template #footer>
                            <div class="flex justify-end gap-3">
                                <SecondaryButton @click="$inertia.get(route('quotes.show', quote.id))">
                                    Cancelar
                                </SecondaryButton>
                                <PrimaryButton :disabled="form.processing" type="submit">
                                    Guardar Cambios
                                </PrimaryButton>
                            </div>
                        </template>
                    </Card>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/UI/Card.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    quote: Object,
    customers: Array,
    products: Array,
});

const form = useForm({
    customer_id: props.quote.customer_id,
    quote_date: props.quote.quote_date,
    valid_until: props.quote.valid_until,
    payment_terms: props.quote.payment_terms || '',
    notes: props.quote.notes || '',
    items: props.quote.items.map(item => ({
        id: item.id,
        product_id: item.product_id || '',
        description: item.description || '',
        quantity: item.quantity,
        unit_price: item.unit_price,
        total_amount: item.total_amount,
    })),
});

const totals = computed(() => {
    const subtotal = form.items.reduce((sum, item) => sum + (item.total_amount || 0), 0);
    const tax = subtotal * 0.19;
    const total = subtotal + tax;
    
    return {
        subtotal,
        tax,
        total
    };
});

const addItem = () => {
    form.items.push({
        product_id: '',
        description: '',
        quantity: 1,
        unit_price: 0,
        total_amount: 0,
    });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const selectProduct = (index) => {
    const product = props.products.find(p => p.id === form.items[index].product_id);
    if (product) {
        form.items[index].unit_price = product.sale_price;
        form.items[index].description = product.description || '';
        calculateItemTotal(index);
    }
};

const calculateItemTotal = (index) => {
    const item = form.items[index];
    item.total_amount = (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-CL').format(value || 0);
};

const submit = () => {
    form.transform(data => ({
        ...data,
        subtotal: totals.value.subtotal,
        tax_amount: totals.value.tax,
        total_amount: totals.value.total,
        items: data.items.filter(item => item.product_id || item.description)
    })).put(route('quotes.update', props.quote.id));
};
</script>