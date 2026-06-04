<?php

namespace App\Services;

use App\Models\Register;
use App\Models\RegisterField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterService
{
    public function create(array $data, string $userId): Register
    {
        return DB::transaction(function () use ($data, $userId) {
            return Register::create([
                'id' => (string) Str::uuid(),
                'code' => $data['code'],
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'fiscal_year' => $data['fiscal_year'],
                'created_by' => $userId,
            ]);
        });
    }

    public function update(Register $register, array $data): Register
    {
        return DB::transaction(function () use ($register, $data) {
            $register->update([
                'code' => $data['code'],
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'fiscal_year' => $data['fiscal_year'],
            ]);
            return $register;
        });
    }

    public function addField(Register $register, array $data): RegisterField
    {
        return DB::transaction(function () use ($register, $data) {
            return RegisterField::create([
                'id' => (string) Str::uuid(),
                'register_id' => $register->id,
                'name' => $data['name'],
                'label_ar' => $data['label_ar'],
                'label_en' => $data['label_en'] ?? null,
                'field_type' => $data['field_type'],
                'is_required' => $data['is_required'] ?? false,
                'is_visible' => $data['is_visible'] ?? true,
                'is_financial' => $data['is_financial'] ?? false,
                'sort_order' => $data['sort_order'] ?? 0,
                'validation_rules' => $data['validation_rules'] ?? null,
                'default_value' => $data['default_value'] ?? null,
                'options' => $data['options'] ?? null,
            ]);
        });
    }

    public function updateField(RegisterField $field, array $data): RegisterField
    {
        return DB::transaction(function () use ($field, $data) {
            $field->update([
                'name' => $data['name'],
                'label_ar' => $data['label_ar'],
                'label_en' => $data['label_en'] ?? null,
                'field_type' => $data['field_type'],
                'is_required' => $data['is_required'] ?? false,
                'is_visible' => $data['is_visible'] ?? true,
                'is_financial' => $data['is_financial'] ?? false,
                'sort_order' => $data['sort_order'] ?? 0,
                'validation_rules' => $data['validation_rules'] ?? null,
                'default_value' => $data['default_value'] ?? null,
                'options' => $data['options'] ?? null,
            ]);
            return $field;
        });
    }

    public function reorderFields(Register $register, array $fieldsData): void
    {
        DB::transaction(function () use ($register, $fieldsData) {
            foreach ($fieldsData as $item) {
                RegisterField::where('id', $item['id'])
                    ->where('register_id', $register->id)
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });
    }
}
