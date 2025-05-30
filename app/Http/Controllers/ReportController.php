<?php

namespace App\Http\Controllers;

use App\Models\ReportTemplate;
use App\Models\ScheduledReport;
use App\Models\ReportExecution;
use App\Models\ReportFilter;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->middleware(['auth', 'tenant']);
        $this->reportService = $reportService;
    }

    /**
     * Reports dashboard
     */
    public function index()
    {
        $templates = ReportTemplate::active()->get();
        $recentExecutions = ReportExecution::where('tenant_id', tenant()->id)
            ->with(['reportTemplate', 'user'])
            ->latest()
            ->limit(10)
            ->get();
        
        $scheduledReports = ScheduledReport::where('tenant_id', tenant()->id)
            ->with(['reportTemplate'])
            ->latest()
            ->limit(5)
            ->get();

        $statistics = $this->reportService->getReportStatistics();

        return Inertia::render('Reports/Index', [
            'templates' => $templates,
            'recentExecutions' => $recentExecutions,
            'scheduledReports' => $scheduledReports,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Show report templates
     */
    public function templates()
    {
        $templates = ReportTemplate::active()
            ->withCount(['scheduledReports', 'reportExecutions'])
            ->get();

        return Inertia::render('Reports/Templates/Index', [
            'templates' => $templates,
        ]);
    }

    /**
     * Show template details and generate form
     */
    public function showTemplate(ReportTemplate $template)
    {
        $template->load(['reportFilters' => function($query) {
            $query->accessibleByUser(auth()->id());
        }]);

        return Inertia::render('Reports/Templates/Show', [
            'template' => $template,
            'filters' => $template->reportFilters,
        ]);
    }

    /**
     * Generate report manually
     */
    public function generate(Request $request, ReportTemplate $template)
    {
        $request->validate([
            'parameters' => 'array',
            'format' => 'required|in:pdf,excel,csv',
        ]);

        try {
            $execution = $this->reportService->generateReport(
                $template,
                $request->parameters ?? [],
                $request->format
            );

            return back()->with('success', 'Reporte en cola de generación. Recibirás una notificación cuando esté listo.')
                         ->with('execution_id', $execution->id);

        } catch (\Exception $e) {
            return back()->withErrors(['generation' => $e->getMessage()]);
        }
    }

    /**
     * Show scheduled reports
     */
    public function scheduled()
    {
        $scheduledReports = ScheduledReport::where('tenant_id', tenant()->id)
            ->with(['reportTemplate', 'user', 'lastExecution'])
            ->withCount(['reportExecutions', 'successfulExecutions', 'failedExecutions'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Reports/Scheduled/Index', [
            'scheduledReports' => $scheduledReports,
        ]);
    }

    /**
     * Create scheduled report form
     */
    public function createScheduled()
    {
        $templates = ReportTemplate::active()->get();

        return Inertia::render('Reports/Scheduled/Create', [
            'templates' => $templates,
        ]);
    }

    /**
     * Store scheduled report
     */
    public function storeScheduled(Request $request)
    {
        $request->validate([
            'report_template_id' => 'required|exists:report_templates,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'frequency' => 'required|in:daily,weekly,monthly,quarterly,yearly',
            'frequency_details' => 'required|array',
            'parameters' => 'array',
            'format' => 'required|in:pdf,excel,csv',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'email',
            'auto_send' => 'boolean',
            'store_file' => 'boolean',
        ]);

        try {
            $scheduledReport = $this->reportService->createScheduledReport($request->all());

            return redirect()->route('reports.scheduled.show', $scheduledReport)
                           ->with('success', 'Reporte programado creado exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['creation' => $e->getMessage()]);
        }
    }

    /**
     * Show scheduled report
     */
    public function showScheduled(ScheduledReport $scheduledReport)
    {
        $this->authorize('view', $scheduledReport);

        $scheduledReport->load([
            'reportTemplate',
            'user',
            'reportExecutions' => function($query) {
                $query->latest()->limit(20);
            }
        ]);

        return Inertia::render('Reports/Scheduled/Show', [
            'scheduledReport' => $scheduledReport,
        ]);
    }

    /**
     * Edit scheduled report
     */
    public function editScheduled(ScheduledReport $scheduledReport)
    {
        $this->authorize('update', $scheduledReport);

        $templates = ReportTemplate::active()->get();

        return Inertia::render('Reports/Scheduled/Edit', [
            'scheduledReport' => $scheduledReport,
            'templates' => $templates,
        ]);
    }

    /**
     * Update scheduled report
     */
    public function updateScheduled(Request $request, ScheduledReport $scheduledReport)
    {
        $this->authorize('update', $scheduledReport);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'frequency' => 'required|in:daily,weekly,monthly,quarterly,yearly',
            'frequency_details' => 'required|array',
            'parameters' => 'array',
            'format' => 'required|in:pdf,excel,csv',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'email',
            'auto_send' => 'boolean',
            'store_file' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $scheduledReport->update($request->all());
        $scheduledReport->updateNextRun();

        return redirect()->route('reports.scheduled.show', $scheduledReport)
                       ->with('success', 'Reporte programado actualizado exitosamente.');
    }

    /**
     * Delete scheduled report
     */
    public function destroyScheduled(ScheduledReport $scheduledReport)
    {
        $this->authorize('delete', $scheduledReport);

        $scheduledReport->delete();

        return redirect()->route('reports.scheduled')
                       ->with('success', 'Reporte programado eliminado exitosamente.');
    }

    /**
     * Show report executions
     */
    public function executions()
    {
        $executions = ReportExecution::where('tenant_id', tenant()->id)
            ->with(['reportTemplate', 'scheduledReport', 'user'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Reports/Executions/Index', [
            'executions' => $executions,
        ]);
    }

    /**
     * Show report execution
     */
    public function showExecution(ReportExecution $execution)
    {
        $this->authorize('view', $execution);

        $execution->load(['reportTemplate', 'scheduledReport', 'user']);

        return Inertia::render('Reports/Executions/Show', [
            'execution' => $execution,
        ]);
    }

    /**
     * Download report file
     */
    public function downloadExecution(ReportExecution $execution)
    {
        $this->authorize('view', $execution);

        if (!$execution->fileExists()) {
            return back()->withErrors(['download' => 'El archivo del reporte no está disponible.']);
        }

        $filename = $execution->getDownloadFilename();
        $filePath = $execution->file_path;

        return Storage::download($filePath, $filename);
    }

    /**
     * Delete report execution
     */
    public function destroyExecution(ReportExecution $execution)
    {
        $this->authorize('delete', $execution);

        // Delete the file if it exists
        $execution->deleteFile();
        
        // Delete the execution record
        $execution->delete();

        return back()->with('success', 'Ejecución de reporte eliminada exitosamente.');
    }

    /**
     * Run scheduled report manually
     */
    public function runScheduled(ScheduledReport $scheduledReport)
    {
        $this->authorize('update', $scheduledReport);

        try {
            $execution = $this->reportService->generateReport(
                $scheduledReport->reportTemplate,
                $scheduledReport->parameters ?? [],
                $scheduledReport->format,
                auth()->user(),
                $scheduledReport
            );

            return back()->with('success', 'Reporte ejecutado manualmente. Recibirás una notificación cuando esté listo.')
                         ->with('execution_id', $execution->id);

        } catch (\Exception $e) {
            return back()->withErrors(['execution' => $e->getMessage()]);
        }
    }

    /**
     * Toggle scheduled report status
     */
    public function toggleScheduled(ScheduledReport $scheduledReport)
    {
        $this->authorize('update', $scheduledReport);

        $scheduledReport->update(['is_active' => !$scheduledReport->is_active]);
        
        if ($scheduledReport->is_active) {
            $scheduledReport->updateNextRun();
        }

        $status = $scheduledReport->is_active ? 'activado' : 'desactivado';
        return back()->with('success', "Reporte programado {$status} exitosamente.");
    }

    /**
     * Report statistics API
     */
    public function statistics()
    {
        $statistics = $this->reportService->getReportStatistics();
        return response()->json($statistics);
    }

    /**
     * Clean up old reports
     */
    public function cleanup(Request $request)
    {
        $request->validate([
            'days_old' => 'integer|min:1|max:365',
        ]);

        $daysOld = $request->days_old ?? 30;
        $results = $this->reportService->cleanupOldReports($daysOld);

        return back()->with('success', 
            "Limpieza completada: {$results['deleted_files']} archivos eliminados, " .
            "{$results['formatted_freed_space']} liberados."
        );
    }
}