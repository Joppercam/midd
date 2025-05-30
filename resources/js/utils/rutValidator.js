/**
 * Validate Chilean RUT
 * @param {string} rut 
 * @returns {boolean}
 */
export function validateRut(rut) {
    if (!rut) return false;
    
    // Clean RUT (remove dots and dashes)
    const cleanRut = rut.replace(/[^0-9kK]/g, '');
    
    if (cleanRut.length < 8 || cleanRut.length > 9) {
        return false;
    }
    
    // Separate body and verifier digit
    const body = cleanRut.slice(0, -1);
    const dv = cleanRut.slice(-1).toUpperCase();
    
    // Calculate verifier digit
    const calculatedDv = calculateDv(body);
    
    return dv === calculatedDv;
}

/**
 * Calculate verifier digit for RUT
 * @param {string} rutBody 
 * @returns {string}
 */
function calculateDv(rutBody) {
    let suma = 0;
    let multiplicador = 2;
    
    // Iterate from right to left
    for (let i = rutBody.length - 1; i >= 0; i--) {
        suma += parseInt(rutBody[i]) * multiplicador;
        multiplicador++;
        
        if (multiplicador > 7) {
            multiplicador = 2;
        }
    }
    
    const resto = suma % 11;
    const dv = 11 - resto;
    
    if (dv === 11) {
        return '0';
    } else if (dv === 10) {
        return 'K';
    } else {
        return dv.toString();
    }
}

/**
 * Format RUT with dots and dash
 * @param {string} rut 
 * @returns {string}
 */
export function formatRut(rut) {
    if (!rut) return '';
    
    // Remove all non-numeric characters except 'k' or 'K'
    const cleaned = rut.replace(/[^0-9kK]/g, '');
    
    if (cleaned.length < 2) return cleaned;
    
    // Separate body and verifier digit
    const body = cleaned.slice(0, -1);
    const dv = cleaned.slice(-1).toUpperCase();
    
    // Format body with dots
    const formattedBody = body.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    
    return `${formattedBody}-${dv}`;
}

/**
 * Clean RUT (remove formatting)
 * @param {string} rut 
 * @returns {string}
 */
export function cleanRut(rut) {
    return rut.replace(/[^0-9kK]/g, '');
}

/**
 * Format RUT as user types
 * @param {string} value 
 * @returns {string}
 */
export function formatRutOnInput(value) {
    // Remove all non-numeric characters except 'k' or 'K'
    let cleaned = value.replace(/[^0-9kK]/g, '');
    
    // Limit length
    if (cleaned.length > 9) {
        cleaned = cleaned.slice(0, 9);
    }
    
    // If there's a 'k' or 'K' not at the end, move it to the end
    const kIndex = cleaned.toLowerCase().indexOf('k');
    if (kIndex !== -1 && kIndex !== cleaned.length - 1) {
        cleaned = cleaned.replace(/[kK]/g, '') + 'K';
    }
    
    // Format only if we have more than 1 character
    if (cleaned.length <= 1) return cleaned;
    
    // Separate body and potential DV
    let body, dv;
    if (cleaned.length >= 2) {
        dv = cleaned.slice(-1);
        body = cleaned.slice(0, -1);
    } else {
        body = cleaned;
        dv = '';
    }
    
    // Add dots to body
    const formattedBody = body.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    
    // Return formatted RUT
    return dv ? `${formattedBody}-${dv.toUpperCase()}` : formattedBody;
}