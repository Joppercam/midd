<?php

namespace App\Modules\CRM\Services;

use App\Modules\CRM\Models\Contact;
use App\Modules\CRM\Models\Company;
use App\Modules\CRM\Models\ScoringRule;
use Illuminate\Support\Collection;

class LeadScoringService
{
    /**
     * Calcular score para un contacto
     */
    public function scoreContact(Contact $contact): int
    {
        $rules = $this->getActiveRules($contact->tenant_id, 'contact');
        $totalScore = 0;

        foreach ($rules as $rule) {
            if ($this->evaluateRule($rule, $contact)) {
                $totalScore += $rule->points;
            }
        }

        // Aplicar score por comportamiento
        $totalScore += $this->calculateBehaviorScore($contact);

        // Limitar score entre 0 y 100
        $finalScore = max(0, min(100, $totalScore));
        
        // Actualizar score del contacto
        $contact->update(['score' => $finalScore]);

        return $finalScore;
    }

    /**
     * Calcular score para una empresa
     */
    public function scoreCompany(Company $company): int
    {
        $rules = $this->getActiveRules($company->tenant_id, 'company');
        $totalScore = 0;

        foreach ($rules as $rule) {
            if ($this->evaluateRule($rule, $company)) {
                $totalScore += $rule->points;
            }
        }

        // Score adicional por contactos asociados
        $avgContactScore = $company->contacts()->avg('score') ?? 0;
        $totalScore += round($avgContactScore * 0.3); // 30% del promedio de contactos

        // Limitar score entre 0 y 100
        $finalScore = max(0, min(100, $totalScore));
        
        // Actualizar score de la empresa
        $company->update(['score' => $finalScore]);

        return $finalScore;
    }

    /**
     * Obtener reglas activas
     */
    private function getActiveRules(int $tenantId, string $entityType): Collection
    {
        return ScoringRule::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Evaluar una regla contra una entidad
     */
    private function evaluateRule(ScoringRule $rule, $entity): bool
    {
        $conditions = $rule->conditions;
        
        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $entity)) {
                return false; // Todas las condiciones deben cumplirse
            }
        }

        return true;
    }

    /**
     * Evaluar una condición individual
     */
    private function evaluateCondition(array $condition, $entity): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? null;
        $value = $condition['value'] ?? null;

        if (!$field || !$operator) {
            return false;
        }

        // Obtener valor del campo (soporta notación punto para relaciones)
        $fieldValue = data_get($entity, $field);

        // Evaluar según operador
        switch ($operator) {
            case 'equals':
                return $fieldValue == $value;
            
            case 'not_equals':
                return $fieldValue != $value;
            
            case 'contains':
                return str_contains(strtolower($fieldValue), strtolower($value));
            
            case 'starts_with':
                return str_starts_with(strtolower($fieldValue), strtolower($value));
            
            case 'ends_with':
                return str_ends_with(strtolower($fieldValue), strtolower($value));
            
            case 'greater_than':
                return $fieldValue > $value;
            
            case 'less_than':
                return $fieldValue < $value;
            
            case 'greater_equal':
                return $fieldValue >= $value;
            
            case 'less_equal':
                return $fieldValue <= $value;
            
            case 'in':
                return in_array($fieldValue, (array)$value);
            
            case 'not_in':
                return !in_array($fieldValue, (array)$value);
            
            case 'is_null':
                return is_null($fieldValue);
            
            case 'is_not_null':
                return !is_null($fieldValue);
            
            case 'is_true':
                return $fieldValue == true;
            
            case 'is_false':
                return $fieldValue == false;
            
            default:
                return false;
        }
    }

    /**
     * Calcular score basado en comportamiento
     */
    private function calculateBehaviorScore(Contact $contact): int
    {
        $score = 0;

        // Score por actividad reciente
        $recentActivities = $contact->activities()
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        
        $score += min($recentActivities * 2, 10); // Max 10 puntos

        // Score por comunicaciones
        $recentComms = $contact->communications()
            ->where('occurred_at', '>=', now()->subDays(30))
            ->count();
        
        $score += min($recentComms * 3, 15); // Max 15 puntos

        // Score por oportunidades
        $openOpportunities = $contact->opportunities()
            ->where('status', 'open')
            ->count();
        
        $score += $openOpportunities * 5; // 5 puntos por oportunidad

        // Score por valor de oportunidades
        $totalValue = $contact->opportunities()
            ->where('status', 'open')
            ->sum('amount');
        
        if ($totalValue > 100000) {
            $score += 10;
        } elseif ($totalValue > 50000) {
            $score += 5;
        }

        // Score por engagement en campañas
        $campaignEngagement = $contact->campaignMemberships()
            ->whereIn('status', ['clicked', 'responded', 'converted'])
            ->count();
        
        $score += min($campaignEngagement * 4, 20); // Max 20 puntos

        return $score;
    }

    /**
     * Crear reglas de scoring predeterminadas
     */
    public function createDefaultRules(int $tenantId): void
    {
        $defaultRules = [
            // Reglas para contactos
            [
                'name' => 'Email corporativo',
                'entity_type' => 'contact',
                'conditions' => [
                    ['field' => 'email', 'operator' => 'not_contains', 'value' => 'gmail'],
                    ['field' => 'email', 'operator' => 'not_contains', 'value' => 'hotmail'],
                    ['field' => 'email', 'operator' => 'not_contains', 'value' => 'yahoo'],
                ],
                'points' => 10,
            ],
            [
                'name' => 'Cargo directivo',
                'entity_type' => 'contact',
                'conditions' => [
                    ['field' => 'position', 'operator' => 'contains', 'value' => 'director'],
                ],
                'points' => 15,
            ],
            [
                'name' => 'Lead calificado',
                'entity_type' => 'contact',
                'conditions' => [
                    ['field' => 'lead_status', 'operator' => 'in', 'value' => ['qualified', 'proposal', 'negotiation']],
                ],
                'points' => 20,
            ],
            
            // Reglas para empresas
            [
                'name' => 'Empresa grande',
                'entity_type' => 'company',
                'conditions' => [
                    ['field' => 'size', 'operator' => 'in', 'value' => ['201-500', '500+']],
                ],
                'points' => 20,
            ],
            [
                'name' => 'Industria objetivo',
                'entity_type' => 'company',
                'conditions' => [
                    ['field' => 'industry', 'operator' => 'in', 'value' => ['Tecnología', 'Finanzas', 'Salud']],
                ],
                'points' => 15,
            ],
            [
                'name' => 'Alto revenue',
                'entity_type' => 'company',
                'conditions' => [
                    ['field' => 'annual_revenue', 'operator' => 'greater_than', 'value' => 1000000],
                ],
                'points' => 25,
            ],
        ];

        foreach ($defaultRules as $ruleData) {
            ScoringRule::create([
                'tenant_id' => $tenantId,
                'name' => $ruleData['name'],
                'entity_type' => $ruleData['entity_type'],
                'conditions' => $ruleData['conditions'],
                'points' => $ruleData['points'],
                'is_active' => true,
            ]);
        }
    }

    /**
     * Recalcular scores para todos los contactos
     */
    public function recalculateAllContactScores(int $tenantId): void
    {
        Contact::where('tenant_id', $tenantId)
            ->chunk(100, function ($contacts) {
                foreach ($contacts as $contact) {
                    $this->scoreContact($contact);
                }
            });
    }

    /**
     * Recalcular scores para todas las empresas
     */
    public function recalculateAllCompanyScores(int $tenantId): void
    {
        Company::where('tenant_id', $tenantId)
            ->chunk(100, function ($companies) {
                foreach ($companies as $company) {
                    $this->scoreCompany($company);
                }
            });
    }
}