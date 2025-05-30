<?php

namespace App\Modules\HRM\Services;

use App\Modules\HRM\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EmployeeService
{
    public function create(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            // Generate employee code if not provided
            if (empty($data['employee_code'])) {
                $data['employee_code'] = $this->generateEmployeeCode();
            }

            // Handle profile photo upload
            if (isset($data['profile_photo'])) {
                $data['profile_photo_path'] = $data['profile_photo']->store('employees/photos', 'public');
                unset($data['profile_photo']);
            }

            $employee = Employee::create($data);

            // Create employment contract if provided
            if (isset($data['contract'])) {
                $employee->contracts()->create($data['contract']);
            }

            // Add emergency contacts if provided
            if (isset($data['emergency_contacts'])) {
                foreach ($data['emergency_contacts'] as $contact) {
                    $employee->emergencyContacts()->create($contact);
                }
            }

            return $employee;
        });
    }

    public function update(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            // Handle profile photo upload
            if (isset($data['profile_photo'])) {
                // Delete old photo if exists
                if ($employee->profile_photo_path) {
                    Storage::disk('public')->delete($employee->profile_photo_path);
                }
                $data['profile_photo_path'] = $data['profile_photo']->store('employees/photos', 'public');
                unset($data['profile_photo']);
            }

            $employee->update($data);

            // Update emergency contacts if provided
            if (isset($data['emergency_contacts'])) {
                $employee->emergencyContacts()->delete();
                foreach ($data['emergency_contacts'] as $contact) {
                    $employee->emergencyContacts()->create($contact);
                }
            }

            return $employee;
        });
    }

    public function delete(Employee $employee): bool
    {
        return DB::transaction(function () use ($employee) {
            // Delete profile photo if exists
            if ($employee->profile_photo_path) {
                Storage::disk('public')->delete($employee->profile_photo_path);
            }

            // Delete related documents
            foreach ($employee->documents as $document) {
                Storage::disk('public')->delete($document->file_path);
                $document->delete();
            }

            return $employee->delete();
        });
    }

    public function uploadDocument(Employee $employee, array $data): void
    {
        $path = $data['document']->store('employees/documents/' . $employee->id, 'public');
        
        $employee->documents()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'file_path' => $path,
            'uploaded_by' => auth()->id(),
        ]);
    }

    public function generateEmployeeCode(): string
    {
        $prefix = 'EMP';
        $lastEmployee = Employee::where('employee_code', 'like', $prefix . '%')
            ->orderBy('employee_code', 'desc')
            ->first();

        if ($lastEmployee) {
            $lastNumber = intval(substr($lastEmployee->employee_code, strlen($prefix)));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    public function getOrganizationChart(): array
    {
        $employees = Employee::with(['department', 'manager'])
            ->where('status', 'active')
            ->get();

        return $this->buildHierarchy($employees);
    }

    private function buildHierarchy($employees, $managerId = null): array
    {
        $hierarchy = [];

        foreach ($employees as $employee) {
            if ($employee->manager_id == $managerId) {
                $subordinates = $this->buildHierarchy($employees, $employee->id);
                
                $hierarchy[] = [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'position' => $employee->position,
                    'department' => $employee->department?->name,
                    'subordinates' => $subordinates,
                ];
            }
        }

        return $hierarchy;
    }

    public function importFromCsv($file): array
    {
        $results = [
            'success' => 0,
            'errors' => [],
        ];

        $csv = array_map('str_getcsv', file($file->getRealPath()));
        $headers = array_shift($csv);

        foreach ($csv as $row) {
            try {
                $data = array_combine($headers, $row);
                
                // Map CSV columns to model fields
                $employeeData = [
                    'first_name' => $data['first_name'] ?? '',
                    'last_name' => $data['last_name'] ?? '',
                    'rut' => $data['rut'] ?? '',
                    'email' => $data['email'] ?? '',
                    'phone' => $data['phone'] ?? null,
                    'position' => $data['position'] ?? '',
                    'department_id' => $data['department_id'] ?? null,
                    'hire_date' => $data['hire_date'] ? Carbon::parse($data['hire_date']) : now(),
                    'base_salary' => $data['base_salary'] ?? 0,
                ];

                $this->create($employeeData);
                $results['success']++;
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'row' => $row,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function calculateServiceYears(Employee $employee): float
    {
        return $employee->hire_date->diffInYears(now());
    }

    public function getActiveEmployees(): \Illuminate\Database\Eloquent\Collection
    {
        return Employee::where('status', 'active')
            ->with(['department', 'contracts' => function ($query) {
                $query->where('status', 'active');
            }])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function getEmployeesByDepartment(int $departmentId): \Illuminate\Database\Eloquent\Collection
    {
        return Employee::where('department_id', $departmentId)
            ->where('status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function searchEmployees(string $query): \Illuminate\Database\Eloquent\Collection
    {
        return Employee::where(function ($q) use ($query) {
            $q->where('first_name', 'like', "%{$query}%")
                ->orWhere('last_name', 'like', "%{$query}%")
                ->orWhere('rut', 'like', "%{$query}%")
                ->orWhere('employee_code', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%");
        })
        ->with('department')
        ->limit(10)
        ->get();
    }
}