<?php

namespace App\Http\Controllers;

use App\Models\DemoRequest;
use App\Services\DemoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DemoController extends Controller
{
    protected $demoService;
    
    public function __construct(DemoService $demoService)
    {
        $this->demoService = $demoService;
    }
    
    public function landing()
    {
        return Inertia::render('Demo/Landing', [
            'config' => config('demo'),
            'features' => $this->getDemoFeatures(),
            'testimonials' => $this->getTestimonials(),
        ]);
    }
    
    public function start(Request $request)
    {
        $demoRequestId = $request->get('request_id');
        
        // Crear nueva sesión de demo
        $demoSession = $this->demoService->createDemoSession($demoRequestId);
        
        // Marcar solicitud como demo iniciada
        if ($demoRequestId) {
            $demoRequest = DemoRequest::find($demoRequestId);
            if ($demoRequest) {
                $demoRequest->update([
                    'status' => 'demo_scheduled',
                    'demo_scheduled_at' => now()
                ]);
            }
        }
        
        // Autenticar usuario demo
        Auth::login($demoSession['user']);
        
        return redirect()
            ->route('demo.dashboard', ['session' => $demoSession['session_id']])
            ->with('demo_session', $demoSession);
    }
    
    public function dashboard(Request $request, $sessionId)
    {
        // Verificar que la sesión es válida
        if ($this->demoService->isDemoExpired($sessionId)) {
            return redirect()->route('demo.expired');
        }
        
        return Inertia::render('Demo/Dashboard', [
            'demo_session_id' => $sessionId,
            'demo_config' => config('demo'),
            'tour_steps' => $this->getTourSteps(),
            'demo_data' => $this->getDemoData(),
        ]);
    }
    
    public function extend(Request $request, $sessionId)
    {
        $extended = $this->demoService->extendDemoSession($sessionId);
        
        return response()->json([
            'success' => $extended,
            'message' => $extended 
                ? 'Sesión extendida por ' . config('demo.session.duration', 30) . ' minutos más'
                : 'No se puede extender más la sesión'
        ]);
    }
    
    public function end(Request $request, $sessionId)
    {
        $this->demoService->endDemoSession($sessionId);
        Auth::logout();
        
        return redirect()->route('demo.feedback', ['session' => $sessionId]);
    }
    
    public function expired()
    {
        return Inertia::render('Demo/Expired', [
            'contact_info' => config('demo.branding.contact_info')
        ]);
    }
    
    public function feedback(Request $request, $sessionId)
    {
        return Inertia::render('Demo/Feedback', [
            'session_id' => $sessionId,
            'contact_info' => config('demo.branding.contact_info')
        ]);
    }
    
    public function submitFeedback(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|string',
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:1000',
            'interested_in_purchase' => 'boolean',
            'preferred_contact_method' => 'nullable|string',
        ]);
        
        // Guardar feedback (puedes crear una tabla demo_feedback)
        // Para este ejemplo, lo guardamos en logs
        \Log::info('Demo Feedback', $validated);
        
        return response()->json([
            'success' => true,
            'message' => '¡Gracias por tu feedback! Nos pondremos en contacto contigo pronto.'
        ]);
    }
    
    protected function getDemoFeatures()
    {
        return [
            [
                'icon' => '📊',
                'title' => 'Dashboard Intuitivo',
                'description' => 'Visualiza todas las métricas importantes de tu empresa en tiempo real'
            ],
            [
                'icon' => '🧾',
                'title' => 'Facturación Electrónica',
                'description' => 'Genera y envía DTEs al SII automáticamente'
            ],
            [
                'icon' => '📈',
                'title' => 'Reportes Avanzados',
                'description' => 'Analiza tu negocio con reportes detallados y personalizables'
            ],
            [
                'icon' => '💰',
                'title' => 'Control Financiero',
                'description' => 'Gestiona pagos, cuentas por cobrar y flujo de caja'
            ],
            [
                'icon' => '📦',
                'title' => 'Gestión de Inventario',
                'description' => 'Controla stock, movimientos y alertas de inventario'
            ],
            [
                'icon' => '🤖',
                'title' => 'Automatización',
                'description' => 'Automatiza procesos repetitivos y ahorra tiempo valioso'
            ]
        ];
    }
    
    protected function getTestimonials()
    {
        return [
            [
                'name' => 'María González',
                'company' => 'Comercial Las Flores',
                'text' => 'CrecePyme nos ayudó a digitalizar completamente nuestros procesos. Ahora facturamos en segundos.',
                'rating' => 5
            ],
            [
                'name' => 'Carlos Mendoza',
                'company' => 'Taller Automotriz CM',
                'text' => 'La integración con el SII es perfecta. Ya no tenemos problemas con la facturación electrónica.',
                'rating' => 5
            ],
            [
                'name' => 'Ana Ruiz',
                'company' => 'Restaurante El Sabor',
                'text' => 'Los reportes nos permiten tomar mejores decisiones. Hemos aumentado nuestra rentabilidad en un 15%.',
                'rating' => 5
            ]
        ];
    }
    
    protected function getTourSteps()
    {
        return [
            [
                'target' => '.dashboard-metrics',
                'title' => 'Métricas Principales',
                'content' => 'Aquí ves un resumen de las métricas más importantes de tu empresa: ventas, clientes, productos y más.',
                'position' => 'bottom'
            ],
            [
                'target' => '.navigation-menu',
                'title' => 'Menú de Navegación',
                'content' => 'Desde aquí puedes acceder a todos los módulos: facturación, inventario, reportes, configuración.',
                'position' => 'right'
            ],
            [
                'target' => '.quick-actions',
                'title' => 'Acciones Rápidas',
                'content' => 'Botones de acceso rápido para las tareas más comunes como crear facturas o cotizaciones.',
                'position' => 'left'
            ],
            [
                'target' => '.notifications-bell',
                'title' => 'Notificaciones',
                'content' => 'Recibe alertas sobre pagos pendientes, stock bajo, y otras actividades importantes.',
                'position' => 'bottom'
            ]
        ];
    }
    
    protected function getDemoData()
    {
        return [
            'metrics' => [
                'total_sales' => 2450000,
                'monthly_sales' => 890000,
                'pending_invoices' => 15,
                'active_customers' => 47,
                'products_count' => 156,
                'low_stock_alerts' => 3
            ],
            'recent_activities' => [
                ['type' => 'invoice', 'description' => 'Factura #001234 creada', 'time' => '2 min'],
                ['type' => 'payment', 'description' => 'Pago recibido de Cliente ABC', 'time' => '15 min'],
                ['type' => 'stock', 'description' => 'Stock bajo en Producto XYZ', 'time' => '1 hora'],
            ]
        ];
    }
}
