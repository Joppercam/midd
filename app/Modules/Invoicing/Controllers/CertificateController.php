<?php

namespace App\Modules\Invoicing\Controllers;

use App\Http\Controllers\Controller;
use App\Services\SII\XMLSignerService;
use App\Traits\ChecksPermissions;
use App\Modules\Invoicing\Services\CertificateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Exception;

class CertificateController extends Controller
{
    use ChecksPermissions;

    protected $xmlSigner;
    protected $certificateService;

    public function __construct(XMLSignerService $xmlSigner, CertificateService $certificateService)
    {
        $this->middleware(['auth', 'verified', 'check.subscription', 'check.module:invoicing']);
        $this->xmlSigner = $xmlSigner;
        $this->certificateService = $certificateService;
    }

    public function index()
    {
        $this->checkPermission('sii.certificates');
        
        $tenant = auth()->user()->tenant;
        $certificateInfo = $this->certificateService->getCertificateInfo($tenant);
        
        return Inertia::render('Invoicing/SII/Certificate', [
            'hasCertificate' => $certificateInfo['exists'],
            'certificateInfo' => $certificateInfo['info'],
            'isExpiringSoon' => $certificateInfo['expires_soon'] ?? false,
            'daysUntilExpiry' => $certificateInfo['days_until_expiry'] ?? null,
        ]);
    }

    public function upload(Request $request)
    {
        $this->checkPermission('sii.certificates');
        
        $request->validate([
            'certificate' => 'required|file|mimes:p12,pfx|max:5120', // 5MB max
            'password' => 'required|string|min:1|max:255',
            'alias' => 'nullable|string|max:100',
        ]);

        $tenant = auth()->user()->tenant;

        try {
            $result = $this->certificateService->uploadCertificate(
                $tenant,
                $request->file('certificate'),
                $request->password,
                $request->alias
            );

            if ($result['success']) {
                return redirect()->back()->with('success', $result['message']);
            } else {
                return redirect()->back()->withErrors(['error' => $result['message']]);
            }

        } catch (Exception $e) {
            Log::error('Certificate upload error', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Error al subir el certificado. Por favor, verifique que el archivo y la contraseña sean correctos.'
            ]);
        }
    }

    public function destroy()
    {
        $this->checkPermission('sii.certificates');
        
        $tenant = auth()->user()->tenant;

        try {
            $result = $this->certificateService->deleteCertificate($tenant);
            
            if ($result['success']) {
                return redirect()->back()->with('success', $result['message']);
            } else {
                return redirect()->back()->withErrors(['error' => $result['message']]);
            }

        } catch (Exception $e) {
            Log::error('Certificate deletion error', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Error al eliminar el certificado.'
            ]);
        }
    }

    public function test(Request $request)
    {
        $this->checkPermission('sii.certificates');
        
        $tenant = auth()->user()->tenant;

        try {
            $result = $this->certificateService->testCertificate($tenant);
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'details' => $result['details'] ?? null,
            ]);

        } catch (Exception $e) {
            Log::error('Certificate test error', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al probar el certificado: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function info()
    {
        $this->checkPermission('sii.certificates');
        
        $tenant = auth()->user()->tenant;

        try {
            $info = $this->certificateService->getCertificateInfo($tenant);
            
            return response()->json([
                'success' => true,
                'info' => $info,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function renew(Request $request)
    {
        $this->checkPermission('sii.certificates');
        
        $request->validate([
            'certificate' => 'required|file|mimes:p12,pfx|max:5120',
            'password' => 'required|string|min:1|max:255',
            'alias' => 'nullable|string|max:100',
        ]);

        $tenant = auth()->user()->tenant;

        try {
            // First backup the current certificate
            $backupResult = $this->certificateService->backupCertificate($tenant);
            
            if (!$backupResult['success']) {
                return redirect()->back()->withErrors(['error' => $backupResult['message']]);
            }

            // Upload the new certificate
            $result = $this->certificateService->uploadCertificate(
                $tenant,
                $request->file('certificate'),
                $request->password,
                $request->alias,
                true // renewal flag
            );

            if ($result['success']) {
                // Test the new certificate
                $testResult = $this->certificateService->testCertificate($tenant);
                
                if (!$testResult['success']) {
                    // Restore backup if test fails
                    $this->certificateService->restoreCertificateBackup($tenant);
                    
                    return redirect()->back()->withErrors([
                        'error' => 'El nuevo certificado no pasó las pruebas. Se restauró el certificado anterior.'
                    ]);
                }

                return redirect()->back()->with('success', 'Certificado renovado exitosamente.');
            } else {
                return redirect()->back()->withErrors(['error' => $result['message']]);
            }

        } catch (Exception $e) {
            Log::error('Certificate renewal error', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Try to restore backup
            $this->certificateService->restoreCertificateBackup($tenant);

            return redirect()->back()->withErrors([
                'error' => 'Error durante la renovación del certificado. Se restauró el certificado anterior.'
            ]);
        }
    }

    public function downloadBackup()
    {
        $this->checkPermission('sii.certificates');
        
        $tenant = auth()->user()->tenant;

        try {
            $download = $this->certificateService->downloadCertificateBackup($tenant);
            
            return $download;

        } catch (Exception $e) {
            Log::error('Certificate backup download error', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Error al descargar el respaldo del certificado.'
            ]);
        }
    }

    public function history()
    {
        $this->checkPermission('sii.certificates');
        
        $tenant = auth()->user()->tenant;
        $history = $this->certificateService->getCertificateHistory($tenant);
        
        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }

    public function validateCertificate(Request $request)
    {
        $this->checkPermission('sii.certificates');
        
        $request->validate([
            'certificate' => 'required|file|mimes:p12,pfx|max:5120',
            'password' => 'required|string',
        ]);

        try {
            $result = $this->certificateService->validateCertificateFile(
                $request->file('certificate'),
                $request->password
            );
            
            return response()->json([
                'success' => $result['valid'],
                'message' => $result['message'],
                'info' => $result['info'] ?? null,
                'warnings' => $result['warnings'] ?? [],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al validar el certificado: ' . $e->getMessage(),
            ], 422);
        }
    }
}