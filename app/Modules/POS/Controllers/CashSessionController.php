<?php

namespace App\Modules\POS\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\POS\Services\CashSessionService;
use App\Modules\POS\Requests\CashSessionRequest;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CashSessionController extends Controller
{
    use ChecksPermissions;

    public function __construct(private CashSessionService $cashSessionService)
    {
        $this->middleware(['auth', 'check.module:pos']);
    }

    public function index()
    {
        $this->checkPermission('pos.sessions.view');

        $sessions = $this->cashSessionService->getSessionsList();

        return Inertia::render('POS/CashSessions/Index', [
            'sessions' => $sessions,
            'currentSession' => $this->cashSessionService->getCurrentSession(auth()->id()),
        ]);
    }

    public function create()
    {
        $this->checkPermission('pos.cash_register.open');

        // Verificar que no hay sesión activa
        $currentSession = $this->cashSessionService->getCurrentSession(auth()->id());
        if ($currentSession) {
            return redirect()->route('pos.cash-sessions.show', $currentSession->id)
                ->with('error', 'Ya tiene una sesión de caja activa');
        }

        $data = [
            'terminals' => $this->cashSessionService->getAvailableTerminals(),
            'defaultStartAmount' => config('pos.cash_registers.default_start_amount'),
            'denominations' => config('pos.denominations'),
        ];

        return Inertia::render('POS/CashSessions/Create', $data);
    }

    public function store(CashSessionRequest $request)
    {
        $this->checkPermission('pos.cash_register.open');

        try {
            $session = $this->cashSessionService->openSession($request->validated());

            return redirect()->route('pos.sale')
                ->with('success', 'Sesión de caja abierta exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $this->checkPermission('pos.sessions.view');

        $session = $this->cashSessionService->getSessionDetails($id);

        return Inertia::render('POS/CashSessions/Show', [
            'session' => $session,
            'transactions' => $this->cashSessionService->getSessionTransactions($id),
            'movements' => $this->cashSessionService->getSessionMovements($id),
            'summary' => $this->cashSessionService->getSessionSummary($id),
        ]);
    }

    public function close(Request $request, $id)
    {
        $this->checkPermission('pos.cash_register.close');

        $request->validate([
            'final_amount' => 'required|numeric|min:0',
            'counted_denominations' => 'required|array',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->cashSessionService->closeSession($id, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Sesión cerrada exitosamente',
                'redirect' => route('pos.cash-sessions.index'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function suspend($id)
    {
        $this->checkPermission('pos.sessions.manage');

        try {
            $this->cashSessionService->suspendSession($id);

            return response()->json([
                'success' => true,
                'message' => 'Sesión suspendida',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function resume($id)
    {
        $this->checkPermission('pos.sessions.manage');

        try {
            $this->cashSessionService->resumeSession($id);

            return response()->json([
                'success' => true,
                'message' => 'Sesión reanudada',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function addCashMovement(Request $request, $sessionId)
    {
        $this->checkPermission('pos.cash_register.manage');

        $request->validate([
            'type' => 'required|in:cash_in,cash_out',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
            'manager_pin' => 'required|string',
        ]);

        try {
            $movement = $this->cashSessionService->addCashMovement($sessionId, $request->all());

            return response()->json([
                'success' => true,
                'movement' => $movement,
                'message' => 'Movimiento de caja registrado',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getCurrentBalance($sessionId)
    {
        $balance = $this->cashSessionService->getCurrentBalance($sessionId);

        return response()->json([
            'balance' => $balance,
            'last_updated' => now(),
        ]);
    }

    public function printReport($sessionId)
    {
        $this->checkPermission('pos.reports.cash');

        try {
            $report = $this->cashSessionService->generateSessionReport($sessionId);

            return response()->json([
                'success' => true,
                'report' => $report,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function transfer(Request $request)
    {
        $this->checkPermission('pos.cash_register.transfer');

        $request->validate([
            'from_session_id' => 'required|exists:cash_sessions,id',
            'to_session_id' => 'required|exists:cash_sessions,id|different:from_session_id',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'manager_pin' => 'required|string',
        ]);

        try {
            $transfer = $this->cashSessionService->transferCash($request->all());

            return response()->json([
                'success' => true,
                'transfer' => $transfer,
                'message' => 'Transferencia realizada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function count(Request $request, $sessionId)
    {
        $this->checkPermission('pos.cash_register.count');

        $request->validate([
            'counted_amount' => 'required|numeric|min:0',
            'denominations' => 'required|array',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $count = $this->cashSessionService->performCashCount($sessionId, $request->all());

            return response()->json([
                'success' => true,
                'count' => $count,
                'variance' => $count['variance'],
                'message' => 'Arqueo realizado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function history()
    {
        $this->checkPermission('pos.sessions.view');

        $history = $this->cashSessionService->getSessionHistory(auth()->id());

        return Inertia::render('POS/CashSessions/History', [
            'sessions' => $history,
            'statistics' => $this->cashSessionService->getUserStatistics(auth()->id()),
        ]);
    }

    public function export(Request $request)
    {
        $this->checkPermission('pos.reports.export');

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'required|in:pdf,excel,csv',
            'user_id' => 'nullable|exists:users,id',
        ]);

        try {
            $export = $this->cashSessionService->exportSessions($request->all());

            return response()->download($export['file'], $export['filename']);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}