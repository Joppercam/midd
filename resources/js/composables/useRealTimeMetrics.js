import { ref, reactive, onMounted, onUnmounted, computed } from 'vue';
import axios from 'axios';

export function useRealTimeMetrics(options = {}) {
    // Configuración por defecto
    const config = {
        updateInterval: 60000, // 1 minuto
        retryAttempts: 3,
        retryDelay: 5000,
        autoStart: true,
        enableWebSocket: false, // Para futuras implementaciones
        ...options
    };

    // Estado reactivo
    const metrics = ref({});
    const charts = ref({});
    const notifications = ref([]);
    const isLoading = ref(false);
    const isConnected = ref(false);
    const lastUpdated = ref(null);
    const error = ref(null);
    const retryCount = ref(0);

    // Intervalos y timeouts
    let updateInterval = null;
    let retryTimeout = null;

    // Estado de conexión
    const connectionStatus = computed(() => {
        if (isLoading.value) return 'loading';
        if (error.value) return 'error';
        if (isConnected.value) return 'connected';
        return 'disconnected';
    });

    // Obtener métricas desde la API
    const fetchMetrics = async () => {
        try {
            isLoading.value = true;
            error.value = null;

            const response = await axios.get('/api/v1/dashboard/metrics', {
                timeout: 10000,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.data.success) {
                // Actualizar métricas
                metrics.value = response.data.data;
                lastUpdated.value = new Date(response.data.data.last_updated);
                
                // Actualizar notificaciones si existen
                if (response.data.data.notifications) {
                    updateNotifications(response.data.data.notifications);
                }

                isConnected.value = true;
                retryCount.value = 0;

                // Emitir evento personalizado para componentes que escuchen
                window.dispatchEvent(new CustomEvent('metrics-updated', {
                    detail: response.data.data
                }));

                console.log('✅ Métricas actualizadas:', new Date().toLocaleTimeString());
                
                return response.data.data;
            } else {
                throw new Error(response.data.message || 'Error al obtener métricas');
            }
        } catch (err) {
            console.error('❌ Error al obtener métricas:', err);
            error.value = err.response?.data?.message || err.message || 'Error de conexión';
            isConnected.value = false;
            
            // Intentar reconectar si hay reintentos disponibles
            if (retryCount.value < config.retryAttempts) {
                scheduleRetry();
            }
            
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    // Obtener métricas específicas
    const fetchSpecificMetric = async (metricKey) => {
        try {
            const response = await axios.get(`/api/v1/dashboard/metrics/${metricKey}`, {
                timeout: 5000
            });

            if (response.data.success) {
                // Actualizar solo la métrica específica
                if (metrics.value) {
                    metrics.value[metricKey] = response.data.data;
                }
                return response.data.data;
            }
        } catch (err) {
            console.error(`❌ Error al obtener métrica ${metricKey}:`, err);
            throw err;
        }
    };

    // Obtener datos de gráficos
    const fetchChartData = async (chartType, period = '7d') => {
        try {
            const response = await axios.get('/api/v1/dashboard/charts', {
                params: { type: chartType, period },
                timeout: 10000
            });

            if (response.data.success) {
                if (!charts.value) charts.value = {};
                charts.value[chartType] = response.data.data;
                
                // Emitir evento para actualización de gráficos
                window.dispatchEvent(new CustomEvent('chart-updated', {
                    detail: { type: chartType, data: response.data.data }
                }));
                
                return response.data.data;
            }
        } catch (err) {
            console.error(`❌ Error al obtener gráfico ${chartType}:`, err);
            throw err;
        }
    };

    // Programar reintento
    const scheduleRetry = () => {
        retryCount.value++;
        console.log(`🔄 Reintentando conexión (${retryCount.value}/${config.retryAttempts})...`);
        
        retryTimeout = setTimeout(() => {
            fetchMetrics();
        }, config.retryDelay);
    };

    // Actualizar notificaciones
    const updateNotifications = (newNotifications) => {
        // Mantener notificaciones existentes que no están en la nueva lista
        const existingIds = notifications.value.map(n => n.id);
        const newIds = newNotifications.map(n => n.id);
        
        // Agregar nuevas notificaciones
        newNotifications.forEach(notification => {
            if (!existingIds.includes(notification.id)) {
                notifications.value.unshift(notification);
                
                // Mostrar notificación del navegador si está permitido
                if (Notification.permission === 'granted' && notification.type === 'error') {
                    showBrowserNotification(notification);
                }
            }
        });
        
        // Remover notificaciones que ya no existen
        notifications.value = notifications.value.filter(n => 
            newIds.includes(n.id) || Date.now() - new Date(n.created_at).getTime() < 300000 // 5 minutos
        );
    };

    // Mostrar notificación del navegador
    const showBrowserNotification = (notification) => {
        try {
            new Notification(notification.title, {
                body: notification.message,
                icon: '/favicon.ico',
                tag: notification.id,
                requireInteraction: notification.type === 'error'
            });
        } catch (err) {
            console.warn('No se pudo mostrar notificación del navegador:', err);
        }
    };

    // Solicitar permisos de notificación
    const requestNotificationPermission = async () => {
        if ('Notification' in window && Notification.permission === 'default') {
            const permission = await Notification.requestPermission();
            return permission === 'granted';
        }
        return Notification.permission === 'granted';
    };

    // Iniciar actualizaciones automáticas
    const start = () => {
        if (updateInterval) return; // Ya está iniciado
        
        console.log('🚀 Iniciando métricas en tiempo real...');
        
        // Obtener métricas inmediatamente
        fetchMetrics();
        
        // Configurar intervalo de actualización
        updateInterval = setInterval(() => {
            if (!document.hidden) { // Solo actualizar si la página está visible
                fetchMetrics();
            }
        }, config.updateInterval);
        
        // Pausar cuando la página esté oculta para ahorrar recursos
        document.addEventListener('visibilitychange', handleVisibilityChange);
        
        // Reanudar cuando se recupere la conexión
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
    };

    // Detener actualizaciones
    const stop = () => {
        console.log('⏹️ Deteniendo métricas en tiempo real...');
        
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
        
        if (retryTimeout) {
            clearTimeout(retryTimeout);
            retryTimeout = null;
        }
        
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        window.removeEventListener('online', handleOnline);
        window.removeEventListener('offline', handleOffline);
        
        isConnected.value = false;
    };

    // Reiniciar conexión
    const restart = () => {
        stop();
        start();
    };

    // Manejar cambios de visibilidad de la página
    const handleVisibilityChange = () => {
        if (!document.hidden && isConnected.value) {
            // Página visible - actualizar métricas inmediatamente
            fetchMetrics();
        }
    };

    // Manejar conexión online
    const handleOnline = () => {
        console.log('🌐 Conexión restaurada');
        retryCount.value = 0;
        fetchMetrics();
    };

    // Manejar conexión offline
    const handleOffline = () => {
        console.log('📡 Conexión perdida');
        isConnected.value = false;
        error.value = 'Sin conexión a internet';
    };

    // Obtener valor de métrica específica
    const getMetricValue = (path, defaultValue = 0) => {
        try {
            const keys = path.split('.');
            let value = metrics.value;
            
            for (const key of keys) {
                if (value && typeof value === 'object' && key in value) {
                    value = value[key];
                } else {
                    return defaultValue;
                }
            }
            
            return value ?? defaultValue;
        } catch {
            return defaultValue;
        }
    };

    // Formatear valores
    const formatValue = (value, type = 'number') => {
        if (value === null || value === undefined) return '-';
        
        switch (type) {
            case 'currency':
                return new Intl.NumberFormat('es-CL', {
                    style: 'currency',
                    currency: 'CLP',
                    minimumFractionDigits: 0
                }).format(value);
            case 'percentage':
                return new Intl.NumberFormat('es-CL', {
                    style: 'percent',
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 1
                }).format(value / 100);
            case 'number':
                return new Intl.NumberFormat('es-CL').format(value);
            default:
                return value;
        }
    };

    // Calcular tendencia
    const getTrend = (current, previous) => {
        if (!previous || previous === 0) return 0;
        return ((current - previous) / previous) * 100;
    };

    // Obtener color de tendencia
    const getTrendColor = (trend) => {
        if (trend > 0) return 'text-green-600';
        if (trend < 0) return 'text-red-600';
        return 'text-gray-600';
    };

    // Marcar notificación como leída
    const markNotificationAsRead = (notificationId) => {
        const index = notifications.value.findIndex(n => n.id === notificationId);
        if (index !== -1) {
            notifications.value.splice(index, 1);
        }
    };

    // Limpiar todas las notificaciones
    const clearNotifications = () => {
        notifications.value = [];
    };

    // Lifecycle hooks
    onMounted(() => {
        if (config.autoStart) {
            start();
        }
        
        // Solicitar permisos de notificación si están disponibles
        if ('Notification' in window) {
            requestNotificationPermission();
        }
    });

    onUnmounted(() => {
        stop();
    });

    // API pública
    return {
        // Estado
        metrics: readonly(metrics),
        charts: readonly(charts),
        notifications: readonly(notifications),
        isLoading: readonly(isLoading),
        isConnected: readonly(isConnected),
        lastUpdated: readonly(lastUpdated),
        error: readonly(error),
        connectionStatus,
        
        // Métodos principales
        start,
        stop,
        restart,
        fetchMetrics,
        fetchSpecificMetric,
        fetchChartData,
        
        // Utilidades
        getMetricValue,
        formatValue,
        getTrend,
        getTrendColor,
        
        // Notificaciones
        markNotificationAsRead,
        clearNotifications,
        requestNotificationPermission,
        
        // Configuración
        config: readonly(config)
    };
}

// Función helper para hacer readonly
function readonly(ref) {
    return computed(() => ref.value);
}