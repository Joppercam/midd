<?php

namespace App\Modules\POS\Services;

use App\Modules\POS\Models\CashSession;
use App\Modules\POS\Models\Terminal;
use App\Modules\POS\Models\Transaction;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class CashSessionService
{
    use BelongsToTenant;

    /**
     * Obtener lista de sesiones de caja
     */
    public function getSessionsList($filters = [])
    {
        $query = CashSession::query()
            ->with(['user', 'terminal'])
            ->forCurrentTenant();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['terminal_id'])) {
            $query->where('terminal_id', $filters['terminal_id']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    /**
     * Obtener sesión actual del usuario
     */
    public function getCurrentSession($userId)
    {
        return CashSession::where('user_id', $userId)
            ->whereIn('status', ['active', 'suspended'])
            ->forCurrentTenant()
            ->with(['terminal'])
            ->first();
    }

    /**
     * Obtener terminales disponibles
     */
    public function getAvailableTerminals()
    {
        return Terminal::where('status', 'active')
            ->whereDoesntHave('cashSessions', function ($query) {
                $query->whereIn('status', ['active', 'suspended']);
            })
            ->forCurrentTenant()
            ->get();
    }

    /**
     * Abrir nueva sesión de caja
     */
    public function openSession($data)
    {
        DB::beginTransaction();
        try {
            // Verificar que no hay sesión activa
            if ($this->getCurrentSession($data['user_id'] ?? Auth::id())) {
                throw new \Exception('Ya existe una sesión de caja activa');
            }

            // Verificar terminal disponible
            $terminal = Terminal::find($data['terminal_id']);
            if (!$terminal || $terminal->status !== 'active') {
                throw new \Exception('Terminal no disponible');
            }

            // Verificar si el terminal está en uso
            $terminalInUse = CashSession::where('terminal_id', $terminal->id)
                ->whereIn('status', ['active', 'suspended'])
                ->exists();

            if ($terminalInUse) {
                throw new \Exception('Terminal ya está en uso');
            }

            // Crear sesión
            $session = CashSession::create([
                'user_id' => $data['user_id'] ?? Auth::id(),
                'terminal_id' => $data['terminal_id'],
                'start_amount' => $data['start_amount'],
                'current_amount' => $data['start_amount'],
                'status' => 'active',
                'opened_at' => now(),
                'notes' => $data['notes'] ?? null,
                'tenant_id' => $this->getCurrentTenantId(),
            ]);

            // Registrar movimiento inicial
            $session->movements()->create([
                'type' => 'initial',
                'amount' => $data['start_amount'],
                'description' => 'Apertura de caja',
                'user_id' => Auth::id(),
                'tenant_id' => $this->getCurrentTenantId(),
            ]);

            // Actualizar estado del terminal
            $terminal->update([
                'current_session_id' => $session->id,
                'last_activity_at' => now(),
            ]);

            // Limpiar cache
            $this->clearSessionCache($data['user_id'] ?? Auth::id());

            DB::commit();

            Log::info('Sesión de caja abierta', [
                'session_id' => $session->id,
                'user_id' => $session->user_id,
                'terminal_id' => $session->terminal_id,
            ]);

            return $session->load(['user', 'terminal']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al abrir sesión de caja: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener detalles de sesión
     */
    public function getSessionDetails($sessionId)
    {
        $session = CashSession::with(['user', 'terminal', 'movements.user'])
            ->forCurrentTenant()
            ->findOrFail($sessionId);

        // Calcular totales
        $session->totals = $this->calculateSessionTotals($sessionId);

        return $session;
    }

    /**
     * Obtener transacciones de la sesión
     */
    public function getSessionTransactions($sessionId)
    {
        return Transaction::where('cash_session_id', $sessionId)
            ->with(['customer', 'items.product', 'payments'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Obtener movimientos de caja
     */
    public function getSessionMovements($sessionId)
    {
        $session = CashSession::findOrFail($sessionId);
        
        return $session->movements()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Obtener resumen de sesión
     */
    public function getSessionSummary($sessionId)
    {
        $session = CashSession::findOrFail($sessionId);
        $transactions = $this->getSessionTransactions($sessionId);
        $movements = $this->getSessionMovements($sessionId);

        $summary = [
            'start_amount' => $session->start_amount,
            'current_amount' => $session->current_amount,
            'sales' => [
                'count' => 0,
                'total' => 0,
                'by_payment_method' => [],
            ],
            'refunds' => [
                'count' => 0,
                'total' => 0,
            ],
            'movements' => [
                'cash_in' => 0,
                'cash_out' => 0,
            ],
            'expected_amount' => 0,
            'difference' => 0,
        ];

        // Procesar transacciones
        foreach ($transactions as $transaction) {
            if ($transaction->type === 'sale' && $transaction->status === 'completed') {
                $summary['sales']['count']++;
                $summary['sales']['total'] += $transaction->total;

                foreach ($transaction->payments as $payment) {
                    if (!isset($summary['sales']['by_payment_method'][$payment->method])) {
                        $summary['sales']['by_payment_method'][$payment->method] = 0;
                    }
                    $summary['sales']['by_payment_method'][$payment->method] += $payment->amount;
                }
            } elseif ($transaction->type === 'refund') {
                $summary['refunds']['count']++;
                $summary['refunds']['total'] += $transaction->total;
            }
        }

        // Procesar movimientos
        foreach ($movements as $movement) {
            if ($movement->type === 'cash_in') {
                $summary['movements']['cash_in'] += $movement->amount;
            } elseif ($movement->type === 'cash_out') {
                $summary['movements']['cash_out'] += $movement->amount;
            }
        }

        // Calcular monto esperado
        $cashSales = $summary['sales']['by_payment_method']['cash'] ?? 0;
        $summary['expected_amount'] = $session->start_amount + $cashSales - $summary['refunds']['total'] 
            + $summary['movements']['cash_in'] - $summary['movements']['cash_out'];
        
        $summary['difference'] = $session->current_amount - $summary['expected_amount'];

        return $summary;
    }

    /**
     * Cerrar sesión de caja
     */
    public function closeSession($sessionId, $data)
    {
        DB::beginTransaction();
        try {
            $session = CashSession::forCurrentTenant()->findOrFail($sessionId);

            if ($session->status !== 'active') {
                throw new \Exception('La sesión no está activa');
            }

            // Calcular diferencia
            $summary = $this->getSessionSummary($sessionId);
            $difference = $data['final_amount'] - $summary['expected_amount'];

            // Actualizar sesión
            $session->update([
                'final_amount' => $data['final_amount'],
                'difference' => $difference,
                'status' => 'closed',
                'closed_at' => now(),
                'closing_notes' => $data['notes'] ?? null,
                'denominations' => $data['counted_denominations'] ?? null,
            ]);

            // Registrar movimiento de cierre
            $session->movements()->create([
                'type' => 'closing',
                'amount' => $data['final_amount'],
                'description' => 'Cierre de caja',
                'user_id' => Auth::id(),
                'tenant_id' => $this->getCurrentTenantId(),
            ]);

            // Liberar terminal
            $session->terminal->update([
                'current_session_id' => null,
                'last_activity_at' => now(),
            ]);

            // Limpiar cache
            $this->clearSessionCache($session->user_id);

            DB::commit();

            Log::info('Sesión de caja cerrada', [
                'session_id' => $session->id,
                'difference' => $difference,
            ]);

            return $session->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cerrar sesión: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Suspender sesión
     */
    public function suspendSession($sessionId)
    {
        $session = CashSession::forCurrentTenant()->findOrFail($sessionId);

        if ($session->status !== 'active') {
            throw new \Exception('La sesión no está activa');
        }

        $session->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        Log::info('Sesión suspendida', ['session_id' => $sessionId]);

        return $session;
    }

    /**
     * Reanudar sesión
     */
    public function resumeSession($sessionId)
    {
        $session = CashSession::forCurrentTenant()->findOrFail($sessionId);

        if ($session->status !== 'suspended') {
            throw new \Exception('La sesión no está suspendida');
        }

        $session->update([
            'status' => 'active',
            'resumed_at' => now(),
        ]);

        Log::info('Sesión reanudada', ['session_id' => $sessionId]);

        return $session;
    }

    /**
     * Agregar movimiento de caja
     */
    public function addCashMovement($sessionId, $data)
    {
        DB::beginTransaction();
        try {
            $session = CashSession::forCurrentTenant()->findOrFail($sessionId);

            if ($session->status !== 'active') {
                throw new \Exception('La sesión no está activa');
            }

            // Validar PIN de manager si es requerido
            if (isset($data['manager_pin'])) {
                $this->validateManagerPin($data['manager_pin']);
            }

            // Crear movimiento
            $movement = $session->movements()->create([
                'type' => $data['type'],
                'amount' => $data['amount'],
                'description' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'user_id' => Auth::id(),
                'authorized_by' => isset($data['manager_pin']) ? Auth::id() : null,
                'tenant_id' => $this->getCurrentTenantId(),
            ]);

            // Actualizar monto actual
            $newAmount = $session->current_amount;
            if ($data['type'] === 'cash_in') {
                $newAmount += $data['amount'];
            } else {
                $newAmount -= $data['amount'];
            }

            $session->update(['current_amount' => $newAmount]);

            DB::commit();

            Log::info('Movimiento de caja registrado', [
                'session_id' => $sessionId,
                'type' => $data['type'],
                'amount' => $data['amount'],
            ]);

            return $movement;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al registrar movimiento: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener balance actual
     */
    public function getCurrentBalance($sessionId)
    {
        $session = CashSession::forCurrentTenant()->findOrFail($sessionId);
        $summary = $this->getSessionSummary($sessionId);

        return [
            'current_amount' => $session->current_amount,
            'expected_amount' => $summary['expected_amount'],
            'difference' => $summary['difference'],
            'sales_total' => $summary['sales']['total'],
            'refunds_total' => $summary['refunds']['total'],
            'movements_in' => $summary['movements']['cash_in'],
            'movements_out' => $summary['movements']['cash_out'],
        ];
    }

    /**
     * Generar reporte de sesión
     */
    public function generateSessionReport($sessionId)
    {
        $session = $this->getSessionDetails($sessionId);
        $summary = $this->getSessionSummary($sessionId);
        $transactions = $this->getSessionTransactions($sessionId);
        $movements = $this->getSessionMovements($sessionId);

        $report = [
            'session' => $session,
            'summary' => $summary,
            'transactions' => $transactions,
            'movements' => $movements,
            'generated_at' => now(),
            'generated_by' => Auth::user()->name,
        ];

        // Aquí se podría generar un PDF o formato específico
        return $report;
    }

    /**
     * Transferir efectivo entre sesiones
     */
    public function transferCash($data)
    {
        DB::beginTransaction();
        try {
            // Validar PIN de manager
            $this->validateManagerPin($data['manager_pin']);

            $fromSession = CashSession::forCurrentTenant()->findOrFail($data['from_session_id']);
            $toSession = CashSession::forCurrentTenant()->findOrFail($data['to_session_id']);

            if ($fromSession->status !== 'active' || $toSession->status !== 'active') {
                throw new \Exception('Ambas sesiones deben estar activas');
            }

            if ($fromSession->current_amount < $data['amount']) {
                throw new \Exception('Fondos insuficientes en la sesión origen');
            }

            // Registrar movimientos
            $fromMovement = $fromSession->movements()->create([
                'type' => 'cash_out',
                'amount' => $data['amount'],
                'description' => 'Transferencia a sesión #' . $toSession->id,
                'notes' => $data['reason'],
                'user_id' => Auth::id(),
                'authorized_by' => Auth::id(),
                'tenant_id' => $this->getCurrentTenantId(),
            ]);

            $toMovement = $toSession->movements()->create([
                'type' => 'cash_in',
                'amount' => $data['amount'],
                'description' => 'Transferencia desde sesión #' . $fromSession->id,
                'notes' => $data['reason'],
                'user_id' => Auth::id(),
                'authorized_by' => Auth::id(),
                'tenant_id' => $this->getCurrentTenantId(),
            ]);

            // Actualizar montos
            $fromSession->decrement('current_amount', $data['amount']);
            $toSession->increment('current_amount', $data['amount']);

            DB::commit();

            Log::info('Transferencia de efectivo realizada', [
                'from_session' => $fromSession->id,
                'to_session' => $toSession->id,
                'amount' => $data['amount'],
            ]);

            return [
                'from_movement' => $fromMovement,
                'to_movement' => $toMovement,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en transferencia: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Realizar arqueo de caja
     */
    public function performCashCount($sessionId, $data)
    {
        DB::beginTransaction();
        try {
            $session = CashSession::forCurrentTenant()->findOrFail($sessionId);

            if ($session->status !== 'active') {
                throw new \Exception('La sesión no está activa');
            }

            $summary = $this->getSessionSummary($sessionId);
            $variance = $data['counted_amount'] - $session->current_amount;

            // Registrar arqueo
            $count = $session->cashCounts()->create([
                'counted_amount' => $data['counted_amount'],
                'expected_amount' => $session->current_amount,
                'variance' => $variance,
                'denominations' => $data['denominations'],
                'notes' => $data['notes'] ?? null,
                'performed_by' => Auth::id(),
                'tenant_id' => $this->getCurrentTenantId(),
            ]);

            // Si hay diferencia significativa, crear alerta
            if (abs($variance) > config('pos.cash_variance_threshold', 10)) {
                $this->createVarianceAlert($session, $variance);
            }

            DB::commit();

            Log::info('Arqueo de caja realizado', [
                'session_id' => $sessionId,
                'variance' => $variance,
            ]);

            return [
                'count' => $count,
                'variance' => $variance,
                'summary' => $summary,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en arqueo: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener historial de sesiones
     */
    public function getSessionHistory($userId, $limit = 30)
    {
        return CashSession::where('user_id', $userId)
            ->forCurrentTenant()
            ->with(['terminal'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Obtener estadísticas del usuario
     */
    public function getUserStatistics($userId)
    {
        $sessions = CashSession::where('user_id', $userId)
            ->forCurrentTenant()
            ->get();

        return [
            'total_sessions' => $sessions->count(),
            'active_sessions' => $sessions->where('status', 'active')->count(),
            'total_sales' => Transaction::whereIn('cash_session_id', $sessions->pluck('id'))
                ->where('type', 'sale')
                ->where('status', 'completed')
                ->sum('total'),
            'average_session_duration' => $this->calculateAverageSessionDuration($sessions),
            'total_variance' => $sessions->sum('difference'),
        ];
    }

    /**
     * Exportar sesiones
     */
    public function exportSessions($filters)
    {
        $sessions = CashSession::query()
            ->with(['user', 'terminal'])
            ->forCurrentTenant()
            ->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);

        if (isset($filters['user_id'])) {
            $sessions->where('user_id', $filters['user_id']);
        }

        $sessions = $sessions->get();

        switch ($filters['format']) {
            case 'pdf':
                return $this->exportToPdf($sessions);
            case 'excel':
                return $this->exportToExcel($sessions);
            case 'csv':
                return $this->exportToCsv($sessions);
            default:
                throw new \Exception('Formato de exportación no válido');
        }
    }

    /**
     * Obtener sesiones activas
     */
    public function getActiveSessions()
    {
        return CashSession::where('status', 'active')
            ->forCurrentTenant()
            ->with(['user', 'terminal'])
            ->get()
            ->map(function ($session) {
                $session->current_balance = $this->getCurrentBalance($session->id);
                return $session;
            });
    }

    /**
     * Métodos privados de ayuda
     */
    private function calculateSessionTotals($sessionId)
    {
        $transactions = Transaction::where('cash_session_id', $sessionId)->get();
        
        return [
            'sales' => $transactions->where('type', 'sale')->where('status', 'completed')->sum('total'),
            'refunds' => $transactions->where('type', 'refund')->sum('total'),
            'voids' => $transactions->where('status', 'voided')->count(),
            'transaction_count' => $transactions->count(),
        ];
    }

    private function validateManagerPin($pin)
    {
        // Aquí se implementaría la validación del PIN del manager
        // Por ahora, validamos contra el usuario actual con rol de manager
        $user = Auth::user();
        
        if (!$user->hasRole(['admin', 'manager'])) {
            throw new \Exception('Usuario no autorizado');
        }

        if (!Hash::check($pin, $user->pin ?? $user->password)) {
            throw new \Exception('PIN incorrecto');
        }

        return true;
    }

    private function clearSessionCache($userId)
    {
        Cache::tags(['cash_sessions', "user_{$userId}"])->flush();
    }

    private function createVarianceAlert($session, $variance)
    {
        // Crear notificación o alerta para managers
        Log::warning('Varianza significativa detectada', [
            'session_id' => $session->id,
            'variance' => $variance,
            'user_id' => $session->user_id,
        ]);
    }

    private function calculateAverageSessionDuration($sessions)
    {
        $totalDuration = 0;
        $closedSessions = $sessions->where('status', 'closed');

        foreach ($closedSessions as $session) {
            if ($session->closed_at) {
                $totalDuration += $session->opened_at->diffInMinutes($session->closed_at);
            }
        }

        return $closedSessions->count() > 0 
            ? round($totalDuration / $closedSessions->count()) 
            : 0;
    }

    private function exportToPdf($sessions)
    {
        $pdf = PDF::loadView('pos.reports.cash-sessions', compact('sessions'));
        $filename = 'sesiones_caja_' . now()->format('Y-m-d') . '.pdf';
        
        return [
            'file' => $pdf->download()->getFile(),
            'filename' => $filename,
        ];
    }

    private function exportToExcel($sessions)
    {
        // Implementar exportación a Excel
        $filename = 'sesiones_caja_' . now()->format('Y-m-d') . '.xlsx';
        
        return [
            'file' => storage_path("app/exports/{$filename}"),
            'filename' => $filename,
        ];
    }

    private function exportToCsv($sessions)
    {
        // Implementar exportación a CSV
        $filename = 'sesiones_caja_' . now()->format('Y-m-d') . '.csv';
        
        return [
            'file' => storage_path("app/exports/{$filename}"),
            'filename' => $filename,
        ];
    }
}