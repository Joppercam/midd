<?php

namespace App\Http\Controllers\SII;

use App\Http\Controllers\Controller;
use App\Services\SII\XSDValidatorService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SchemaController extends Controller
{
    private XSDValidatorService $validator;

    public function __construct(XSDValidatorService $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Show schema management page
     */
    public function index()
    {
        return Inertia::render('SII/Schemas', [
            'schemas' => $this->validator->getAvailableSchemas(),
            'schemasExist' => $this->validator->schemasExist(),
        ]);
    }

    /**
     * Download all schemas
     */
    public function download()
    {
        try {
            $results = $this->validator->downloadSchemas();
            
            $successful = collect($results)->where('success', true)->count();
            $failed = collect($results)->where('success', false)->count();

            if ($failed > 0) {
                return redirect()->back()->with('warning', 
                    "Descarga parcial: {$successful} esquemas descargados, {$failed} fallaron.");
            }

            return redirect()->back()->with('success', 
                "Todos los esquemas XSD han sido descargados correctamente ({$successful} archivos).");

        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'download' => 'Error al descargar esquemas: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Validate XML document
     */
    public function validate(Request $request)
    {
        $request->validate([
            'xml' => 'required|string',
            'type' => 'required|in:DTE,EnvioDTE,EnvioBOLETA,RespuestaDTE,ReciboDTE',
        ]);

        try {
            $result = $this->validator->validateDTE($request->xml, $request->type);
            
            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Error al validar XML: ' . $e->getMessage(),
                'errors' => [],
            ], 500);
        }
    }

    /**
     * Validate DTE data before XML generation
     */
    public function validateData(Request $request)
    {
        $request->validate([
            'data' => 'required|array',
        ]);

        try {
            $result = $this->validator->validateDTEData($request->data);
            
            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'errors' => ['Error al validar datos: ' . $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Get schema content
     */
    public function show($schema)
    {
        $schemas = collect($this->validator->getAvailableSchemas());
        $schemaInfo = $schemas->firstWhere('name', $schema);

        if (!$schemaInfo || !$schemaInfo['exists']) {
            abort(404, 'Schema not found');
        }

        $schemaPath = storage_path('app/sii/schemas/' . $schemaInfo['filename']);
        $content = file_get_contents($schemaPath);

        return response($content, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}