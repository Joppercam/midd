<?php

namespace App\Validators;

class RutValidator
{
    /**
     * Validate Chilean RUT
     *
     * @param string $rut
     * @return bool
     */
    public static function validate($rut)
    {
        if (!$rut) return false;
        
        // Clean RUT (remove dots and dashes)
        $rut = preg_replace('/[^0-9kK]/', '', $rut);
        
        if (strlen($rut) < 8 || strlen($rut) > 9) {
            return false;
        }
        
        // Separate body and verifier digit
        $body = substr($rut, 0, -1);
        $dv = strtoupper(substr($rut, -1));
        
        // Calculate verifier digit
        $calculatedDv = self::calculateDv($body);
        
        return $dv === $calculatedDv;
    }
    
    /**
     * Calculate verifier digit for RUT
     *
     * @param string $rutBody
     * @return string
     */
    private static function calculateDv($rutBody)
    {
        $suma = 0;
        $multiplicador = 2;
        
        // Iterate from right to left
        for ($i = strlen($rutBody) - 1; $i >= 0; $i--) {
            $suma += $rutBody[$i] * $multiplicador;
            $multiplicador++;
            
            if ($multiplicador > 7) {
                $multiplicador = 2;
            }
        }
        
        $resto = $suma % 11;
        $dv = 11 - $resto;
        
        if ($dv == 11) {
            return '0';
        } elseif ($dv == 10) {
            return 'K';
        } else {
            return (string) $dv;
        }
    }
    
    /**
     * Format RUT with dots and dash
     *
     * @param string $rut
     * @return string|null
     */
    public static function format($rut)
    {
        if (!$rut) return null;
        
        // Clean RUT
        $rut = preg_replace('/[^0-9kK]/', '', $rut);
        
        if (strlen($rut) < 2) return $rut;
        
        // Separate body and verifier digit
        $body = substr($rut, 0, -1);
        $dv = strtoupper(substr($rut, -1));
        
        // Add dots to body
        $formattedBody = '';
        $bodyReversed = strrev($body);
        
        for ($i = 0; $i < strlen($bodyReversed); $i++) {
            if ($i > 0 && $i % 3 == 0) {
                $formattedBody = '.' . $formattedBody;
            }
            $formattedBody = $bodyReversed[$i] . $formattedBody;
        }
        
        return $formattedBody . '-' . $dv;
    }
    
    /**
     * Clean RUT (remove formatting)
     *
     * @param string $rut
     * @return string
     */
    public static function clean($rut)
    {
        return preg_replace('/[^0-9kK]/', '', $rut);
    }
}