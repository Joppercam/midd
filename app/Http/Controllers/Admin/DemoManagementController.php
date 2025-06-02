<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DemoRequest;
use App\Services\DemoService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Mail;

class DemoManagementController extends Controller
{
    protected $demoService;
    
    public function __construct(DemoService $demoService)
    {
        $this->demoService = $demoService;
        $this->middleware(['auth', 'verified']);
    }
    
    public function index(Request $request)
    {
        // Check if user has admin role
        if (auth()->user()->role !== 'admin' && !auth()->user()->hasRole('admin') && !auth()->user()->hasRole('super-admin')) {
            abort(403, 'User does not have the right roles.');
        }
        $query = DemoRequest::query()
            ->orderBy('created_at', 'desc');
        
        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        $demoRequests = $query->paginate(15);
        
        // Estadísticas
        $stats = [
            'total' => DemoRequest::count(),
            'pending' => DemoRequest::where('status', 'pending')->count(),
            'contacted' => DemoRequest::where('status', 'contacted')->count(),
            'demo_scheduled' => DemoRequest::where('status', 'demo_scheduled')->count(),
            'converted' => DemoRequest::where('status', 'converted')->count(),
            'today' => DemoRequest::whereDate('created_at', today())->count(),
            'this_week' => DemoRequest::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
        ];
        
        return Inertia::render('Admin/DemoManagement/Index', [
            'demoRequests' => $demoRequests,
            'stats' => $stats,
            'filters' => $request->only(['status', 'search']),
            'statusOptions' => [
                'pending' => 'Pendiente',
                'contacted' => 'Contactado',
                'demo_scheduled' => 'Demo Agendada',
                'demo_completed' => 'Demo Completada',
                'converted' => 'Convertido',
                'declined' => 'Declinado'
            ]
        ]);
    }
    
    public function show(DemoRequest $demoRequest)
    {
        return Inertia::render('Admin/DemoManagement/Show', [
            'demoRequest' => $demoRequest->load(['notes']),
            'timeline' => $this->getRequestTimeline($demoRequest),
        ]);
    }
    
    public function updateStatus(Request $request, DemoRequest $demoRequest)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,contacted,demo_scheduled,demo_completed,converted,declined',
            'note' => 'nullable|string|max:500'
        ]);
        
        $oldStatus = $demoRequest->status;
        
        $demoRequest->update([
            'status' => $validated['status'],
            'contacted_at' => $validated['status'] === 'contacted' ? now() : $demoRequest->contacted_at,
            'demo_scheduled_at' => $validated['status'] === 'demo_scheduled' ? now() : $demoRequest->demo_scheduled_at,
        ]);
        
        // Agregar nota si se proporciona
        if ($validated['note']) {
            $demoRequest->addNote($validated['note'], auth()->user()->name);
        }
        
        // Enviar email automático si aplica
        $this->sendStatusUpdateEmail($demoRequest, $oldStatus);
        
        return back()->with('success', 'Estado actualizado correctamente.');
    }
    
    public function generateCredentials(DemoRequest $demoRequest)
    {
        if ($demoRequest->status !== 'contacted') {
            return back()->withErrors(['error' => 'La solicitud debe estar en estado "Contactado" para generar credenciales.']);
        }
        
        try {
            // Crear sesión de demo
            $demoSession = $this->demoService->createDemoSession($demoRequest->id);
            
            // Actualizar solicitud
            $demoRequest->update([
                'status' => 'demo_scheduled',
                'demo_scheduled_at' => now()
            ]);
            
            // Enviar credenciales por email
            $this->sendDemoCredentials($demoRequest, $demoSession);
            
            return back()->with('success', 'Credenciales generadas y enviadas por email.');
            
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al generar credenciales: ' . $e->getMessage()]);
        }
    }
    
    public function addNote(Request $request, DemoRequest $demoRequest)
    {
        $validated = $request->validate([
            'note' => 'required|string|max:1000'
        ]);
        
        $demoRequest->addNote($validated['note'], auth()->user()->name);
        
        return back()->with('success', 'Nota agregada correctamente.');
    }
    
    public function assignTo(Request $request, DemoRequest $demoRequest)
    {
        $validated = $request->validate([
            'assigned_to' => 'required|string|max:255'
        ]);
        
        $demoRequest->update($validated);
        
        return back()->with('success', 'Solicitud asignada correctamente.');
    }
    
    protected function getRequestTimeline(DemoRequest $demoRequest)
    {
        $timeline = [
            [
                'date' => $demoRequest->created_at,
                'title' => 'Solicitud creada',
                'description' => 'Nueva solicitud de demo recibida',
                'type' => 'created'
            ]
        ];
        
        if ($demoRequest->contacted_at) {
            $timeline[] = [
                'date' => $demoRequest->contacted_at,
                'title' => 'Cliente contactado',
                'description' => 'Se estableció contacto con el cliente',
                'type' => 'contacted'
            ];
        }
        
        if ($demoRequest->demo_scheduled_at) {
            $timeline[] = [
                'date' => $demoRequest->demo_scheduled_at,
                'title' => 'Demo programada',
                'description' => 'Se programó la demostración del producto',
                'type' => 'scheduled'
            ];
        }
        
        // Agregar notas al timeline
        if ($demoRequest->notes) {
            foreach ($demoRequest->notes as $note) {
                $timeline[] = [
                    'date' => \Carbon\Carbon::parse($note['created_at']),
                    'title' => 'Nota agregada',
                    'description' => $note['content'],
                    'author' => $note['author'] ?? 'Sistema',
                    'type' => 'note'
                ];
            }
        }
        
        // Ordenar por fecha
        usort($timeline, function ($a, $b) {
            return $a['date']->timestamp - $b['date']->timestamp;
        });
        
        return $timeline;
    }
    
    protected function sendStatusUpdateEmail(DemoRequest $demoRequest, string $oldStatus)
    {
        if ($demoRequest->status === 'demo_scheduled' && $oldStatus !== 'demo_scheduled') {
            // Email cuando se programa el demo
            Mail::raw(
                "Hola {$demoRequest->contact_name},\n\n" .
                "¡Excelentes noticias! Tu demo de CrecePyme ha sido programada.\n\n" .
                "Recibirás las credenciales de acceso en un email separado.\n\n" .
                "Si tienes alguna pregunta, no dudes en contactarnos:\n" .
                "📧 ventas@crecepyme.cl\n" .
                "📱 +56 9 1234 5678\n\n" .
                "¡Estamos emocionados de mostrarte todo lo que CrecePyme puede hacer por tu empresa!\n\n" .
                "Saludos,\n" .
                "Equipo CrecePyme",
                function ($message) use ($demoRequest) {
                    $message->to($demoRequest->email)
                           ->subject('Demo CrecePyme Programada')
                           ->from('ventas@crecepyme.cl', 'Equipo CrecePyme');
                }
            );
        }
    }
    
    protected function sendDemoCredentials(DemoRequest $demoRequest, array $demoSession)
    {
        $demoUrl = $demoSession['demo_url'];
        $expiresAt = $demoSession['expires_at']->format('d/m/Y H:i');
        
        Mail::raw(
            "Hola {$demoRequest->contact_name},\n\n" .
            "¡Tu demo personalizada de CrecePyme está lista!\n\n" .
            "🔗 **Accede a tu demo aquí:**\n" .
            "{$demoUrl}\n\n" .
            "⏰ **Duración de la sesión:** 30 minutos\n" .
            "📅 **Válida hasta:** {$expiresAt}\n\n" .
            "💬 **¿Necesitas ayuda?** \n" .
            "Durante tu demo, encontrarás un chatbot que te guiará paso a paso y responderá todas tus preguntas.\n\n" .
            "🚀 **Qué puedes hacer en el demo:**\n" .
            "• Crear facturas electrónicas\n" .
            "• Explorar reportes financieros\n" .
            "• Gestionar inventario\n" .
            "• Ver integración con SII\n" .
            "• Y mucho más...\n\n" .
            "📞 **¿Preguntas?**\n" .
            "ventas@crecepyme.cl | +56 9 1234 5678\n\n" .
            "¡Disfruta explorando CrecePyme!\n\n" .
            "Saludos,\n" .
            "Equipo CrecePyme",
            function ($message) use ($demoRequest) {
                $message->to($demoRequest->email)
                       ->subject('🚀 Tu Demo CrecePyme está Lista - Acceso Inmediato')
                       ->from('ventas@crecepyme.cl', 'Equipo CrecePyme');
            }
        );
    }
}
