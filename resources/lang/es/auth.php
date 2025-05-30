<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña proporcionada es incorrecta.',
    'throttle' => 'Demasiados intentos de acceso. Por favor intente nuevamente en :seconds segundos.',

    // Login & Registration
    'login' => 'Iniciar Sesión',
    'register' => 'Registrarse',
    'logout' => 'Cerrar Sesión',
    'email' => 'Correo Electrónico',
    'password_field' => 'Contraseña',
    'confirm_password' => 'Confirmar Contraseña',
    'remember_me' => 'Recordarme',
    'forgot_password' => '¿Olvidaste tu contraseña?',
    'reset_password' => 'Restablecer Contraseña',
    'send_reset_link' => 'Enviar Enlace de Restablecimiento',
    'confirm_password_text' => 'Esta es un área segura de la aplicación. Por favor confirma tu contraseña antes de continuar.',
    'confirm' => 'Confirmar',
    'cancel' => 'Cancelar',

    // Two Factor Authentication
    'two_factor' => [
        'title' => 'Autenticación de Dos Factores',
        'description' => 'Por favor confirma el acceso a tu cuenta ingresando el código de autenticación proporcionado por tu aplicación de autenticación.',
        'code' => 'Código',
        'recovery_code' => 'Código de Recuperación',
        'use_recovery_code' => 'Usar un código de recuperación',
        'use_authentication_code' => 'Usar un código de autenticación',
    ],

    // Email Verification
    'verify_email' => [
        'title' => 'Verificar Dirección de Correo Electrónico',
        'description' => 'Gracias por registrarte. Antes de comenzar, ¿podrías verificar tu dirección de correo electrónico haciendo clic en el enlace que acabamos de enviarte? Si no recibiste el correo, con gusto te enviaremos otro.',
        'sent' => 'Se ha enviado un nuevo enlace de verificación a tu dirección de correo electrónico.',
        'resend' => 'Reenviar Correo de Verificación',
    ],

    // Validation messages
    'validation' => [
        'email_required' => 'El campo correo electrónico es obligatorio.',
        'email_email' => 'El correo electrónico debe ser una dirección válida.',
        'password_required' => 'El campo contraseña es obligatorio.',
        'password_confirmed' => 'La confirmación de contraseña no coincide.',
        'password_min' => 'La contraseña debe tener al menos :min caracteres.',
    ],

];