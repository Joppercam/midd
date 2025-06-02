<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { ArrowLeftIcon, CogIcon, GlobeAltIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    settings: Object,
});

const form = useForm({
    global_rate_limit: props.settings.global_rate_limit || 1000,
    webhook_url: props.settings.webhook_url || '',
    webhook_secret: '',
    enable_webhooks: props.settings.enable_webhooks || false,
    api_documentation_public: props.settings.api_documentation_public || false,
});

const generateWebhookSecret = () => {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let result = '';
    for (let i = 0; i < 32; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    form.webhook_secret = result;
};

const submit = () => {
    form.post(route('api-management.settings.update'), {
        onSuccess: () => {
            // Could show success message
        },
    });
};
</script>

<template>
    <Head title="Configuración de API" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center space-x-4">
                <SecondaryButton @click="router.visit(route('api-management.index'))">
                    <ArrowLeftIcon class="w-4 h-4 mr-2" />
                    Volver
                </SecondaryButton>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Configuración de API
                    </h2>
                    <p class="text-gray-600 text-sm mt-1">
                        Configura los parámetros globales de la API y webhooks
                    </p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-8">
                    <!-- Rate Limiting Settings -->
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex items-center">
                                <CogIcon class="h-6 w-6 text-gray-600 mr-3" />
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">
                                        Límites de Velocidad
                                    </h3>
                                    <p class="text-sm text-gray-600">
                                        Configura los límites globales de requests por minuto
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="global_rate_limit" value="Límite Global por Minuto" />
                                    <TextInput
                                        id="global_rate_limit"
                                        v-model="form.global_rate_limit"
                                        type="number"
                                        class="mt-1 block w-full"
                                        min="100"
                                        max="10000"
                                        required
                                    />
                                    <p class="text-sm text-gray-500 mt-1">
                                        Número máximo de requests permitidos por minuto para todos los tokens
                                    </p>
                                    <div v-if="form.errors.global_rate_limit" class="text-red-600 text-sm mt-1">
                                        {{ form.errors.global_rate_limit }}
                                    </div>
                                </div>
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <h4 class="text-sm font-medium text-blue-900 mb-2">Recomendaciones</h4>
                                    <ul class="text-sm text-blue-800 space-y-1">
                                        <li>• Uso ligero: 100-500 requests/min</li>
                                        <li>• Uso moderado: 500-2000 requests/min</li>
                                        <li>• Uso intensivo: 2000+ requests/min</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Webhook Settings -->
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex items-center">
                                <GlobeAltIcon class="h-6 w-6 text-gray-600 mr-3" />
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">
                                        Configuración de Webhooks
                                    </h3>
                                    <p class="text-sm text-gray-600">
                                        Configura las notificaciones automáticas para eventos de la API
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="p-6 space-y-6">
                            <div class="flex items-center">
                                <Checkbox
                                    id="enable_webhooks"
                                    v-model:checked="form.enable_webhooks"
                                />
                                <div class="ml-3">
                                    <label for="enable_webhooks" class="text-sm font-medium text-gray-700">
                                        Habilitar Webhooks
                                    </label>
                                    <p class="text-sm text-gray-500">
                                        Permite el envío de notificaciones automáticas a sistemas externos
                                    </p>
                                </div>
                            </div>

                            <div v-if="form.enable_webhooks" class="space-y-4 pl-7">
                                <div>
                                    <InputLabel for="webhook_url" value="URL del Webhook" />
                                    <TextInput
                                        id="webhook_url"
                                        v-model="form.webhook_url"
                                        type="url"
                                        class="mt-1 block w-full"
                                        placeholder="https://tu-servidor.com/webhook/crecepyme"
                                    />
                                    <p class="text-sm text-gray-500 mt-1">
                                        URL donde se enviarán las notificaciones de eventos
                                    </p>
                                    <div v-if="form.errors.webhook_url" class="text-red-600 text-sm mt-1">
                                        {{ form.errors.webhook_url }}
                                    </div>
                                </div>

                                <div>
                                    <InputLabel for="webhook_secret" value="Secreto del Webhook" />
                                    <div class="flex mt-1">
                                        <TextInput
                                            id="webhook_secret"
                                            v-model="form.webhook_secret"
                                            type="password"
                                            class="block w-full rounded-r-none"
                                            placeholder="Introduce un secreto o genera uno nuevo"
                                        />
                                        <SecondaryButton
                                            type="button"
                                            @click="generateWebhookSecret"
                                            class="rounded-l-none"
                                        >
                                            Generar
                                        </SecondaryButton>
                                    </div>
                                    <p class="text-sm text-gray-500 mt-1">
                                        Secreto usado para firmar las peticiones webhook (HMAC-SHA256)
                                    </p>
                                    <div v-if="form.errors.webhook_secret" class="text-red-600 text-sm mt-1">
                                        {{ form.errors.webhook_secret }}
                                    </div>
                                </div>

                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="text-sm font-medium text-gray-900 mb-2">Eventos Webhook Disponibles</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-gray-600">
                                        <div>• invoice.created</div>
                                        <div>• invoice.sent</div>
                                        <div>• invoice.paid</div>
                                        <div>• customer.created</div>
                                        <div>• customer.updated</div>
                                        <div>• product.created</div>
                                        <div>• product.low_stock</div>
                                        <div>• payment.received</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Documentation Settings -->
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex items-center">
                                <DocumentTextIcon class="h-6 w-6 text-gray-600 mr-3" />
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">
                                        Documentación de la API
                                    </h3>
                                    <p class="text-sm text-gray-600">
                                        Configura el acceso a la documentación de la API
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center">
                                <Checkbox
                                    id="api_documentation_public"
                                    v-model:checked="form.api_documentation_public"
                                />
                                <div class="ml-3">
                                    <label for="api_documentation_public" class="text-sm font-medium text-gray-700">
                                        Documentación Pública
                                    </label>
                                    <p class="text-sm text-gray-500">
                                        Permite acceso público a la documentación de la API (sin autenticación)
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-md p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-yellow-800">
                                            Nota de Seguridad
                                        </h3>
                                        <div class="mt-2 text-sm text-yellow-700">
                                            <p>
                                                La documentación pública puede revelar información sobre los endpoints disponibles.
                                                Solo habilítala si planeas ofrecer integración a terceros.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- API Information -->
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                Información de la API
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt class="font-medium text-gray-700">Base URL:</dt>
                                    <dd class="text-gray-600 font-mono">{{ $page.props.app_url }}/api/v1</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-gray-700">Autenticación:</dt>
                                    <dd class="text-gray-600">Bearer Token</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-gray-700">Formato:</dt>
                                    <dd class="text-gray-600">JSON</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-gray-700">Documentación:</dt>
                                    <dd>
                                        <a :href="route('api.info')" class="text-indigo-600 hover:text-indigo-500">
                                            Ver documentación completa
                                        </a>
                                    </dd>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="flex justify-end">
                        <PrimaryButton type="submit" :disabled="form.processing">
                            Guardar Configuración
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>