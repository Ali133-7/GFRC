<?php

namespace App\Services;

use App\Models\WorkflowField;
use Illuminate\Support\Collection;

class InsuranceEngine
{
    protected CalculationContext $ctx;

    public function __construct(CalculationContext $ctx = null)
    {
        $this->ctx = $ctx ?? CalculationContext::default();
    }

    public function collectInsuranceSnapshots(Collection $fields, array $values): array
    {
        $snapshots = [];

        foreach ($fields as $field) {
            if (!$field->is_insured) {
                continue;
            }

            $fieldValue = $values[$field->register_field_id] ?? null;
            $insuranceValue = $field->insurance_value;

            if ($insuranceValue === null && !$field->is_financial) {
                continue;
            }

            $snapshots[] = $this->createSnapshot($field, $fieldValue, $insuranceValue);
        }

        return $snapshots;
    }

    public function createSnapshot(WorkflowField $field, mixed $fieldValue, mixed $insuranceValue = null): array
    {
        $normalizedValue = $this->normalizeFinancialValue($fieldValue);
        $normalizedInsurance = $insuranceValue !== null
            ? $this->normalizeFinancialValue($insuranceValue)
            : $normalizedValue;

        return [
            'field_id' => $field->register_field_id,
            'field_name' => $field->registerField?->name ?? '',
            'label' => $field->label,
            'field_value' => $normalizedValue,
            'insurance_value' => $normalizedInsurance,
            'coverage_ratio' => $this->calculateCoverageRatio($normalizedValue, $normalizedInsurance),
            'timestamp' => now()->toIso8601String(),
            'scale' => $this->ctx->scale(),
        ];
    }

    public function calculateRiskExposure(Collection $fields, array $values): string
    {
        $totalExposure = '0';

        foreach ($fields as $field) {
            if (!$field->is_insured) {
                continue;
            }

            $fieldValue = $values[$field->register_field_id] ?? '0';
            $insuranceValue = $field->insurance_value ?? '0';

            $normalizedField = $this->normalizeFinancialValue($fieldValue);
            $normalizedInsurance = $this->normalizeFinancialValue($insuranceValue);

            if (bccomp($normalizedField, $normalizedInsurance, $this->ctx->scale()) > 0) {
                $exposure = bcsub($normalizedField, $normalizedInsurance, $this->ctx->scale());
                $totalExposure = bcadd($totalExposure, $exposure, $this->ctx->scale());
            }
        }

        return $totalExposure;
    }

    public function includeInAuditSnapshot(Collection $fields, array $values): array
    {
        $auditItems = [];

        foreach ($fields as $field) {
            if (!$field->is_insured) {
                continue;
            }

            $fieldValue = $values[$field->register_field_id] ?? null;
            $auditItems[] = [
                'field_id' => $field->register_field_id,
                'field_type' => $field->field_type,
                'value' => $fieldValue,
                'insurance_value' => $field->insurance_value,
                'is_financial' => $field->is_financial,
            ];
        }

        return $auditItems;
    }

    protected function normalizeFinancialValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.000';
        }

        $str = (string) $value;
        if (!is_numeric($str)) {
            return '0.000';
        }

        $scale = $this->ctx->scale();

        if (str_contains($str, '.')) {
            $parts = explode('.', $str, 2);
            $integerPart = $parts[0];
            $decimalPart = str_pad(substr($parts[1], 0, $scale), $scale, '0');
            return $integerPart . '.' . $decimalPart;
        }

        return $str . '.' . str_repeat('0', $scale);
    }

    protected function calculateCoverageRatio(string $fieldValue, string $insuranceValue): string
    {
        if (bccomp($fieldValue, '0', $this->ctx->scale()) === 0) {
            return '1.000';
        }

        if (bccomp($insuranceValue, '0', $this->ctx->scale()) === 0) {
            return '0.000';
        }

        return bcdiv($insuranceValue, $fieldValue, $this->ctx->scale());
    }
}
