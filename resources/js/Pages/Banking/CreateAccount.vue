<template>
    <AuthenticatedLayout title="Nueva Cuenta Bancaria">
        <div class="py-6">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Encabezado -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900">Nueva Cuenta Bancaria</h1>
                    <p class="mt-2 text-gray-600">Agrega una nueva cuenta bancaria para comenzar a conciliar</p>
                </div>

                <!-- Formulario -->
                <form @submit.prevent="submit" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="grid grid-cols-1 gap-6">
                        <!-- Nombre de la cuenta -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">
                                Nombre de la cuenta <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                placeholder="Ej: Cuenta Corriente Principal"
                                required
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <!-- Banco -->
                        <div>
                            <label for="bank_name" class="block text-sm font-medium text-gray-700">
                                Banco <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="bank_name"
                                v-model="form.bank_name"
                                type="text"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                placeholder="Ej: Banco de Chile"
                                required
                            />
                            <p v-if="form.errors.bank_name" class="mt-1 text-sm text-red-600">{{ form.errors.bank_name }}</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Número de cuenta -->
                            <div>
                                <label for="account_number" class="block text-sm font-medium text-gray-700">
                                    Número de cuenta
                                </label>
                                <input
                                    id="account_number"
                                    v-model="form.account_number"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    placeholder="Ej: 0012345678"
                                />
                                <p v-if="form.errors.account_number" class="mt-1 text-sm text-red-600">{{ form.errors.account_number }}</p>
                            </div>

                            <!-- Tipo de cuenta -->
                            <div>
                                <label for="account_type" class="block text-sm font-medium text-gray-700">
                                    Tipo de cuenta <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="account_type"
                                    v-model="form.account_type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    required
                                >
                                    <option value="">Selecciona un tipo</option>
                                    <option value="checking">Cuenta Corriente</option>
                                    <option value="savings">Cuenta de Ahorro</option>
                                    <option value="credit_card">Tarjeta de Crédito</option>
                                </select>
                                <p v-if="form.errors.account_type" class="mt-1 text-sm text-red-600">{{ form.errors.account_type }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Moneda -->
                            <div>
                                <label for="currency" class="block text-sm font-medium text-gray-700">
                                    Moneda <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="currency"
                                    v-model="form.currency"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    required
                                >
                                    <option value="CLP">CLP - Peso Chileno</option>
                                    <option value="USD">USD - Dólar Estadounidense</option>
                                    <option value="EUR">EUR - Euro</option>
                                </select>
                                <p v-if="form.errors.currency" class="mt-1 text-sm text-red-600">{{ form.errors.currency }}</p>
                            </div>

                            <!-- Saldo inicial -->
                            <div>
                                <label for="current_balance" class="block text-sm font-medium text-gray-700">
                                    Saldo inicial
                                </label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">$</span>
                                    </div>
                                    <input
                                        id="current_balance"
                                        v-model="form.current_balance"
                                        type="number"
                                        step="0.01"
                                        class="pl-7 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        placeholder="0.00"
                                    />
                                </div>
                                <p v-if="form.errors.current_balance" class="mt-1 text-sm text-red-600">{{ form.errors.current_balance }}</p>
                                <p class="mt-1 text-xs text-gray-500">Este será el saldo inicial conciliado</p>
                            </div>
                        </div>

                        <!-- Notas -->
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700">
                                Notas
                            </label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                placeholder="Información adicional sobre esta cuenta..."
                            ></textarea>
                            <p v-if="form.errors.notes" class="mt-1 text-sm text-red-600">{{ form.errors.notes }}</p>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="mt-6 flex items-center justify-end space-x-3">
                        <Link :href="route('banking.accounts')" class="btn btn-secondary">
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="btn btn-primary"
                        >
                            <svg v-if="form.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ form.processing ? 'Guardando...' : 'Guardar Cuenta' }}
                        </button>
                    </div>
                </form>

                <!-- Información adicional -->
                <div class="mt-8 bg-blue-50 rounded-lg p-6">
                    <h3 class="text-sm font-medium text-blue-900">Consejos para la conciliación bancaria</h3>
                    <ul class="mt-2 text-sm text-blue-700 space-y-1">
                        <li>• Asegúrate de ingresar el saldo actual correcto de tu cuenta bancaria</li>
                        <li>• El número de cuenta es opcional pero útil para identificar la cuenta</li>
                        <li>• Podrás importar extractos bancarios después de crear la cuenta</li>
                        <li>• La conciliación se realiza comparando las transacciones del sistema con las del banco</li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    bank_name: '',
    account_number: '',
    account_type: '',
    currency: 'CLP',
    current_balance: '',
    notes: ''
});

const submit = () => {
    form.post(route('banking.accounts.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        }
    });
};
</script>