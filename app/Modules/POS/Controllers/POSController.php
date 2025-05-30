<?php

namespace App\Modules\POS\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Customer;
use App\Modules\POS\Models\POSTerminal;
use App\Modules\POS\Models\CashSession;
use App\Modules\POS\Services\POSService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class POSController extends Controller
{
    protected POSService $posService;

    public function __construct(POSService $posService)
    {
        $this->middleware('permission:pos.operate');
        $this->posService = $posService;
    }

    /**
     * Main POS interface
     */
    public function index(Request $request)
    {
        $terminalId = $request->get('terminal');
        
        if (!$terminalId) {
            return $this->selectTerminal();
        }

        $terminal = POSTerminal::where('tenant_id', tenant()->id)
            ->where('id', $terminalId)
            ->active()
            ->firstOrFail();

        // Check if terminal has active session
        if (!$terminal->hasActiveSession()) {
            return $this->openSession($terminal);
        }

        $session = $terminal->currentSession;
        
        // Get products for POS
        $products = Product::where('tenant_id', tenant()->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->with(['category'])
            ->orderBy('name')
            ->get();

        return Inertia::render('POS/Interface', [
            'terminal' => $terminal,
            'session' => $session,
            'products' => $products,
        ]);
    }

    /**
     * Terminal selection page
     */
    protected function selectTerminal()
    {
        $terminals = POSTerminal::where('tenant_id', tenant()->id)
            ->active()
            ->with('currentSession.user')
            ->get();

        return Inertia::render('POS/SelectTerminal', [
            'terminals' => $terminals,
        ]);
    }

    /**
     * Session opening page
     */
    protected function openSession(POSTerminal $terminal)
    {
        return Inertia::render('POS/OpenSession', [
            'terminal' => $terminal,
        ]);
    }

    /**
     * Open a new cash session
     */
    public function openCashSession(Request $request, POSTerminal $terminal)
    {
        $request->validate([
            'opening_balance' => 'required|numeric|min:0',
            'opening_notes' => 'nullable|string|max:500',
        ]);

        if ($terminal->hasActiveSession()) {
            return redirect()->back()
                ->withErrors(['session' => 'Terminal already has an active session.']);
        }

        $session = $this->posService->openSession(
            $terminal,
            auth()->user(),
            $request->opening_balance,
            null,
            $request->opening_notes
        );

        return redirect()->route('pos.index', ['terminal' => $terminal->id])
            ->with('success', 'Cash session opened successfully.');
    }

    /**
     * Search products
     */
    public function searchProducts(Request $request)
    {
        $search = $request->get('q');
        
        if (!$search || strlen($search) < 2) {
            return response()->json([]);
        }

        $products = Product::where('tenant_id', tenant()->id)
            ->where('is_active', true)
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            })
            ->limit(20)
            ->get();

        return response()->json($products);
    }
}