<?php

namespace App\Helpers;

use App\Models\WorkflowField;

/**
 * FieldKey Helper - SINGLE SOURCE OF TRUTH for field identification.
 * 
 * This matches the frontend fieldKey() function exactly.
 * 
 * Usage:
 *   $key = FieldKey::make($field);
 *   $key = FieldKey::makeFromIds($registerFieldId, $workflowFieldId);
 * 
 * NEVER use:
 *   - $field->id directly in rules/conditions/actions
 *   - $field->register_field_id directly (use FieldKey::make instead)
 *   - Manual "custom_" + $field->id concatenation
 */
class FieldKey
{
    /**
     * Generate the canonical field key for a WorkflowField.
     * 
     * This is THE ONLY way to generate field keys for rules, conditions, actions,
     * and execution values. Using any other method will cause field resolution failures.
     * 
     * @param WorkflowField $field
     * @return string
     */
    public static function make(WorkflowField $field): string
    {
        // Register-backed fields use register_field_id
        if (!empty($field->register_field_id)) {
            return $field->register_field_id;
        }
        
        // Custom workflow fields use custom_<id>
        return 'custom_' . $field->id;
    }
    
    /**
     * Generate field key from raw IDs (when you don't have a WorkflowField object).
     * 
     * @param string|null $registerFieldId The register_field_id (if any)
     * @param string $workflowFieldId The workflow field UUID
     * @return string
     */
    public static function makeFromIds(?string $registerFieldId, string $workflowFieldId): string
    {
        if (!empty($registerFieldId)) {
            return $registerFieldId;
        }
        
        return 'custom_' . $workflowFieldId;
    }
    
    /**
     * Check if a key is a custom field key (starts with "custom_").
     * 
     * @param string $key
     * @return bool
     */
    public static function isCustom(string $key): bool
    {
        return str_starts_with($key, 'custom_');
    }
    
    /**
     * Extract the workflow field UUID from a custom field key.
     * 
     * @param string $key
     * @return string|null The UUID, or null if not a custom key
     */
    public static function extractUuid(string $key): ?string
    {
        if (!self::isCustom($key)) {
            return null;
        }
        
        return substr($key, 7); // Remove "custom_" prefix
    }
    
    /**
     * Normalize a field identifier to canonical key format.
     * 
     * Handles all possible input formats:
     * - UUID (workflow field ID) → converts to custom_<uuid>
     * - register_field_id → returns as-is
     * - custom_<uuid> → returns as-is
     * 
     * @param string $identifier The field identifier (any format)
     * @param array $fields Array of WorkflowField objects for lookup
     * @return string|null The canonical key, or null if not found
     */
    public static function normalize(string $identifier, array $fields): ?string
    {
        // Already in correct format?
        if (str_starts_with($identifier, 'custom_')) {
            return $identifier;
        }
        
        // Check if it's a register_field_id
        foreach ($fields as $field) {
            if ($field->register_field_id === $identifier) {
                return $identifier;
            }
            
            if ($field->id === $identifier) {
                return self::make($field);
            }
        }
        
        // Not found - return as-is and let caller handle
        return null;
    }
    
    /**
     * Get all possible aliases for a field (for lookup purposes).
     * 
     * Returns all keys that might be used to reference this field:
     * - Canonical key (register_field_id or custom_<id>)
     * - Workflow field UUID
     * - custom_<id> format
     * 
     * @param WorkflowField $field
     * @return array<string>
     */
    public static function aliases(WorkflowField $field): array
    {
        $aliases = [];
        
        // Canonical key
        $canonical = self::make($field);
        $aliases[$canonical] = $canonical;
        
        // UUID
        $aliases[$field->id] = $canonical;
        
        // custom_<id> format
        $aliases['custom_' . $field->id] = $canonical;
        
        // register_field_id (if different from canonical)
        if (!empty($field->register_field_id) && $field->register_field_id !== $canonical) {
            $aliases[$field->register_field_id] = $canonical;
        }
        
        return array_values($aliases);
    }
}
