<template>
    <AuthenticatedLayout>
        <Head title="Nueva Orden de Compra" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="mb-6">
                    <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">
                        Nueva Orden de Compra
                    </h1>
                </div>

                <form @submit.prevent="submit">
                    <!-- Información general -->
                    <Card class="mb-6">
                        <div class="p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Información General</h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Proveedor -->
                                <div>
                                    <InputLabel for="supplier" value="Proveedor" />
                                    <select
                                        id="supplier"
                                        v-model="form.supplier_id"
                                        @change="updateSupplierInfo"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                        required
                                    >
                                        <option value="">Seleccionar proveedor</option>
                                        <option
                                            v-for="supplier in suppliers"
                                            :key="supplier.id"
                                            :value="supplier.id"
                                        >
                                            {{ supplier.name }} - {{ supplier.rut }}
                                        </option>
                                    </select>
                                    <InputError :message="form.errors.supplier_id" class="mt-2" />
                                </div>

                                <!-- Fecha de orden -->
                                <div>
                                    <InputLabel for="order_date" value="Fecha de Orden" />
                                    <TextInput
                                        id="order_date"
                                        v-model="form.order_date"
                                        type="date"
                                        required
                                    />
                                    <InputError :message="form.errors.order_date" class="mt-2" />
                                </div>

                                <!-- Fecha esperada -->
                                <div>
                                    <InputLabel for="expected_date" value="Fecha Esperada" />
                                    <TextInput
                                        id="expected_date"
                                        v-model="form.expected_date"
                                        type="date"
                                        :min="form.order_date"
                                    />
                                    <InputError :message="form.errors.expected_date" class="mt-2" />
                                </div>

                                <!-- Referencia -->
                                <div>
                                    <InputLabel for="reference" value="Referencia" />
                                    <TextInput
                                        id="reference"
                                        v-model="form.reference"
                                        type="text"
                                        placeholder="Ej: Cotización #123"
                                    />
                                    <InputError :message="form.errors.reference" class="mt-2" />
                                </div>

                                <!-- Moneda -->
                                <div>
                                    <InputLabel for="currency" value="Moneda" />
                                    <select
                                        id="currency"
                                        v-model="form.currency"
                                        @change="updateExchangeRate"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                    >
                                        <option value="CLP">CLP - Peso Chileno</option>
                                        <option value="USD">USD - Dólar Americano</option>
                                        <option value="EUR">EUR - Euro</option>
                                    </select>
                                    <InputError :message="form.errors.currency" class="mt-2" />
                                </div>

                                <!-- Tipo de cambio -->
                                <div v-if="form.currency !== 'CLP'">
                                    <InputLabel for="exchange_rate" value="Tipo de Cambio" />
                                    <TextInput
                                        id="exchange_rate"
                                        v-model="form.exchange_rate"
                                        type="number"
                                        step="0.0001"
                                        min="0.0001"
                                        required
                                    />
                                    <InputError :message="form.errors.exchange_rate" class="mt-2" />
                                </div>

                                <!-- Método de envío -->
                                <div>
                                    <InputLabel for="shipping_method" value="Método de Envío" />
                                    <TextInput
                                        id="shipping_method"
                                        v-model="form.shipping_method"
                                        type="text"
                                        placeholder="Ej: Courier, Retiro en tienda"
                                    />
                                    <InputError :message="form.errors.shipping_method" class="mt-2" />
                                </div>
                            </div>

                            <!-- Direcciones -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                <div>
                                    <InputLabel for="shipping_address" value="Dirección de Envío" />
                                    <textarea
                                        id="shipping_address"
                                        v-model="form.shipping_address"
                                        rows="3"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                    ></textarea>
                                    <InputError :message="form.errors.shipping_address" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="billing_address" value="Dirección de Facturación" />
                                    <textarea
                                        id="billing_address"
                                        v-model="form.billing_address"
                                        rows="3"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                    ></textarea>
                                    <InputError :message="form.errors.billing_address" class="mt-2" />
                                </div>
                            </div>

                            <!-- Notas y términos -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                <div>
                                    <InputLabel for="notes" value="Notas" />
                                    <textarea
                                        id="notes"
                                        v-model="form.notes"
                                        rows="3"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                    ></textarea>
                                    <InputError :message="form.errors.notes" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="terms" value="Términos y Condiciones" />
                                    <textarea
                                        id="terms"
                                        v-model="form.terms"
                                        rows="3"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                    ></textarea>
                                    <InputError :message="form.errors.terms" class="mt-2" />
                                </div>
                            </div>
                        </div>
                    </Card>

                    <!-- Items -->
                    <Card class="mb-6">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-lg font-medium text-gray-900">Items</h2>
                                <button
                                    type="button"
                                    @click="addItem"
                                    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Agregar Item
                                </button>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Producto
                                            </th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Descripción
                                            </th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Cantidad
                                            </th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Unidad
                                            </th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Precio
                                            </th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Desc %
                                            </th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Total
                                            </th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="(item, index) in form.items" :key="index">
                                            <td class="px-3 py-2">
                                                <select
                                                    v-model="item.product_id"
                                                    @change="updateItemFromProduct(index)"
                                                    class="w-full text-sm border-gray-300 rounded-md"
                                                >
                                                    <option value="">Sin producto</option>
                                                    <option
                                                        v-for="product in products"
                                                        :key="product.id"
                                                        :value="product.id"
                                                    >
                                                        {{ product.sku }} - {{ product.name }}
                                                    </option>
                                                </select>
                                            </td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model="item.description"
                                                    type="text"
                                                    required
                                                    class="w-full text-sm border-gray-300 rounded-md"
                                                />
                                            </td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model.number="item.quantity"
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    required
                                                    @input="calculateItemTotal(index)"
                                                    class="w-20 text-sm border-gray-300 rounded-md"
                                                />
                                            </td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model="item.unit"
                                                    type="text"
                                                    required
                                                    class="w-20 text-sm border-gray-300 rounded-md"
                                                />
                                            </td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model.number="item.unit_price"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    required
                                                    @input="calculateItemTotal(index)"
                                                    class="w-24 text-sm border-gray-300 rounded-md"
                                                />
                                            </td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model.number="item.discount_percent"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    @input="calculateItemTotal(index)"
                                                    class="w-16 text-sm border-gray-300 rounded-md"
                                                />
                                            </td>
                                            <td class="px-3 py-2 text-sm font-medium">
                                                {{ formatCurrency(item.total) }}
                                            </td>
                                            <td class="px-3 py-2">
                                                <button
                                                    type="button"
                                                    @click="removeItem(index)"
                                                    class="text-red-600 hover:text-red-900"
                                                >
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </Card>

                    <!-- Totales -->
                    <Card class="mb-6">
                        <div class="p-6">
                            <div class="max-w-xs ml-auto">
                                <div class="flex justify-between py-2">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span class="font-medium">{{ formatCurrency(totals.subtotal) }}</span>
                                </div>
                                <div class="flex justify-between py-2">
                                    <span class="text-gray-600">Descuento:</span>
                                    <span class="font-medium">{{ formatCurrency(totals.discount) }}</span>
                                </div>
                                <div class="flex justify-between py-2">
                                    <span class="text-gray-600">IVA (19%):</span>
                                    <span class="font-medium">{{ formatCurrency(totals.tax) }}</span>
                                </div>
                                <div class="flex justify-between py-2 border-t pt-4">
                                    <span class="text-lg font-medium">Total:</span>
                                    <span class="text-lg font-bold">{{ formatCurrency(totals.total) }}</span>
                                </div>
                            </div>
                        </div>
                    </Card>

                    <!-- Botones de acción -->
                    <div class="flex justify-end space-x-3">
                        <Link
                            :href="route('purchase-orders.index')"
                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                        >
                            Cancelar
                        </Link>
                        <PrimaryButton :disabled="form.processing">
                            Crear Orden
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/UI/Card.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useFormatters } from '@/composables/useFormatters';

const { formatCurrency } = useFormatters();

const props = defineProps({
    suppliers: Array,
    products: Array,
    defaultTerms: String,
});

const form = useForm({
    supplier_id: '',
    order_date: new Date().toISOString().split('T')[0],
    expected_date: '',
    reference: '',
    notes: '',
    terms: props.defaultTerms || '',
    currency: 'CLP',
    exchange_rate: 1,
    shipping_method: '',
    shipping_address: '',
    billing_address: '',
    items: [{
        product_id: null,
        description: '',
        sku: '',
        quantity: 1,
        unit: 'unidad',
        unit_price: 0,
        discount_percent: 0,
        tax_rate: 19,
        subtotal: 0,
        discount_amount: 0,
        tax_amount: 0,
        total: 0,
    }],
});

const totals = computed(() => {
    let subtotal = 0;
    let discount = 0;
    let tax = 0;
    let total = 0;

    form.items.forEach(item => {
        subtotal += item.subtotal || 0;
        discount += item.discount_amount || 0;
        tax += item.tax_amount || 0;
        total += item.total || 0;
    });

    return { subtotal, discount, tax, total };
});

const updateSupplierInfo = () => {
    const supplier = props.suppliers.find(s => s.id == form.supplier_id);
    if (supplier) {
        // Aquí podrías actualizar direcciones o términos basados en el proveedor
    }
};

const updateExchangeRate = () => {
    if (form.currency === 'CLP') {
        form.exchange_rate = 1;
    }
    // En producción, aquí podrías obtener el tipo de cambio de una API
};

const addItem = () => {
    form.items.push({
        product_id: null,
        description: '',
        sku: '',
        quantity: 1,
        unit: 'unidad',
        unit_price: 0,
        discount_percent: 0,
        tax_rate: 19,
        subtotal: 0,
        discount_amount: 0,
        tax_amount: 0,
        total: 0,
    });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const updateItemFromProduct = (index) => {
    const item = form.items[index];
    const product = props.products.find(p => p.id == item.product_id);
    
    if (product) {
        item.description = product.name;
        item.sku = product.sku;
        item.unit_price = product.price;
        calculateItemTotal(index);
    }
};

const calculateItemTotal = (index) => {
    const item = form.items[index];
    
    // Calcular subtotal
    item.subtotal = item.quantity * item.unit_price;
    
    // Calcular descuento
    item.discount_amount = item.subtotal * (item.discount_percent / 100);
    
    // Subtotal después del descuento
    const subtotalAfterDiscount = item.subtotal - item.discount_amount;
    
    // Calcular impuesto
    item.tax_amount = subtotalAfterDiscount * (item.tax_rate / 100);
    
    // Total
    item.total = subtotalAfterDiscount + item.tax_amount;
};

const submit = () => {
    form.post(route('purchase-orders.store'));
};
</script>