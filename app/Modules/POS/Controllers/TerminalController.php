<?php

namespace App\Modules\POS\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\POS\Services\TerminalService;
use App\Modules\POS\Requests\TerminalRequest;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TerminalController extends Controller
{
    use ChecksPermissions;

    public function __construct(private TerminalService $terminalService)
    {
        $this->middleware(['auth', 'check.module:pos']);
    }

    public function index()
    {
        $this->checkPermission('pos.terminals.view');

        $terminals = $this->terminalService->getTerminalsList();

        return Inertia::render('POS/Terminals/Index', [
            'terminals' => $terminals,
            'statistics' => $this->terminalService->getTerminalsStatistics(),
        ]);
    }

    public function create()
    {
        $this->checkPermission('pos.terminals.create');

        $data = [
            'locations' => $this->terminalService->getAvailableLocations(),
            'printers' => $this->terminalService->getAvailablePrinters(),
            'templates' => $this->terminalService->getTerminalTemplates(),
        ];

        return Inertia::render('POS/Terminals/Create', $data);
    }

    public function store(TerminalRequest $request)
    {
        $this->checkPermission('pos.terminals.create');

        try {
            $terminal = $this->terminalService->createTerminal($request->validated());

            return redirect()->route('pos.terminals.show', $terminal->id)
                ->with('success', 'Terminal creado exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $this->checkPermission('pos.terminals.view');

        $terminal = $this->terminalService->getTerminalDetails($id);

        return Inertia::render('POS/Terminals/Show', [
            'terminal' => $terminal,
            'activeSessions' => $this->terminalService->getTerminalSessions($id),
            'recentActivity' => $this->terminalService->getTerminalActivity($id),
            'performance' => $this->terminalService->getTerminalPerformance($id),
        ]);
    }

    public function edit($id)
    {
        $this->checkPermission('pos.terminals.edit');

        $terminal = $this->terminalService->getTerminal($id);
        
        $data = [
            'terminal' => $terminal,
            'locations' => $this->terminalService->getAvailableLocations(),
            'printers' => $this->terminalService->getAvailablePrinters(),
        ];

        return Inertia::render('POS/Terminals/Edit', $data);
    }

    public function update(TerminalRequest $request, $id)
    {
        $this->checkPermission('pos.terminals.edit');

        try {
            $terminal = $this->terminalService->updateTerminal($id, $request->validated());

            return redirect()->route('pos.terminals.show', $terminal->id)
                ->with('success', 'Terminal actualizado exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        $this->checkPermission('pos.terminals.delete');

        try {
            $this->terminalService->deleteTerminal($id);

            return redirect()->route('pos.terminals.index')
                ->with('success', 'Terminal eliminado exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function activate($id)
    {
        $this->checkPermission('pos.terminals.edit');

        try {
            $this->terminalService->activateTerminal($id);

            return response()->json([
                'success' => true,
                'message' => 'Terminal activado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function deactivate($id)
    {
        $this->checkPermission('pos.terminals.edit');

        try {
            $this->terminalService->deactivateTerminal($id);

            return response()->json([
                'success' => true,
                'message' => 'Terminal desactivado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function assign(Request $request, $id)
    {
        $this->checkPermission('pos.terminals.assign');

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $this->terminalService->assignTerminal($id, $request->user_id, $request->notes);

            return response()->json([
                'success' => true,
                'message' => 'Terminal asignado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function unassign($id)
    {
        $this->checkPermission('pos.terminals.assign');

        try {
            $this->terminalService->unassignTerminal($id);

            return response()->json([
                'success' => true,
                'message' => 'Terminal liberado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function testPrinter(Request $request, $id)
    {
        $this->checkPermission('pos.settings.printers');

        $request->validate([
            'printer_type' => 'required|in:receipt,kitchen,fiscal',
        ]);

        try {
            $result = $this->terminalService->testPrinter($id, $request->printer_type);

            return response()->json([
                'success' => true,
                'result' => $result,
                'message' => 'Prueba de impresora completada',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function restartTerminal($id)
    {
        $this->checkPermission('pos.terminals.edit');

        try {
            $this->terminalService->restartTerminal($id);

            return response()->json([
                'success' => true,
                'message' => 'Terminal reiniciado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getStatus($id)
    {
        $status = $this->terminalService->getTerminalStatus($id);

        return response()->json([
            'status' => $status,
            'timestamp' => now(),
        ]);
    }

    public function updateSettings(Request $request, $id)
    {
        $this->checkPermission('pos.settings.manage');

        $request->validate([
            'settings' => 'required|array',
            'settings.receipt_printer' => 'nullable|string',
            'settings.kitchen_printer' => 'nullable|string',
            'settings.auto_print_receipt' => 'boolean',
            'settings.sound_enabled' => 'boolean',
            'settings.theme' => 'in:light,dark',
            'settings.timeout' => 'integer|min:300|max:86400',
        ]);

        try {
            $this->terminalService->updateTerminalSettings($id, $request->settings);

            return response()->json([
                'success' => true,
                'message' => 'Configuración actualizada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function maintenance($id)
    {
        $this->checkPermission('pos.terminals.edit');

        $terminal = $this->terminalService->getTerminal($id);

        return Inertia::render('POS/Terminals/Maintenance', [
            'terminal' => $terminal,
            'diagnostics' => $this->terminalService->runDiagnostics($id),
            'logs' => $this->terminalService->getTerminalLogs($id),
        ]);
    }

    public function clearCache($id)
    {
        $this->checkPermission('pos.terminals.edit');

        try {
            $this->terminalService->clearTerminalCache($id);

            return response()->json([
                'success' => true,
                'message' => 'Cache eliminado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function backup($id)
    {
        $this->checkPermission('pos.backup.create');

        try {
            $backup = $this->terminalService->createTerminalBackup($id);

            return response()->json([
                'success' => true,
                'backup' => $backup,
                'message' => 'Respaldo creado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function restore(Request $request, $id)
    {
        $this->checkPermission('pos.backup.restore');

        $request->validate([
            'backup_file' => 'required|file|mimes:zip',
        ]);

        try {
            $this->terminalService->restoreTerminalBackup($id, $request->file('backup_file'));

            return response()->json([
                'success' => true,
                'message' => 'Respaldo restaurado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}