import { getCurrentInstance } from 'vue';

export function useGlobalFormatters() {
    const instance = getCurrentInstance();
    
    return {
        formatCurrency: instance.appContext.config.globalProperties.$formatCurrency,
        formatNumber: instance.appContext.config.globalProperties.$formatNumber,
        formatDate: instance.appContext.config.globalProperties.$formatDate,
        formatDateTime: instance.appContext.config.globalProperties.$formatDateTime,
        formatPercentage: instance.appContext.config.globalProperties.$formatPercentage,
        formatRut: instance.appContext.config.globalProperties.$formatRut,
    };
}