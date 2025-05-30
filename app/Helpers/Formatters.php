<?php

namespace App\Helpers;

class Formatters
{
    /**
     * Format amount as Chilean Pesos
     *
     * @param float|int $amount
     * @return string
     */
    public static function currency($amount)
    {
        return '$' . number_format($amount, 0, ',', '.');
    }

    /**
     * Format number with thousand separators
     *
     * @param float|int $number
     * @param int $decimals
     * @return string
     */
    public static function number($number, $decimals = 0)
    {
        return number_format($number, $decimals, ',', '.');
    }

    /**
     * Format RUT chileno
     *
     * @param string $rut
     * @return string
     */
    public static function rut($rut)
    {
        if (!$rut) return '';
        
        // Remove all non-alphanumeric characters
        $rut = preg_replace('/[^0-9kK]/', '', $rut);
        
        if (strlen($rut) < 2) return $rut;
        
        // Separate body and verifier digit
        $body = substr($rut, 0, -1);
        $dv = strtoupper(substr($rut, -1));
        
        // Format body with dots
        $formattedBody = number_format($body, 0, '', '.');
        
        return $formattedBody . '-' . $dv;
    }

    /**
     * Format percentage
     *
     * @param float $value
     * @param int $decimals
     * @return string
     */
    public static function percentage($value, $decimals = 1)
    {
        return number_format($value, $decimals, ',', '.') . '%';
    }

    /**
     * Format date in Chilean format
     *
     * @param string|\DateTime $date
     * @return string
     */
    public static function date($date)
    {
        if (!$date) return '';
        
        if (is_string($date)) {
            $date = new \DateTime($date);
        }
        
        return $date->format('d/m/Y');
    }

    /**
     * Format datetime in Chilean format
     *
     * @param string|\DateTime $datetime
     * @return string
     */
    public static function datetime($datetime)
    {
        if (!$datetime) return '';
        
        if (is_string($datetime)) {
            $datetime = new \DateTime($datetime);
        }
        
        return $datetime->format('d/m/Y H:i');
    }
}