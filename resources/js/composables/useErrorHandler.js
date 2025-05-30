import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';

export function useErrorHandler() {
    const errors = ref({});
    const errorMessage = ref('');
    const errorId = ref('');
    const isLoading = ref(false);
    const hasError = ref(false);

    // Estado para múltiples alertas
    const alerts = reactive([]);

    /**
     * Manejar errores de respuesta
     */
    const handleError = (error) => {
        console.error('Error caught:', error);
        
        if (error.response) {
            const status = error.response.status;
            const data = error.response.data;

            // Manejar diferentes tipos de errores
            switch (status) {
                case 422:
                    handleValidationError(data);
                    break;
                case 401:
                    handleAuthenticationError();
                    break;
                case 403:
                    handleAuthorizationError(data);
                    break;
                case 404:
                    handleNotFoundError(data);
                    break;
                case 429:
                    handleRateLimitError(data);
                    break;
                case 500:
                case 502:
                case 503:
                    handleServerError(data);
                    break;
                default:
                    handleGenericError(data);
            }
        } else if (error.request) {
            // Error de red
            handleNetworkError();
        } else {
            // Error de configuración
            handleGenericError({ message: error.message });
        }

        hasError.value = true;
    };

    /**
     * Manejar errores de validación
     */
    const handleValidationError = (data) => {
        errors.value = data.errors || {};
        errorMessage.value = data.message || 'Por favor, corrige los errores en el formulario.';
        
        addAlert({
            type: 'error',
            title: 'Error de validación',
            message: errorMessage.value,
            errors: errors.value,
            dismissible: true
        });
    };

    /**
     * Manejar errores de autenticación
     */
    const handleAuthenticationError = () => {
        errorMessage.value = 'Tu sesión ha expirado. Por favor, inicia sesión nuevamente.';
        
        addAlert({
            type: 'warning',
            title: 'Sesión expirada',
            message: errorMessage.value,
            actions: [
                {
                    label: 'Iniciar sesión',
                    handler: () => router.visit('/login')
                }
            ],
            dismissible: false
        });

        // Redirigir después de 3 segundos
        setTimeout(() => {
            router.visit('/login');
        }, 3000);
    };

    /**
     * Manejar errores de autorización
     */
    const handleAuthorizationError = (data) => {
        errorMessage.value = data.message || 'No tienes permisos para realizar esta acción.';
        errorId.value = data.error?.id || '';
        
        addAlert({
            type: 'error',
            title: 'Acceso denegado',
            message: errorMessage.value,
            errorId: errorId.value,
            dismissible: true
        });
    };

    /**
     * Manejar errores 404
     */
    const handleNotFoundError = (data) => {
        errorMessage.value = data.message || 'El recurso solicitado no fue encontrado.';
        
        addAlert({
            type: 'warning',
            title: 'No encontrado',
            message: errorMessage.value,
            actions: [
                {
                    label: 'Volver',
                    handler: () => window.history.back()
                }
            ],
            dismissible: true
        });
    };

    /**
     * Manejar errores de límite de rate
     */
    const handleRateLimitError = (data) => {
        const retryAfter = data.retry_after || 60;
        errorMessage.value = `Has realizado demasiadas solicitudes. Por favor, espera ${retryAfter} segundos.`;
        
        addAlert({
            type: 'warning',
            title: 'Límite de solicitudes',
            message: errorMessage.value,
            dismissible: true,
            autoDismiss: retryAfter
        });
    };

    /**
     * Manejar errores del servidor
     */
    const handleServerError = (data) => {
        errorMessage.value = data.error?.message || 'Ha ocurrido un error en el servidor. Por favor, intenta más tarde.';
        errorId.value = data.error?.id || '';
        
        addAlert({
            type: 'error',
            title: 'Error del servidor',
            message: errorMessage.value,
            errorId: errorId.value,
            actions: [
                {
                    label: 'Reintentar',
                    handler: () => window.location.reload()
                },
                {
                    label: 'Contactar soporte',
                    handler: () => window.open('mailto:soporte@crecepyme.cl?subject=Error ' + errorId.value)
                }
            ],
            dismissible: true
        });
    };

    /**
     * Manejar errores de red
     */
    const handleNetworkError = () => {
        errorMessage.value = 'No se pudo conectar con el servidor. Verifica tu conexión a internet.';
        
        addAlert({
            type: 'error',
            title: 'Error de conexión',
            message: errorMessage.value,
            actions: [
                {
                    label: 'Reintentar',
                    handler: () => window.location.reload()
                }
            ],
            dismissible: true
        });
    };

    /**
     * Manejar errores genéricos
     */
    const handleGenericError = (data) => {
        errorMessage.value = data.message || 'Ha ocurrido un error inesperado.';
        errorId.value = data.error?.id || '';
        
        addAlert({
            type: 'error',
            title: 'Error',
            message: errorMessage.value,
            errorId: errorId.value,
            dismissible: true
        });
    };

    /**
     * Agregar una alerta
     */
    const addAlert = (alert) => {
        const id = Date.now() + Math.random();
        alerts.push({ ...alert, id });
        
        // Auto-eliminar después de cierto tiempo si no se especifica autoDismiss
        if (!alert.autoDismiss && alert.dismissible !== false) {
            setTimeout(() => removeAlert(id), 10000); // 10 segundos por defecto
        }
    };

    /**
     * Eliminar una alerta
     */
    const removeAlert = (id) => {
        const index = alerts.findIndex(alert => alert.id === id);
        if (index > -1) {
            alerts.splice(index, 1);
        }
    };

    /**
     * Limpiar todos los errores
     */
    const clearErrors = () => {
        errors.value = {};
        errorMessage.value = '';
        errorId.value = '';
        hasError.value = false;
        alerts.splice(0, alerts.length);
    };

    /**
     * Wrapper para operaciones asíncronas con manejo de errores
     */
    const withErrorHandling = async (callback, options = {}) => {
        isLoading.value = true;
        clearErrors();
        
        try {
            const result = await callback();
            
            // Mostrar mensaje de éxito si se proporciona
            if (options.successMessage) {
                addAlert({
                    type: 'success',
                    title: 'Éxito',
                    message: options.successMessage,
                    dismissible: true,
                    autoDismiss: 5
                });
            }
            
            return result;
        } catch (error) {
            handleError(error);
            
            // Ejecutar callback de error si se proporciona
            if (options.onError) {
                options.onError(error);
            }
            
            throw error;
        } finally {
            isLoading.value = false;
        }
    };

    /**
     * Validar un campo específico
     */
    const validateField = (field, value, rules) => {
        // Aquí se podría implementar validación del lado del cliente
        // Por ahora, solo limpia el error del campo
        if (errors.value[field]) {
            delete errors.value[field];
        }
    };

    return {
        errors,
        errorMessage,
        errorId,
        isLoading,
        hasError,
        alerts,
        handleError,
        clearErrors,
        withErrorHandling,
        validateField,
        addAlert,
        removeAlert
    };
}