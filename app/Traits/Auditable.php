<?php

namespace App\Traits;

trait Auditable
{
    public static function bootAuditable()
    {
        // Trait simplificado que puede expandirse más adelante
        // Por ahora solo evita errores
    }

    public function auditLogs()
    {
        return collect(); // Retornar colección vacía por ahora
    }
}