export function useFormatters() {
    /**
     * Format currency in Chilean Pesos
     * @param {number} amount 
     * @returns {string}
     */
    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('es-CL', {
            style: 'currency',
            currency: 'CLP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount || 0);
    };

    /**
     * Format number with Chilean locale
     * @param {number} number 
     * @returns {string}
     */
    const formatNumber = (number) => {
        return new Intl.NumberFormat('es-CL').format(number || 0);
    };

    /**
     * Format date in Chilean format
     * @param {string|Date} date 
     * @returns {string}
     */
    const formatDate = (date) => {
        if (!date) return '';
        return new Date(date).toLocaleDateString('es-CL', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    };

    /**
     * Format datetime in Chilean format
     * @param {string|Date} date 
     * @returns {string}
     */
    const formatDateTime = (date) => {
        if (!date) return '';
        return new Date(date).toLocaleString('es-CL', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    /**
     * Format percentage
     * @param {number} value 
     * @param {number} decimals 
     * @returns {string}
     */
    const formatPercentage = (value, decimals = 1) => {
        return `${(value || 0).toFixed(decimals)}%`;
    };

    /**
     * Format RUT chileno
     * @param {string} rut 
     * @returns {string}
     */
    const formatRut = (rut) => {
        if (!rut) return '';
        
        // Remove all non-numeric characters except 'k' or 'K'
        const cleaned = rut.toString().replace(/[^0-9kK]/g, '');
        
        if (cleaned.length < 2) return cleaned;
        
        // Separate body and verifier digit
        const body = cleaned.slice(0, -1);
        const dv = cleaned.slice(-1).toUpperCase();
        
        // Format body with dots
        const formattedBody = body.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        
        return `${formattedBody}-${dv}`;
    };

    return {
        formatCurrency,
        formatNumber,
        formatDate,
        formatDateTime,
        formatPercentage,
        formatRut
    };
}