<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Messages Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used throughout the application for
    | various messages that we need to display to the user.
    |
    */

    // General
    'welcome' => 'Bienvenido',
    'dashboard' => 'Panel de Control',
    'profile' => 'Perfil',
    'settings' => 'Configuración',
    'save' => 'Guardar',
    'cancel' => 'Cancelar',
    'delete' => 'Eliminar',
    'edit' => 'Editar',
    'create' => 'Crear',
    'update' => 'Actualizar',
    'view' => 'Ver',
    'search' => 'Buscar',
    'filter' => 'Filtrar',
    'export' => 'Exportar',
    'import' => 'Importar',
    'download' => 'Descargar',
    'upload' => 'Subir',
    'back' => 'Volver',
    'next' => 'Siguiente',
    'previous' => 'Anterior',
    'yes' => 'Sí',
    'no' => 'No',
    'confirm' => 'Confirmar',
    'close' => 'Cerrar',
    'loading' => 'Cargando...',
    'actions' => 'Acciones',
    'status' => 'Estado',
    'date' => 'Fecha',
    'total' => 'Total',
    'subtotal' => 'Subtotal',
    'tax' => 'Impuesto',
    'quantity' => 'Cantidad',
    'price' => 'Precio',
    'amount' => 'Monto',
    'description' => 'Descripción',
    'name' => 'Nombre',
    'email' => 'Correo Electrónico',
    'phone' => 'Teléfono',
    'address' => 'Dirección',
    'city' => 'Ciudad',
    'country' => 'País',
    'active' => 'Activo',
    'inactive' => 'Inactivo',
    'enabled' => 'Habilitado',
    'disabled' => 'Deshabilitado',

    // Success Messages
    'success' => [
        'created' => 'Registro creado exitosamente.',
        'updated' => 'Registro actualizado exitosamente.',
        'deleted' => 'Registro eliminado exitosamente.',
        'saved' => 'Cambios guardados exitosamente.',
        'sent' => 'Enviado exitosamente.',
        'uploaded' => 'Archivo subido exitosamente.',
        'processed' => 'Procesado exitosamente.',
        'operation_completed' => 'Operación completada exitosamente.',
    ],

    // Error Messages
    'error' => [
        'general' => 'Ha ocurrido un error. Por favor intente nuevamente.',
        'not_found' => 'Registro no encontrado.',
        'unauthorized' => 'No tiene permisos para realizar esta acción.',
        'validation_failed' => 'Los datos proporcionados no son válidos.',
        'file_upload_failed' => 'Error al subir el archivo.',
        'file_too_large' => 'El archivo es demasiado grande.',
        'invalid_format' => 'Formato de archivo no válido.',
        'operation_failed' => 'La operación ha fallado.',
        'connection_failed' => 'Error de conexión.',
        'timeout' => 'La operación ha tardado demasiado tiempo.',
    ],

    // Warning Messages
    'warning' => [
        'unsaved_changes' => 'Tiene cambios sin guardar. ¿Está seguro de que desea continuar?',
        'delete_confirmation' => '¿Está seguro de que desea eliminar este registro?',
        'irreversible_action' => 'Esta acción no se puede deshacer.',
    ],

    // Info Messages
    'info' => [
        'no_records' => 'No hay registros para mostrar.',
        'empty_list' => 'La lista está vacía.',
        'processing' => 'Procesando...',
        'please_wait' => 'Por favor espere...',
    ],

    // Navigation
    'navigation' => [
        'home' => 'Inicio',
        'dashboard' => 'Panel',
        'customers' => 'Clientes',
        'suppliers' => 'Proveedores',
        'products' => 'Productos',
        'inventory' => 'Inventario',
        'invoicing' => 'Facturación',
        'accounting' => 'Contabilidad',
        'reports' => 'Reportes',
        'settings' => 'Configuración',
        'users' => 'Usuarios',
        'roles' => 'Roles',
        'permissions' => 'Permisos',
    ],

    // Business
    'business' => [
        'tax_id' => 'RUT',
        'business_name' => 'Razón Social',
        'fantasy_name' => 'Nombre de Fantasía',
        'economic_activity' => 'Actividad Económica',
        'sii_resolution' => 'Resolución SII',
        'start_date' => 'Fecha de Inicio de Actividades',
    ],

    // Documents
    'documents' => [
        'invoice' => 'Factura',
        'ticket' => 'Boleta',
        'credit_note' => 'Nota de Crédito',
        'debit_note' => 'Nota de Débito',
        'purchase_order' => 'Orden de Compra',
        'delivery_note' => 'Guía de Despacho',
        'quote' => 'Cotización',
        'receipt' => 'Recibo',
    ],

    // Payment
    'payment' => [
        'cash' => 'Efectivo',
        'credit_card' => 'Tarjeta de Crédito',
        'debit_card' => 'Tarjeta de Débito',
        'bank_transfer' => 'Transferencia Bancaria',
        'check' => 'Cheque',
        'pending' => 'Pendiente',
        'paid' => 'Pagado',
        'partial' => 'Parcial',
        'overdue' => 'Vencido',
    ],

    // SII (Chilean Tax Service)
    'sii' => [
        'status_pending' => 'Pendiente',
        'status_accepted' => 'Aceptado',
        'status_rejected' => 'Rechazado',
        'status_sent' => 'Enviado',
        'status_error' => 'Error',
        'sending_to_sii' => 'Enviando al SII...',
        'validating_document' => 'Validando documento...',
        'generating_xml' => 'Generando XML...',
        'signing_document' => 'Firmando documento...',
    ],

    // Modules
    'modules' => [
        'core' => 'Núcleo',
        'accounting' => 'Contabilidad',
        'invoicing' => 'Facturación',
        'inventory' => 'Inventario',
        'crm' => 'CRM',
        'hrm' => 'Recursos Humanos',
        'pos' => 'Punto de Venta',
        'ecommerce' => 'E-commerce',
        'banking' => 'Bancario',
        'analytics' => 'Analíticas',
    ],

    // Subscription
    'subscription' => [
        'plan' => 'Plan',
        'basic' => 'Básico',
        'professional' => 'Profesional',
        'enterprise' => 'Empresarial',
        'active' => 'Activa',
        'inactive' => 'Inactiva',
        'expired' => 'Expirada',
        'trial' => 'Prueba',
        'upgrade' => 'Mejorar Plan',
        'downgrade' => 'Reducir Plan',
    ],

    // File Management
    'files' => [
        'upload_file' => 'Subir Archivo',
        'select_file' => 'Seleccionar Archivo',
        'file_uploaded' => 'Archivo subido exitosamente',
        'file_deleted' => 'Archivo eliminado exitosamente',
        'download_file' => 'Descargar Archivo',
        'file_not_found' => 'Archivo no encontrado',
        'invalid_file_type' => 'Tipo de archivo no válido',
        'file_too_large' => 'El archivo es demasiado grande',
        'max_file_size' => 'Tamaño máximo de archivo: :size MB',
    ],

];