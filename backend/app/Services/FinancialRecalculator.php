<?php

namespace App\Services;

/**
 * Financial Recalculator for Real-Time Rule Execution
 * 
 * Recalculates all financial values when fields change.
 * Uses BC Math for financial integrity.
 */
class FinancialRecalculator
{
    /**
     * Recalculate all financial values using BC Math
     */
    public function recalculate(array $values, array $validationResults, array $workflowResults): array
    {
        \Log::info('[FinancialRecalculator] Starting recalculation', [
            'validationResultsCount' => count($validationResults),
            'workflowResultsCount' => count($workflowResults),
            'validationResults' => $validationResults,
            'workflowResults' => $workflowResults
        ]);
        
        $financialValues = [];
        $fieldEffects = [];
        
        // Extract field effects from VALIDATION results
        foreach ($validationResults as $idx => $result) {
            if (isset($result['field_effects']) && is_array($result['field_effects'])) {
                foreach ($result['field_effects'] as $effect) {
                    $fieldEffects[] = $effect;
                    \Log::info('[FinancialRecalculator] Validation field effect [' . $idx . ']:', $effect);
                }
            } else {
                \Log::info('[FinancialRecalculator] Validation result ' . $idx . ' has no field_effects');
            }
        }
        
        // Extract field effects from WORKFLOW results
        foreach ($workflowResults as $idx => $result) {
            if (isset($result['field_effects']) && is_array($result['field_effects'])) {
                foreach ($result['field_effects'] as $effect) {
                    $fieldEffects[] = $effect;
                    \Log::info('[FinancialRecalculator] Workflow field effect [' . $idx . ']:', $effect);
                }
            } else {
                \Log::info('[FinancialRecalculator] Workflow result ' . $idx . ' has no field_effects');
            }
        }
        
        \Log::info('[FinancialRecalculator] Total field effects:', [
            'count' => count($fieldEffects),
            'effects' => $fieldEffects
        ]);
        
        // Apply financial field effects
        foreach ($fieldEffects as $idx => $effect) {
            $action = $effect['action'] ?? null;
            $fieldId = $effect['field_id'] ?? null;
            // Use 'amount' for set_fee/apply_discount, 'resolved_amount' for calculate
            $amount = $effect['amount'] ?? $effect['resolved_amount'] ?? null;
            
            \Log::info('[FinancialRecalculator] Processing effect [' . $idx . ']:', [
                'action' => $action,
                'fieldId' => $fieldId,
                'amount' => $amount,
                'effect_keys' => array_keys($effect),
                'full_effect' => $effect
            ]);
            
            if ($fieldId && $amount) {
                switch ($action) {
                    case 'set_fee':
                    case 'calculate':
                    case 'set_value':
                        // Ensure BC Math format
                        $financialValues[$fieldId] = $this->toDecimalString($amount);
                        \Log::info('[FinancialRecalculator] Added to financial_values:', [
                            'fieldId' => $fieldId,
                            'amount' => $financialValues[$fieldId]
                        ]);
                        break;
                    
                    case 'apply_discount':
                        $financialValues[$fieldId] = $this->toDecimalString($amount);
                        \Log::info('[FinancialRecalculator] Added discount:', [
                            'fieldId' => $fieldId,
                            'amount' => $financialValues[$fieldId]
                        ]);
                        break;
                    
                    default:
                        \Log::warning('[FinancialRecalculator] Unknown action type:', ['action' => $action]);
                }
            } else {
                \Log::warning('[FinancialRecalculator] Skipping effect - missing fieldId or amount:', [
                    'action' => $action,
                    'fieldId' => $fieldId,
                    'amount' => $amount,
                    'effect' => $effect
                ]);
            }
        }
        
        \Log::info('[FinancialRecalculator] Financial values after processing:', $financialValues);
        
        // Recalculate totals using BC Math
        $subtotal = '0.000';
        $discounts = '0.000';
        $fees = '0.000';
        $taxes = '0.000';
        $insurance = '0.000';
        
        foreach ($financialValues as $fieldId => $amount) {
            // Classify amount type based on field ID patterns
            if (stripos($fieldId, 'discount') !== false) {
                $discounts = bcadd($discounts, (string) $amount, 3);
            } elseif (stripos($fieldId, 'fee') !== false || stripos($fieldId, 'tax') !== false) {
                $fees = bcadd($fees, (string) $amount, 3);
            } elseif (stripos($fieldId, 'insurance') !== false) {
                $insurance = bcadd($insurance, (string) $amount, 3);
            } else {
                $subtotal = bcadd($subtotal, (string) $amount, 3);
            }
        }
        
        // Calculate total: (subtotal - discounts) + fees + taxes + insurance
        $total = bcadd(
            bcsub($subtotal, $discounts, 3),
            bcadd($fees, bcadd($taxes, $insurance, 3), 3),
            3
        );
        
        \Log::info('[FinancialRecalculator] Final results:', [
            'financial_values' => $financialValues,
            'subtotal' => $subtotal,
            'discounts' => $discounts,
            'fees' => $fees,
            'taxes' => $taxes,
            'insurance' => $insurance,
            'total' => $total
        ]);
        
        return [
            'financial_values' => $financialValues,
            'subtotal' => $subtotal,
            'discounts' => $discounts,
            'fees' => $fees,
            'taxes' => $taxes,
            'insurance' => $insurance,
            'total' => $total,
        ];
    }
    
    /**
     * Convert value to BC Math decimal string format
     */
    protected function toDecimalString(mixed $value): string
    {
        if (is_string($value)) {
            return bcadd($value, '0', 3);
        }
        if (is_numeric($value)) {
            return bcadd((string) $value, '0', 3);
        }
        return '0.000';
    }
}
