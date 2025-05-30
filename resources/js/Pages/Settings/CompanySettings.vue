<template>
    <AuthenticatedLayout>
        <Head title="Configuración de Empresa" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="mb-6">
                    <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">
                        Configuración de Empresa
                    </h1>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                        Administra la información y configuración de tu empresa
                    </p>
                </div>

                <!-- Tabs -->
                <div class="mb-6">
                    <nav class="flex space-x-4" aria-label="Tabs">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            :class="[
                                activeTab === tab.id
                                    ? 'bg-indigo-100 text-indigo-700'
                                    : 'text-gray-500 hover:text-gray-700',
                                'px-3 py-2 font-medium text-sm rounded-md'
                            ]"
                        >
                            {{ tab.name }}
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div v-show="activeTab === 'basic'">
                    <Card class="mb-6">
                        <form @submit.prevent="updateBasicInfo" class="p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-6">
                                Información Básica
                            </h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="name" value="Nombre Comercial" />
                                    <TextInput
                                        id="name"
                                        v-model="basicInfoForm.name"
                                        type="text"
                                        required
                                    />
                                    <InputError :message="basicInfoForm.errors.name" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="legal_name" value="Razón Social" />
                                    <TextInput
                                        id="legal_name"
                                        v-model="basicInfoForm.legal_name"
                                        type="text"
                                    />
                                    <InputError :message="basicInfoForm.errors.legal_name" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="trade_name" value="Nombre de Fantasía" />
                                    <TextInput
                                        id="trade_name"
                                        v-model="basicInfoForm.trade_name"
                                        type="text"
                                    />
                                    <InputError :message="basicInfoForm.errors.trade_name" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="tax_id" value="RUT" />
                                    <TextInput
                                        id="tax_id"
                                        v-model="basicInfoForm.tax_id"
                                        type="text"
                                        placeholder="12.345.678-9"
                                        required
                                    />
                                    <InputError :message="basicInfoForm.errors.tax_id" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="industry" value="Industria" />
                                    <select
                                        id="industry"
                                        v-model="basicInfoForm.industry"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                    >
                                        <option value="">Seleccionar industria</option>
                                        <option
                                            v-for="(label, value) in industries"
                                            :key="value"
                                            :value="value"
                                        >
                                            {{ label }}
                                        </option>
                                    </select>
                                    <InputError :message="basicInfoForm.errors.industry" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="website" value="Sitio Web" />
                                    <TextInput
                                        id="website"
                                        v-model="basicInfoForm.website"
                                        type="url"
                                        placeholder="https://ejemplo.com"
                                    />
                                    <InputError :message="basicInfoForm.errors.website" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="email" value="Email" />
                                    <TextInput
                                        id="email"
                                        v-model="basicInfoForm.email"
                                        type="email"
                                        required
                                    />
                                    <InputError :message="basicInfoForm.errors.email" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="phone" value="Teléfono" />
                                    <TextInput
                                        id="phone"
                                        v-model="basicInfoForm.phone"
                                        type="tel"
                                    />
                                    <InputError :message="basicInfoForm.errors.phone" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="mobile" value="Móvil" />
                                    <TextInput
                                        id="mobile"
                                        v-model="basicInfoForm.mobile"
                                        type="tel"
                                    />
                                    <InputError :message="basicInfoForm.errors.mobile" class="mt-2" />
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <PrimaryButton :disabled="basicInfoForm.processing">
                                    Guardar Información Básica
                                </PrimaryButton>
                            </div>
                        </form>
                    </Card>

                    <!-- Logo -->
                    <Card>
                        <div class="p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-6">
                                Logo de la Empresa
                            </h2>

                            <div class="flex items-center space-x-6">
                                <div>
                                    <div v-if="tenant.logo_path" class="mb-4">
                                        <img
                                            :src="tenant.logo_url"
                                            alt="Logo actual"
                                            class="h-32 w-auto object-contain"
                                        />
                                    </div>
                                    <div v-else class="w-32 h-32 bg-gray-200 rounded-lg flex items-center justify-center">
                                        <span class="text-gray-400">Sin logo</span>
                                    </div>
                                </div>

                                <div class="flex-1">
                                    <form @submit.prevent="updateLogo">
                                        <input
                                            ref="logoInput"
                                            type="file"
                                            @change="handleLogoChange"
                                            accept="image/*"
                                            class="hidden"
                                        />
                                        <div class="flex space-x-3">
                                            <SecondaryButton
                                                type="button"
                                                @click="$refs.logoInput.click()"
                                            >
                                                Seleccionar Logo
                                            </SecondaryButton>
                                            <PrimaryButton
                                                v-if="selectedLogo"
                                                :disabled="logoForm.processing"
                                            >
                                                Subir Logo
                                            </PrimaryButton>
                                            <DangerButton
                                                v-if="tenant.logo_path"
                                                type="button"
                                                @click="removeLogo"
                                            >
                                                Eliminar Logo
                                            </DangerButton>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-500">
                                            PNG, JPG o SVG. Máximo 2MB.
                                        </p>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>

                <!-- Dirección -->
                <div v-show="activeTab === 'address'">
                    <Card>
                        <form @submit.prevent="updateAddress" class="p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-6">
                                Dirección
                            </h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="md:col-span-2">
                                    <InputLabel for="address" value="Dirección" />
                                    <TextInput
                                        id="address"
                                        v-model="addressForm.address"
                                        type="text"
                                        required
                                    />
                                    <InputError :message="addressForm.errors.address" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="city" value="Ciudad" />
                                    <TextInput
                                        id="city"
                                        v-model="addressForm.city"
                                        type="text"
                                        required
                                    />
                                    <InputError :message="addressForm.errors.city" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="state" value="Región" />
                                    <TextInput
                                        id="state"
                                        v-model="addressForm.state"
                                        type="text"
                                        required
                                    />
                                    <InputError :message="addressForm.errors.state" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="postal_code" value="Código Postal" />
                                    <TextInput
                                        id="postal_code"
                                        v-model="addressForm.postal_code"
                                        type="text"
                                    />
                                    <InputError :message="addressForm.errors.postal_code" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="country" value="País" />
                                    <TextInput
                                        id="country"
                                        v-model="addressForm.country"
                                        type="text"
                                        required
                                    />
                                    <InputError :message="addressForm.errors.country" class="mt-2" />
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <PrimaryButton :disabled="addressForm.processing">
                                    Guardar Dirección
                                </PrimaryButton>
                            </div>
                        </form>
                    </Card>
                </div>

                <!-- Información Fiscal -->
                <div v-show="activeTab === 'fiscal'">
                    <Card>
                        <form @submit.prevent="updateFiscalInfo" class="p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-6">
                                Información Fiscal
                            </h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="tax_regime" value="Régimen Tributario" />
                                    <select
                                        id="tax_regime"
                                        v-model="fiscalForm.tax_regime"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                        required
                                    >
                                        <option value="">Seleccionar régimen</option>
                                        <option
                                            v-for="(label, value) in taxRegimes"
                                            :key="value"
                                            :value="value"
                                        >
                                            {{ label }}
                                        </option>
                                    </select>
                                    <InputError :message="fiscalForm.errors.tax_regime" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="economic_activity_code" value="Código Actividad Económica" />
                                    <select
                                        id="economic_activity_code"
                                        v-model="fiscalForm.economic_activity_code"
                                        @change="updateEconomicActivity"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                        required
                                    >
                                        <option value="">Seleccionar actividad</option>
                                        <option
                                            v-for="(label, code) in economicActivities"
                                            :key="code"
                                            :value="code"
                                        >
                                            {{ code }} - {{ label }}
                                        </option>
                                    </select>
                                    <InputError :message="fiscalForm.errors.economic_activity_code" class="mt-2" />
                                </div>

                                <div class="md:col-span-2">
                                    <InputLabel for="economic_activity" value="Giro Comercial" />
                                    <TextInput
                                        id="economic_activity"
                                        v-model="fiscalForm.economic_activity"
                                        type="text"
                                        required
                                    />
                                    <InputError :message="fiscalForm.errors.economic_activity" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="fiscal_year_start_month" value="Mes Inicio Año Fiscal" />
                                    <select
                                        id="fiscal_year_start_month"
                                        v-model="fiscalForm.fiscal_year_start_month"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                        required
                                    >
                                        <option :value="1">Enero</option>
                                        <option :value="2">Febrero</option>
                                        <option :value="3">Marzo</option>
                                        <option :value="4">Abril</option>
                                        <option :value="5">Mayo</option>
                                        <option :value="6">Junio</option>
                                        <option :value="7">Julio</option>
                                        <option :value="8">Agosto</option>
                                        <option :value="9">Septiembre</option>
                                        <option :value="10">Octubre</option>
                                        <option :value="11">Noviembre</option>
                                        <option :value="12">Diciembre</option>
                                    </select>
                                    <InputError :message="fiscalForm.errors.fiscal_year_start_month" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="branch_code" value="Código Sucursal" />
                                    <TextInput
                                        id="branch_code"
                                        v-model="fiscalForm.branch_code"
                                        type="text"
                                        placeholder="0 para casa matriz"
                                    />
                                    <InputError :message="fiscalForm.errors.branch_code" class="mt-2" />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="flex items-center">
                                        <Checkbox v-model="fiscalForm.is_holding" />
                                        <span class="ml-2 text-sm text-gray-600">
                                            Es empresa holding o casa matriz
                                        </span>
                                    </label>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="flex items-center">
                                        <Checkbox v-model="fiscalForm.uses_branch_offices" />
                                        <span class="ml-2 text-sm text-gray-600">
                                            Utiliza sucursales
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <PrimaryButton :disabled="fiscalForm.processing">
                                    Guardar Información Fiscal
                                </PrimaryButton>
                            </div>
                        </form>
                    </Card>
                </div>

                <!-- Personalización -->
                <div v-show="activeTab === 'branding'">
                    <Card class="mb-6">
                        <form @submit.prevent="updateBranding" class="p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-6">
                                Colores de Marca
                            </h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="primary_color" value="Color Primario" />
                                    <div class="flex items-center space-x-3">
                                        <input
                                            id="primary_color"
                                            v-model="brandingForm.primary_color"
                                            type="color"
                                            class="h-10 w-20"
                                        />
                                        <TextInput
                                            v-model="brandingForm.primary_color"
                                            type="text"
                                            pattern="^#[0-9A-Fa-f]{6}$"
                                            placeholder="#4F46E5"
                                        />
                                    </div>
                                    <InputError :message="brandingForm.errors.primary_color" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="secondary_color" value="Color Secundario" />
                                    <div class="flex items-center space-x-3">
                                        <input
                                            id="secondary_color"
                                            v-model="brandingForm.secondary_color"
                                            type="color"
                                            class="h-10 w-20"
                                        />
                                        <TextInput
                                            v-model="brandingForm.secondary_color"
                                            type="text"
                                            pattern="^#[0-9A-Fa-f]{6}$"
                                            placeholder="#10B981"
                                        />
                                    </div>
                                    <InputError :message="brandingForm.errors.secondary_color" class="mt-2" />
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <PrimaryButton :disabled="brandingForm.processing">
                                    Guardar Colores
                                </PrimaryButton>
                            </div>
                        </form>
                    </Card>

                    <!-- Configuración de Facturas -->
                    <Card>
                        <form @submit.prevent="updateInvoiceSettings" class="p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-6">
                                Configuración de Facturas
                            </h2>

                            <div class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <InputLabel for="invoice_prefix" value="Prefijo Facturas" />
                                        <TextInput
                                            id="invoice_prefix"
                                            v-model="invoiceSettingsForm.invoice_settings.invoice_prefix"
                                            type="text"
                                            placeholder="FAC"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel for="credit_note_prefix" value="Prefijo Notas de Crédito" />
                                        <TextInput
                                            id="credit_note_prefix"
                                            v-model="invoiceSettingsForm.invoice_settings.credit_note_prefix"
                                            type="text"
                                            placeholder="NC"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel for="debit_note_prefix" value="Prefijo Notas de Débito" />
                                        <TextInput
                                            id="debit_note_prefix"
                                            v-model="invoiceSettingsForm.invoice_settings.debit_note_prefix"
                                            type="text"
                                            placeholder="ND"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <InputLabel for="default_due_days" value="Días de Vencimiento por Defecto" />
                                    <TextInput
                                        id="default_due_days"
                                        v-model.number="invoiceSettingsForm.invoice_settings.default_due_days"
                                        type="number"
                                        min="0"
                                        max="365"
                                        required
                                    />
                                </div>

                                <div>
                                    <label class="flex items-center">
                                        <Checkbox v-model="invoiceSettingsForm.invoice_settings.show_logo" />
                                        <span class="ml-2 text-sm text-gray-600">
                                            Mostrar logo en facturas
                                        </span>
                                    </label>
                                </div>

                                <div>
                                    <label class="flex items-center">
                                        <Checkbox v-model="invoiceSettingsForm.invoice_settings.show_payment_instructions" />
                                        <span class="ml-2 text-sm text-gray-600">
                                            Mostrar instrucciones de pago
                                        </span>
                                    </label>
                                </div>

                                <div v-if="invoiceSettingsForm.invoice_settings.show_payment_instructions">
                                    <InputLabel for="payment_instructions" value="Instrucciones de Pago" />
                                    <textarea
                                        id="payment_instructions"
                                        v-model="invoiceSettingsForm.invoice_settings.payment_instructions"
                                        rows="3"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                    ></textarea>
                                </div>

                                <div>
                                    <InputLabel for="footer_text" value="Texto de Pie de Página" />
                                    <textarea
                                        id="footer_text"
                                        v-model="invoiceSettingsForm.invoice_settings.footer_text"
                                        rows="2"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                    ></textarea>
                                </div>

                                <div>
                                    <InputLabel for="terms_and_conditions" value="Términos y Condiciones" />
                                    <textarea
                                        id="terms_and_conditions"
                                        v-model="invoiceSettingsForm.invoice_settings.terms_and_conditions"
                                        rows="4"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                    ></textarea>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <PrimaryButton :disabled="invoiceSettingsForm.processing">
                                    Guardar Configuración
                                </PrimaryButton>
                            </div>
                        </form>
                    </Card>
                </div>

                <!-- Configuración Regional -->
                <div v-show="activeTab === 'regional'">
                    <Card>
                        <form @submit.prevent="updateRegionalSettings" class="p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-6">
                                Configuración Regional
                            </h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="currency" value="Moneda" />
                                    <select
                                        id="currency"
                                        v-model="regionalForm.currency"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                    >
                                        <option value="CLP">CLP - Peso Chileno</option>
                                        <option value="USD">USD - Dólar Americano</option>
                                        <option value="EUR">EUR - Euro</option>
                                        <option value="UF">UF - Unidad de Fomento</option>
                                    </select>
                                    <InputError :message="regionalForm.errors.currency" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="timezone" value="Zona Horaria" />
                                    <select
                                        id="timezone"
                                        v-model="regionalForm.timezone"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                    >
                                        <option
                                            v-for="(label, value) in timezones"
                                            :key="value"
                                            :value="value"
                                        >
                                            {{ label }}
                                        </option>
                                    </select>
                                    <InputError :message="regionalForm.errors.timezone" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="date_format" value="Formato de Fecha" />
                                    <select
                                        id="date_format"
                                        v-model="regionalForm.date_format"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                    >
                                        <option value="d/m/Y">DD/MM/AAAA</option>
                                        <option value="d-m-Y">DD-MM-AAAA</option>
                                        <option value="Y-m-d">AAAA-MM-DD</option>
                                        <option value="m/d/Y">MM/DD/AAAA</option>
                                    </select>
                                    <InputError :message="regionalForm.errors.date_format" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="time_format" value="Formato de Hora" />
                                    <select
                                        id="time_format"
                                        v-model="regionalForm.time_format"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                    >
                                        <option value="H:i">24 horas (13:30)</option>
                                        <option value="h:i A">12 horas (01:30 PM)</option>
                                    </select>
                                    <InputError :message="regionalForm.errors.time_format" class="mt-2" />
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <PrimaryButton :disabled="regionalForm.processing">
                                    Guardar Configuración Regional
                                </PrimaryButton>
                            </div>
                        </form>
                    </Card>
                </div>

                <!-- Plan y Límites -->
                <div v-show="activeTab === 'plan'">
                    <Card class="mb-6">
                        <div class="p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-6">
                                Plan Actual
                            </h2>

                            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-6">
                                <h3 class="text-xl font-semibold text-indigo-900 mb-2">
                                    {{ getPlanName(tenant.plan) }}
                                </h3>
                                <p class="text-indigo-700 mb-4">
                                    Estado: {{ tenant.subscription_status === 'active' ? 'Activo' : 'En prueba' }}
                                </p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <h4 class="font-medium text-indigo-900 mb-2">Límites</h4>
                                        <ul class="space-y-1 text-sm text-indigo-700">
                                            <li>Usuarios: {{ limits.users === -1 ? 'Ilimitado' : limits.users }}</li>
                                            <li>Documentos/mes: {{ limits.documents_per_month === -1 ? 'Ilimitado' : limits.documents_per_month }}</li>
                                            <li>Productos: {{ limits.products === -1 ? 'Ilimitado' : limits.products }}</li>
                                            <li>Clientes: {{ limits.customers === -1 ? 'Ilimitado' : limits.customers }}</li>
                                        </ul>
                                    </div>

                                    <div>
                                        <h4 class="font-medium text-indigo-900 mb-2">Características</h4>
                                        <ul class="space-y-1 text-sm text-indigo-700">
                                            <li v-if="limits.api_access">✓ Acceso API</li>
                                            <li v-if="limits.multi_branch">✓ Multi-sucursal</li>
                                            <li v-if="tenant.sii_certification_completed">✓ Certificación SII</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Card>

                    <!-- Uso Actual -->
                    <Card>
                        <div class="p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-6">
                                Uso Actual
                            </h2>

                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-700">Usuarios</span>
                                        <span class="text-sm text-gray-500">
                                            {{ usage.users }} / {{ limits.users === -1 ? '∞' : limits.users }}
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            class="bg-indigo-600 h-2 rounded-full"
                                            :style="`width: ${getUsagePercentage('users')}%`"
                                        ></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-700">Documentos este mes</span>
                                        <span class="text-sm text-gray-500">
                                            {{ usage.documents_this_month }} / {{ limits.documents_per_month === -1 ? '∞' : limits.documents_per_month }}
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            class="bg-indigo-600 h-2 rounded-full"
                                            :style="`width: ${getUsagePercentage('documents_this_month')}%`"
                                        ></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-700">Productos</span>
                                        <span class="text-sm text-gray-500">
                                            {{ usage.products }} / {{ limits.products === -1 ? '∞' : limits.products }}
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            class="bg-indigo-600 h-2 rounded-full"
                                            :style="`width: ${getUsagePercentage('products')}%`"
                                        ></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-700">Clientes</span>
                                        <span class="text-sm text-gray-500">
                                            {{ usage.customers }} / {{ limits.customers === -1 ? '∞' : limits.customers }}
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            class="bg-indigo-600 h-2 rounded-full"
                                            :style="`width: ${getUsagePercentage('customers')}%`"
                                        ></div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 pt-6 border-t">
                                <p class="text-sm text-gray-500">
                                    Para cambiar de plan o aumentar tus límites, contacta con soporte.
                                </p>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/UI/Card.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    tenant: Object,
    industries: Object,
    taxRegimes: Object,
    economicActivities: Object,
    timezones: Object,
    plans: Object,
    usage: Object,
    limits: Object,
});

const activeTab = ref('basic');
const selectedLogo = ref(null);

const tabs = [
    { id: 'basic', name: 'Información Básica' },
    { id: 'address', name: 'Dirección' },
    { id: 'fiscal', name: 'Información Fiscal' },
    { id: 'branding', name: 'Personalización' },
    { id: 'regional', name: 'Configuración Regional' },
    { id: 'plan', name: 'Plan y Límites' },
];

// Forms
const basicInfoForm = useForm({
    name: props.tenant.name || '',
    legal_name: props.tenant.legal_name || '',
    trade_name: props.tenant.trade_name || '',
    tax_id: props.tenant.tax_id || '',
    industry: props.tenant.industry || '',
    website: props.tenant.website || '',
    email: props.tenant.email || '',
    phone: props.tenant.phone || '',
    mobile: props.tenant.mobile || '',
});

const addressForm = useForm({
    address: props.tenant.address || '',
    city: props.tenant.city || '',
    state: props.tenant.state || '',
    postal_code: props.tenant.postal_code || '',
    country: props.tenant.country || 'Chile',
});

const fiscalForm = useForm({
    tax_regime: props.tenant.tax_regime || '',
    economic_activity: props.tenant.economic_activity || '',
    economic_activity_code: props.tenant.economic_activity_code || '',
    is_holding: props.tenant.is_holding || false,
    uses_branch_offices: props.tenant.uses_branch_offices || false,
    branch_code: props.tenant.branch_code || '0',
    fiscal_year_start_month: props.tenant.fiscal_year_start_month || 1,
});

const brandingForm = useForm({
    primary_color: props.tenant.primary_color || '#4F46E5',
    secondary_color: props.tenant.secondary_color || '#10B981',
});

const invoiceSettingsForm = useForm({
    invoice_settings: props.tenant.invoice_settings || {
        show_logo: true,
        show_payment_instructions: true,
        payment_instructions: '',
        footer_text: '',
        terms_and_conditions: '',
        default_due_days: 30,
        invoice_prefix: 'FAC',
        credit_note_prefix: 'NC',
        debit_note_prefix: 'ND',
    },
});

const regionalForm = useForm({
    currency: props.tenant.currency || 'CLP',
    timezone: props.tenant.timezone || 'America/Santiago',
    date_format: props.tenant.date_format || 'd/m/Y',
    time_format: props.tenant.time_format || 'H:i',
});

const logoForm = useForm({
    logo: null,
});

// Methods
const updateBasicInfo = () => {
    basicInfoForm.post(route('company-settings.update-basic'), {
        preserveScroll: true,
    });
};

const updateAddress = () => {
    addressForm.post(route('company-settings.update-address'), {
        preserveScroll: true,
    });
};

const updateFiscalInfo = () => {
    fiscalForm.post(route('company-settings.update-fiscal'), {
        preserveScroll: true,
    });
};

const updateBranding = () => {
    brandingForm.post(route('company-settings.update-branding'), {
        preserveScroll: true,
    });
};

const updateInvoiceSettings = () => {
    invoiceSettingsForm.post(route('company-settings.update-invoice-settings'), {
        preserveScroll: true,
    });
};

const updateRegionalSettings = () => {
    regionalForm.post(route('company-settings.update-regional'), {
        preserveScroll: true,
    });
};

const handleLogoChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        selectedLogo.value = file;
        logoForm.logo = file;
    }
};

const updateLogo = () => {
    if (selectedLogo.value) {
        logoForm.post(route('company-settings.update-logo'), {
            preserveScroll: true,
            onSuccess: () => {
                selectedLogo.value = null;
                logoForm.reset();
            },
        });
    }
};

const removeLogo = () => {
    if (confirm('¿Estás seguro de eliminar el logo?')) {
        router.delete(route('company-settings.remove-logo'), {
            preserveScroll: true,
        });
    }
};

const updateEconomicActivity = () => {
    const selected = props.economicActivities[fiscalForm.economic_activity_code];
    if (selected) {
        fiscalForm.economic_activity = selected;
    }
};

const getPlanName = (plan) => {
    return props.plans[plan]?.name || 'Plan Desconocido';
};

const getUsagePercentage = (metric) => {
    const limit = metric === 'documents_this_month' 
        ? props.limits.documents_per_month 
        : props.limits[metric];
    
    if (limit === -1) return 0; // Ilimitado
    if (limit === 0) return 0;
    
    const used = props.usage[metric] || 0;
    return Math.min(100, Math.round((used / limit) * 100));
};
</script>