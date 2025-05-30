<template>
  <Head :title="`Factura ${invoice.number}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          {{ invoice.formatted_number }}
        </h2>
        <div class="flex items-center space-x-2">
          <Link
            v-if="invoice.status === 'draft'"
            :href="route('invoices.edit', invoice)"
            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
          >
            Editar
          </Link>
          <button
            v-if="invoice.status === 'draft'"
            @click="sendToSII"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
          >
            Enviar al SII
          </button>
          <button
            @click="showEmailModal = true"
            v-if="invoice.customer.email && invoice.status !== 'draft'"
            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            Enviar por Email
          </button>
          <button
            @click="downloadPDF"
            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Descargar PDF
          </button>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <!-- Estado -->
            <div class="mb-6 flex items-center justify-between">
              <div>
                <span
                  :class="[
                    'px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full',
                    statusClasses[invoice.status]
                  ]"
                >
                  {{ statusLabels[invoice.status] }}
                </span>
                <span v-if="invoice.sii_status" class="ml-2">
                  <span
                    :class="[
                      'px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full',
                      siiStatusClasses[invoice.sii_status]
                    ]"
                  >
                    SII: {{ siiStatusLabels[invoice.sii_status] }}
                  </span>
                </span>
                <span v-if="invoice.sii_track_id" class="ml-2 text-sm text-gray-500">
                  Track ID: {{ invoice.sii_track_id }}
                </span>
              </div>
              
              <div class="flex space-x-2">
                <SecondaryButton 
                  v-if="invoice.sii_status === 'pending' && invoice.status !== 'draft'"
                  @click="sendToSII"
                  :disabled="sendingToSII"
                >
                  {{ sendingToSII ? 'Enviando...' : 'Enviar al SII' }}
                </SecondaryButton>
                
                <SecondaryButton 
                  v-if="invoice.sii_track_id && ['sent', 'processing'].includes(invoice.sii_status)"
                  @click="checkSIIStatus"
                  :disabled="checkingStatus"
                >
                  {{ checkingStatus ? 'Consultando...' : 'Verificar Estado SII' }}
                </SecondaryButton>
              </div>
            </div>

            <!-- Información del documento -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
              <div>
                <h3 class="text-lg font-medium text-gray-900 mb-4">Información del Documento</h3>
                <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                  <div>
                    <dt class="text-sm font-medium text-gray-500">Tipo</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ documentTypes[invoice.type] }}</dd>
                  </div>
                  <div>
                    <dt class="text-sm font-medium text-gray-500">Número</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ invoice.number }}</dd>
                  </div>
                  <div>
                    <dt class="text-sm font-medium text-gray-500">Fecha de Emisión</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ formatDate(invoice.issue_date) }}</dd>
                  </div>
                  <div>
                    <dt class="text-sm font-medium text-gray-500">Fecha de Vencimiento</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                      <span :class="{ 'text-red-600 font-medium': isOverdue }">
                        {{ formatDate(invoice.due_date) }}
                      </span>
                    </dd>
                  </div>
                </dl>
              </div>

              <div>
                <h3 class="text-lg font-medium text-gray-900 mb-4">Información del Cliente</h3>
                <dl class="grid grid-cols-1 gap-x-4 gap-y-4">
                  <div>
                    <dt class="text-sm font-medium text-gray-500">Nombre</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ invoice.customer.name }}</dd>
                  </div>
                  <div>
                    <dt class="text-sm font-medium text-gray-500">RUT</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ invoice.customer.formatted_rut }}</dd>
                  </div>
                  <div v-if="invoice.customer.email">
                    <dt class="text-sm font-medium text-gray-500">Email</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ invoice.customer.email }}</dd>
                  </div>
                  <div v-if="invoice.customer.phone">
                    <dt class="text-sm font-medium text-gray-500">Teléfono</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ invoice.customer.phone }}</dd>
                  </div>
                </dl>
              </div>
            </div>

            <!-- Detalle de items -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Detalle</h3>
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Descripción
                      </th>
                      <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Cantidad
                      </th>
                      <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Precio Unit.
                      </th>
                      <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Total
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="item in invoice.items" :key="item.id">
                      <td class="px-6 py-4 text-sm text-gray-900">
                        {{ item.description }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                        {{ formatNumber(item.quantity) }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                        ${{ formatCurrency(item.unit_price) }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                        ${{ formatCurrency(item.total) }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Totales -->
            <div class="flex justify-end">
              <div class="w-full max-w-xs">
                <div class="flex justify-between mb-2">
                  <span class="text-gray-600">Subtotal:</span>
                  <span class="font-medium">${{ formatCurrency(invoice.subtotal) }}</span>
                </div>
                <div class="flex justify-between mb-2">
                  <span class="text-gray-600">IVA (19%):</span>
                  <span class="font-medium">${{ formatCurrency(invoice.tax_amount) }}</span>
                </div>
                <div class="flex justify-between text-lg font-bold border-t pt-2">
                  <span>Total:</span>
                  <span>${{ formatCurrency(invoice.total) }}</span>
                </div>
              </div>
            </div>

            <!-- Información de pago -->
            <div v-if="invoice.paid_at" class="mt-8 p-4 bg-green-50 rounded-lg">
              <div class="flex">
                <div class="flex-shrink-0">
                  <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="ml-3">
                  <p class="text-sm text-green-800">
                    Pagado el {{ formatDate(invoice.paid_at) }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de envío por email -->
    <Modal :show="showEmailModal" @close="showEmailModal = false">
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900">
          Enviar Factura por Email
        </h2>

        <form @submit.prevent="sendEmail" class="mt-6 space-y-6">
          <div>
            <InputLabel for="recipient_email" value="Email del destinatario" />
            <TextInput
              id="recipient_email"
              v-model="emailForm.recipient_email"
              type="email"
              class="mt-1 block w-full"
              :disabled="sendingEmail"
            />
            <InputError :message="emailForm.errors.recipient_email" class="mt-2" />
          </div>

          <div>
            <InputLabel for="custom_message" value="Mensaje personalizado (opcional)" />
            <textarea
              id="custom_message"
              v-model="emailForm.custom_message"
              rows="4"
              class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
              placeholder="Escriba un mensaje personalizado para incluir en el email..."
              :disabled="sendingEmail"
            ></textarea>
            <InputError :message="emailForm.errors.custom_message" class="mt-2" />
          </div>

          <div>
            <label class="flex items-center">
              <Checkbox
                v-model:checked="emailForm.attach_pdf"
                :disabled="sendingEmail"
              />
              <span class="ml-2 text-sm text-gray-600">
                Adjuntar PDF de la factura
              </span>
            </label>
          </div>

          <div class="bg-blue-50 p-4 rounded-md">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="ml-3 flex-1">
                <p class="text-sm text-blue-700">
                  El email será enviado desde la dirección configurada en su cuenta.
                </p>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-4">
            <PrimaryButton :disabled="sendingEmail">
              {{ sendingEmail ? 'Enviando...' : 'Enviar Email' }}
            </PrimaryButton>
            <SecondaryButton @click="showEmailModal = false" :disabled="sendingEmail">
              Cancelar
            </SecondaryButton>
          </div>
        </form>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
  invoice: Object,
});

const documentTypes = {
  invoice: 'Factura Electrónica',
  receipt: 'Boleta Electrónica',
  credit_note: 'Nota de Crédito',
  debit_note: 'Nota de Débito',
};

const statusLabels = {
  draft: 'Borrador',
  sent: 'Enviado',
  accepted: 'Aceptado',
  rejected: 'Rechazado',
  cancelled: 'Anulado',
};

const statusClasses = {
  draft: 'bg-gray-100 text-gray-800',
  sent: 'bg-blue-100 text-blue-800',
  accepted: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800',
  cancelled: 'bg-yellow-100 text-yellow-800',
};

const siiStatusLabels = {
  pending: 'Pendiente',
  sent: 'Enviado',
  processing: 'Procesando',
  accepted: 'Aceptado',
  rejected: 'Rechazado',
  error: 'Error',
};

const siiStatusClasses = {
  pending: 'bg-gray-100 text-gray-800',
  sent: 'bg-blue-100 text-blue-800',
  processing: 'bg-yellow-100 text-yellow-800',
  accepted: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800',
  error: 'bg-red-100 text-red-800',
};

const sendingToSII = ref(false);
const checkingStatus = ref(false);
const showEmailModal = ref(false);
const sendingEmail = ref(false);

const emailForm = useForm({
  recipient_email: props.invoice.customer?.email || '',
  custom_message: '',
  attach_pdf: true,
});

const isOverdue = computed(() => {
  return props.invoice.status === 'accepted' && 
         !props.invoice.paid_at && 
         new Date(props.invoice.due_date) < new Date();
});

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('es-CL', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
};

const formatCurrency = (value) => {
  return new Intl.NumberFormat('es-CL', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value || 0);
};

const formatNumber = (value) => {
  return new Intl.NumberFormat('es-CL', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(value || 0);
};

const sendToSII = async () => {
  if (!confirm('¿Está seguro de enviar este documento al SII?')) {
    return;
  }
  
  sendingToSII.value = true;
  try {
    await router.post(route('sii.send', props.invoice), {}, {
      preserveScroll: true,
      onSuccess: () => {
        sendingToSII.value = false;
      },
      onError: () => {
        sendingToSII.value = false;
      },
    });
  } catch (error) {
    sendingToSII.value = false;
  }
};

const checkSIIStatus = async () => {
  checkingStatus.value = true;
  try {
    const response = await axios.get(route('sii.check-status', props.invoice));
    if (response.data.success) {
      router.reload({ only: ['invoice'] });
    }
  } catch (error) {
    console.error('Error checking SII status:', error);
  } finally {
    checkingStatus.value = false;
  }
};

const downloadPDF = () => {
  window.open(route('invoices.download', props.invoice), '_blank');
};

const sendEmail = () => {
  sendingEmail.value = true;
  
  emailForm.post(route('emails.send-invoice', props.invoice), {
    preserveScroll: true,
    onSuccess: () => {
      showEmailModal.value = false;
      emailForm.reset();
      sendingEmail.value = false;
    },
    onError: () => {
      sendingEmail.value = false;
    },
  });
};
</script>