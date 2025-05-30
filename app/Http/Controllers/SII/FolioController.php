<?php

namespace App\Http\Controllers\SII;

use App\Http\Controllers\Controller;
use App\Services\SII\FolioManagerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Exception;

class FolioController extends Controller
{
    protected FolioManagerService $folioManager;

    public function __construct(FolioManagerService $folioManager)
    {
        $this->folioManager = $folioManager;
    }

    /**
     * Display folio management dashboard
     */
    public function index()
    {
        $tenant = Auth::user()->tenant;
        $folioStatuses = $this->folioManager->getAllFolioStatuses($tenant);
        $documentTypes = $this->folioManager->getDocumentTypes();

        return Inertia::render('SII/FolioManagement', [
            'folioStatuses' => $folioStatuses,
            'documentTypes' => $documentTypes,
        ]);
    }

    /**
     * Get folio status for a specific document type
     */
    public function getStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_type' => 'required|integer|in:33,34,39,52,56,61',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $tenant = Auth::user()->tenant;
            $status = $this->folioManager->getFolioStatus($tenant, $request->document_type);

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update folio range for a document type
     */
    public function updateRange(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_type' => 'required|integer|in:33,34,39,52,56,61',
            'range_from' => 'required|integer|min:1',
            'range_to' => 'required|integer|min:1',
            'caf_xml' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $tenant = Auth::user()->tenant;
            
            $result = $this->folioManager->updateFolioRange(
                $tenant,
                $request->document_type,
                $request->range_from,
                $request->range_to,
                $request->caf_xml ?? ''
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Rango de folios actualizado correctamente',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el rango de folios',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reserve a specific folio
     */
    public function reserveFolio(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_type' => 'required|integer|in:33,34,39,52,56,61',
            'folio' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $tenant = Auth::user()->tenant;
            
            $result = $this->folioManager->reserveFolio(
                $tenant,
                $request->document_type,
                $request->folio
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => "Folio {$request->folio} reservado correctamente",
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al reservar el folio',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Release a folio (cancel document)
     */
    public function releaseFolio(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_type' => 'required|integer|in:33,34,39,52,56,61',
            'folio' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $tenant = Auth::user()->tenant;
            
            $result = $this->folioManager->releaseFolio(
                $tenant,
                $request->document_type,
                $request->folio
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => "Folio {$request->folio} liberado correctamente",
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al liberar el folio o folio no encontrado',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate folio report
     */
    public function generateReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $tenant = Auth::user()->tenant;
            $fromDate = $request->date_from ? \Carbon\Carbon::parse($request->date_from) : null;
            $toDate = $request->date_to ? \Carbon\Carbon::parse($request->date_to) : null;
            
            $report = $this->folioManager->generateFolioReport($tenant, $fromDate, $toDate);

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get next available folio for a document type
     */
    public function getNextFolio(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_type' => 'required|integer|in:33,34,39,52,56,61',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $tenant = Auth::user()->tenant;
            $nextFolio = $this->folioManager->getNextFolio($tenant, $request->document_type);

            return response()->json([
                'success' => true,
                'data' => [
                    'next_folio' => $nextFolio,
                    'document_type' => $request->document_type,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}