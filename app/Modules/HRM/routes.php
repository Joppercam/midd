<?php

use Illuminate\Support\Facades\Route;
use App\Modules\HRM\Controllers\EmployeeController;
use App\Modules\HRM\Controllers\AttendanceController;
use App\Modules\HRM\Controllers\PayrollController;

/*
|--------------------------------------------------------------------------
| HRM Module Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'check.subscription', 'check.module:hrm'])->group(function () {
    
    // Employee Management Routes
    Route::prefix('hrm/employees')->name('hrm.employees.')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->name('index');
        Route::get('/create', [EmployeeController::class, 'create'])->name('create');
        Route::post('/', [EmployeeController::class, 'store'])->name('store');
        Route::get('/organization-chart', [EmployeeController::class, 'organizationChart'])->name('organization-chart');
        Route::get('/export', [EmployeeController::class, 'export'])->name('export');
        Route::get('/import-template', [EmployeeController::class, 'importTemplate'])->name('import-template');
        Route::post('/import', [EmployeeController::class, 'import'])->name('import');
        
        Route::get('/{employee}', [EmployeeController::class, 'show'])->name('show');
        Route::get('/{employee}/edit', [EmployeeController::class, 'edit'])->name('edit');
        Route::put('/{employee}', [EmployeeController::class, 'update'])->name('update');
        Route::delete('/{employee}', [EmployeeController::class, 'destroy'])->name('destroy');
        
        // Employee Actions
        Route::post('/{employee}/deactivate', [EmployeeController::class, 'deactivate'])->name('deactivate');
        Route::post('/{employee}/reactivate', [EmployeeController::class, 'reactivate'])->name('reactivate');
        
        // Employee Documents
        Route::get('/{employee}/documents', [EmployeeController::class, 'documents'])->name('documents');
        Route::post('/{employee}/documents', [EmployeeController::class, 'uploadDocument'])->name('documents.upload');
        
        // Employee Contracts
        Route::get('/{employee}/contracts', [EmployeeController::class, 'contracts'])->name('contracts');
        Route::post('/{employee}/contracts', [EmployeeController::class, 'createContract'])->name('contracts.create');
        
        // Emergency Contacts
        Route::get('/{employee}/emergency-contact', [EmployeeController::class, 'emergencyContacts'])->name('emergency-contact');
        Route::put('/{employee}/emergency-contact', [EmployeeController::class, 'updateEmergencyContact'])->name('emergency-contact.update');
        
        // Bank Account
        Route::get('/{employee}/bank-account', [EmployeeController::class, 'bankAccount'])->name('bank-account');
        Route::put('/{employee}/bank-account', [EmployeeController::class, 'updateBankAccount'])->name('bank-account.update');
    });
    
    // Attendance Management Routes
    Route::prefix('hrm/attendance')->name('hrm.attendance.')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('index');
        Route::post('/check-in', [AttendanceController::class, 'checkIn'])->name('check-in');
        Route::post('/check-out', [AttendanceController::class, 'checkOut'])->name('check-out');
        Route::get('/today-status', [AttendanceController::class, 'todayStatus'])->name('today-status');
        Route::get('/calendar', [AttendanceController::class, 'calendar'])->name('calendar');
        Route::get('/monthly-report', [AttendanceController::class, 'monthlyReport'])->name('monthly-report');
        Route::get('/overtime', [AttendanceController::class, 'overtime'])->name('overtime');
        Route::get('/late-report', [AttendanceController::class, 'lateReport'])->name('late-report');
        Route::get('/export', [AttendanceController::class, 'export'])->name('export');
        
        // Bulk Import
        Route::get('/bulk-import', [AttendanceController::class, 'bulkImport'])->name('bulk-import');
        Route::get('/import-template', [AttendanceController::class, 'importTemplate'])->name('import-template');
        Route::post('/import', [AttendanceController::class, 'import'])->name('import');
        
        // Individual Attendance
        Route::get('/{attendance}/edit', [AttendanceController::class, 'edit'])->name('edit');
        Route::put('/{attendance}', [AttendanceController::class, 'update'])->name('update');
        Route::post('/{attendance}/approve', [AttendanceController::class, 'approve'])->name('approve');
        
        // Employee Summary
        Route::get('/employee/{employee}/summary', [AttendanceController::class, 'summary'])->name('employee.summary');
    });
    
    // Payroll Management Routes
    Route::prefix('hrm/payroll')->name('hrm.payroll.')->group(function () {
        Route::get('/', [PayrollController::class, 'index'])->name('index');
        Route::get('/create', [PayrollController::class, 'create'])->name('create');
        Route::post('/', [PayrollController::class, 'store'])->name('store');
        Route::get('/summary', [PayrollController::class, 'summary'])->name('summary');
        Route::get('/tax-report', [PayrollController::class, 'taxReport'])->name('tax-report');
        Route::get('/social-security-report', [PayrollController::class, 'socialSecurityReport'])->name('social-security-report');
        Route::get('/export', [PayrollController::class, 'export'])->name('export');
        
        Route::get('/{payroll}', [PayrollController::class, 'show'])->name('show');
        Route::get('/{payroll}/edit', [PayrollController::class, 'edit'])->name('edit');
        Route::put('/{payroll}', [PayrollController::class, 'update'])->name('update');
        
        // Payroll Actions
        Route::post('/{payroll}/calculate', [PayrollController::class, 'calculate'])->name('calculate');
        Route::post('/{payroll}/approve', [PayrollController::class, 'approve'])->name('approve');
        Route::post('/{payroll}/process', [PayrollController::class, 'process'])->name('process');
        Route::get('/{payroll}/bank-file', [PayrollController::class, 'bankFile'])->name('bank-file');
        Route::get('/{payroll}/bulk-payslips', [PayrollController::class, 'bulkPayslips'])->name('bulk-payslips');
        
        // Deductions and Bonuses
        Route::post('/{payroll}/deductions', [PayrollController::class, 'addDeduction'])->name('deductions.add');
        Route::post('/{payroll}/bonuses', [PayrollController::class, 'addBonus'])->name('bonuses.add');
        
        // Individual Payslip
        Route::get('/{payroll}/employee/{employee}/payslip', [PayrollController::class, 'payslip'])->name('payslip');
    });
    
    // Leave Management Routes (placeholder for future)
    Route::prefix('hrm/leaves')->name('hrm.leaves.')->group(function () {
        Route::get('/', function () {
            return inertia('HRM/Leaves/Index');
        })->name('index');
        Route::get('/calendar', function () {
            return inertia('HRM/Leaves/Calendar');
        })->name('calendar');
        Route::get('/balance', function () {
            return inertia('HRM/Leaves/Balance');
        })->name('balance');
    });
    
    // Performance Management Routes (placeholder for future)
    Route::prefix('hrm/performance')->name('hrm.performance.')->group(function () {
        Route::get('/', function () {
            return inertia('HRM/Performance/Index');
        })->name('index');
        Route::get('/reviews', function () {
            return inertia('HRM/Performance/Reviews');
        })->name('reviews');
        Route::get('/goals', function () {
            return inertia('HRM/Performance/Goals');
        })->name('goals');
    });
    
    // Training Management Routes (placeholder for future)
    Route::prefix('hrm/training')->name('hrm.training.')->group(function () {
        Route::get('/', function () {
            return inertia('HRM/Training/Index');
        })->name('index');
        Route::get('/calendar', function () {
            return inertia('HRM/Training/Calendar');
        })->name('calendar');
        Route::get('/certificates', function () {
            return inertia('HRM/Training/Certificates');
        })->name('certificates');
    });
    
    // Department Management Routes (placeholder for future)
    Route::prefix('hrm/departments')->name('hrm.departments.')->group(function () {
        Route::get('/', function () {
            return inertia('HRM/Departments/Index');
        })->name('index');
    });
    
    // Position Management Routes (placeholder for future)
    Route::prefix('hrm/positions')->name('hrm.positions.')->group(function () {
        Route::get('/', function () {
            return inertia('HRM/Positions/Index');
        })->name('index');
    });
    
    // HRM Dashboard
    Route::get('/hrm/dashboard', function () {
        return inertia('HRM/Dashboard');
    })->name('hrm.dashboard');
    
    // HRM Reports
    Route::prefix('hrm/reports')->name('hrm.reports.')->group(function () {
        Route::get('/', function () {
            return inertia('HRM/Reports/Index');
        })->name('index');
        Route::get('/headcount', function () {
            return inertia('HRM/Reports/Headcount');
        })->name('headcount');
        Route::get('/turnover', function () {
            return inertia('HRM/Reports/Turnover');
        })->name('turnover');
        Route::get('/compensation', function () {
            return inertia('HRM/Reports/Compensation');
        })->name('compensation');
        Route::get('/compliance', function () {
            return inertia('HRM/Reports/Compliance');
        })->name('compliance');
    });
});