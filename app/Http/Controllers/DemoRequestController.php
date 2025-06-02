<?php

namespace App\Http\Controllers;

use App\Models\DemoRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewDemoRequestNotification;

class DemoRequestController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'rut' => 'nullable|string|max:20',
            'contact_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'business_type' => 'nullable|string|max:100',
            'employees' => 'nullable|string|max:20',
            'message' => 'nullable|string|max:1000'
        ], [
            'company_name.required' => 'El nombre de la empresa es obligatorio.',
            'contact_name.required' => 'El nombre de contacto es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'phone.required' => 'El teléfono es obligatorio.'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Crear la solicitud de demo
        $demoRequest = DemoRequest::create($validator->validated());

        // Enviar notificación al equipo de ventas
        $this->notifySalesTeam($demoRequest);

        // Enviar confirmación al cliente
        $this->sendConfirmationEmail($demoRequest);

        return back()->with('success', '¡Gracias por tu interés! Nos contactaremos contigo en menos de 24 horas para coordinar tu demo personalizada.');
    }

    protected function notifySalesTeam(DemoRequest $demoRequest)
    {
        // Aquí puedes enviar notificaciones a tu equipo de ventas
        // Por ejemplo, usando Slack, email, etc.
        
        // Email simple al equipo de ventas
        Mail::raw(
            "Nueva solicitud de demo recibida:\n\n" .
            "Empresa: {$demoRequest->company_name}\n" .
            "Contacto: {$demoRequest->contact_name}\n" .
            "Email: {$demoRequest->email}\n" .
            "Teléfono: {$demoRequest->phone}\n" .
            "Tipo de negocio: {$demoRequest->business_type}\n" .
            "Empleados: {$demoRequest->employees}\n" .
            "Mensaje: {$demoRequest->message}\n\n" .
            "Accede al panel de administración para ver más detalles.",
            function ($message) use ($demoRequest) {
                $message->to(['ventas@crecepyme.cl', 'admin@crecepyme.cl'])
                       ->subject("Nueva solicitud de demo - {$demoRequest->company_name}");
            }
        );
    }

    protected function sendConfirmationEmail(DemoRequest $demoRequest)
    {
        Mail::raw(
            "Hola {$demoRequest->contact_name},\n\n" .
            "¡Gracias por tu interés en MIDD!\n\n" .
            "Hemos recibido tu solicitud de demo para {$demoRequest->company_name}. " .
            "Nuestro equipo comercial se contactará contigo en las próximas 24 horas " .
            "para coordinar una demostración personalizada de nuestra plataforma.\n\n" .
            "Mientras tanto, si tienes alguna pregunta urgente, puedes contactarnos:\n" .
            "📧 ventas@crecepyme.cl\n" .
            "📱 +56 9 1234 5678\n\n" .
            "¡Esperamos ayudarte a hacer crecer tu PyME!\n\n" .
            "Saludos,\n" .
            "Equipo MIDD",
            function ($message) use ($demoRequest) {
                $message->to($demoRequest->email)
                       ->subject('Confirmación de solicitud de demo - MIDD')
                       ->from('noreply@crecepyme.cl', 'MIDD');
            }
        );
    }
}
