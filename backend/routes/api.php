<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BackupController;
use App\Http\Controllers\Api\V1\ElementController;
use App\Http\Controllers\Api\V1\FeeVersionController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HelpCenterController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\StyleController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WorkflowController;
use App\Http\Controllers\Api\V1\WorkflowExecutionController;
use App\Http\Controllers\Api\V1\WorkflowVersionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::get('users/{id}', [UserController::class, 'show']);
        Route::put('users/{id}', [UserController::class, 'update']);
        Route::delete('users/{id}', [UserController::class, 'destroy']);
        Route::put('users/{id}/roles', [UserController::class, 'updateRoles']);
        Route::put('users/{id}/permissions', [UserController::class, 'updatePermissions']);
        Route::get('users/{id}/activity-summary', [UserController::class, 'activitySummary']);

        Route::get('roles', [RoleController::class, 'index']);
        Route::post('roles', [RoleController::class, 'store']);
        Route::put('roles/{id}/permissions', [RoleController::class, 'updatePermissions']);

        Route::get('registers', [RegisterController::class, 'index']);
        Route::post('registers', [RegisterController::class, 'store']);
        Route::get('registers/{id}', [RegisterController::class, 'show']);
        Route::put('registers/{id}', [RegisterController::class, 'update']);
        Route::get('registers/{id}/fields', [RegisterController::class, 'fields']);
        Route::post('registers/{id}/fields', [RegisterController::class, 'storeField']);
        Route::put('registers/{id}/fields/{fieldId}', [RegisterController::class, 'updateField']);
        Route::delete('registers/{id}/fields/{fieldId}', [RegisterController::class, 'destroyField']);
        Route::patch('registers/{id}/fields/reorder', [RegisterController::class, 'reorderFields']);
        Route::get('registers/{id}/templates', [TemplateController::class, 'getRegisterTemplates']);

        // Template routes
        Route::get('templates', [TemplateController::class, 'index']);
        Route::post('templates', [TemplateController::class, 'store']);
        Route::get('templates/{id}', [TemplateController::class, 'show']);
        Route::put('templates/{id}', [TemplateController::class, 'update']);
        Route::delete('templates/{id}', [TemplateController::class, 'destroy']);
        Route::post('templates/{id}/clone', [TemplateController::class, 'clone']);
        Route::post('templates/{id}/make-default', [TemplateController::class, 'makeDefault']);
        Route::get('templates/{id}/preview', [TemplateController::class, 'preview']);
        Route::get('registers/{registerId}/template', [TemplateController::class, 'getRegisterTemplate']);
        Route::delete('templates/{id}/elements', [TemplateController::class, 'clearElements']);

        // Element routes
        Route::post('templates/{templateId}/elements', [ElementController::class, 'store']);
        Route::put('templates/{templateId}/elements/{elementId}', [ElementController::class, 'update']);
        Route::delete('templates/{templateId}/elements/{elementId}', [ElementController::class, 'destroy']);
        Route::patch('templates/{templateId}/elements/reorder', [ElementController::class, 'reorder']);
        Route::patch('templates/{templateId}/elements/bulk-update', [ElementController::class, 'bulkUpdate']);

        // Style routes
        Route::get('styles/defaults', [StyleController::class, 'defaults']);
        Route::get('elements/{elementId}/styles', [StyleController::class, 'show']);
        Route::put('elements/{elementId}/styles', [StyleController::class, 'update']);
        Route::post('elements/{elementId}/styles/preset', [StyleController::class, 'applyPreset']);

        Route::get('receipts', [ReceiptController::class, 'index']);
        Route::post('receipts', [ReceiptController::class, 'store']);
        Route::get('receipts/{id}', [ReceiptController::class, 'show']);
        Route::put('receipts/{id}', [ReceiptController::class, 'update']);
        Route::post('receipts/{id}/issue', [ReceiptController::class, 'issue']);
        Route::post('receipts/{id}/cancel', [ReceiptController::class, 'cancel']);
        Route::post('receipts/{id}/revise', [ReceiptController::class, 'revise']);
        Route::get('receipts/{id}/print', [ReceiptController::class, 'print']);
        Route::get('receipts/{id}/qr', [ReceiptController::class, 'qr']);
        Route::get('receipts/{id}/revisions', [ReceiptController::class, 'revisions']);

        Route::get('reports/daily', [ReportController::class, 'daily']);
        Route::get('reports/monthly', [ReportController::class, 'monthly']);
        Route::get('reports/user-activity', [ReportController::class, 'userActivity']);
        Route::get('reports/register-summary', [ReportController::class, 'registerSummary']);
        Route::post('reports/custom', [ReportController::class, 'custom']);
        Route::get('reports/export-csv', [ReportController::class, 'exportCsv']);

        Route::get('settings', [SettingController::class, 'index']);
        Route::get('settings/public', [SettingController::class, 'publicSettings']);
        Route::post('settings', [SettingController::class, 'store']);
        Route::put('settings/{id}', [SettingController::class, 'update']);
        Route::post('settings/bulk', [SettingController::class, 'bulkUpdate']);
        Route::delete('settings/{id}', [SettingController::class, 'destroy']);

        Route::get('backups', [BackupController::class, 'index']);
        Route::post('backups', [BackupController::class, 'create']);
        Route::post('backups/{filename}/restore', [BackupController::class, 'restore']);
        Route::get('backups/{filename}', [BackupController::class, 'download']);
        Route::delete('backups/{filename}', [BackupController::class, 'destroy']);

        Route::get('audit-logs', [AuditLogController::class, 'index']);

        // Transaction Templates (Guided Receipt Builder)
        Route::get('transaction-templates', [\App\Http\Controllers\Api\V1\TransactionTemplateController::class, 'index']);
        Route::post('transaction-templates', [\App\Http\Controllers\Api\V1\TransactionTemplateController::class, 'store']);
        Route::get('transaction-templates/{id}', [\App\Http\Controllers\Api\V1\TransactionTemplateController::class, 'show']);
        Route::put('transaction-templates/{id}', [\App\Http\Controllers\Api\V1\TransactionTemplateController::class, 'update']);
        Route::delete('transaction-templates/{id}', [\App\Http\Controllers\Api\V1\TransactionTemplateController::class, 'destroy']);
        Route::post('transaction-templates/{id}/clone', [\App\Http\Controllers\Api\V1\TransactionTemplateController::class, 'clone']);
        Route::patch('transaction-templates/{id}/toggle', [\App\Http\Controllers\Api\V1\TransactionTemplateController::class, 'toggle']);
        Route::get('registers/{registerId}/transaction-templates', [\App\Http\Controllers\Api\V1\TransactionTemplateController::class, 'byRegister']);
        Route::get('transaction-templates/{id}/preview', [\App\Http\Controllers\Api\V1\TransactionTemplateController::class, 'preview']);

        // Guided Receipt
        Route::post('guided-receipts/build', [\App\Http\Controllers\Api\V1\GuidedReceiptController::class, 'build']);
        Route::post('guided-receipts', [\App\Http\Controllers\Api\V1\GuidedReceiptController::class, 'store']);

        // Official Fees Library
        Route::get('official-fees/categories', [\App\Http\Controllers\Api\V1\OfficialFeeController::class, 'categories']);
        Route::post('official-fees/categories', [\App\Http\Controllers\Api\V1\OfficialFeeController::class, 'storeCategory']);
        Route::put('official-fees/categories/{id}', [\App\Http\Controllers\Api\V1\OfficialFeeController::class, 'updateCategory']);
        Route::delete('official-fees/categories/{id}', [\App\Http\Controllers\Api\V1\OfficialFeeController::class, 'destroyCategory']);
        Route::get('official-fees', [\App\Http\Controllers\Api\V1\OfficialFeeController::class, 'index']);
        Route::post('official-fees', [\App\Http\Controllers\Api\V1\OfficialFeeController::class, 'store']);
        Route::get('official-fees/{id}', [\App\Http\Controllers\Api\V1\OfficialFeeController::class, 'show']);
        Route::put('official-fees/{id}', [\App\Http\Controllers\Api\V1\OfficialFeeController::class, 'update']);
        Route::delete('official-fees/{id}', [\App\Http\Controllers\Api\V1\OfficialFeeController::class, 'destroy']);

        // Workflow Engine
        Route::get('workflows', [WorkflowController::class, 'index']);
        Route::post('workflows', [WorkflowController::class, 'store']);
        Route::get('workflows/{id}', [WorkflowController::class, 'show']);
        Route::put('workflows/{id}', [WorkflowController::class, 'update']);
        Route::delete('workflows/{id}', [WorkflowController::class, 'destroy']);

        // Workflow Versions
        Route::get('workflows/{workflowId}/versions', [WorkflowVersionController::class, 'index']);
        Route::post('workflows/{workflowId}/versions', [WorkflowVersionController::class, 'store']);
        Route::get('workflows/{workflowId}/versions/{versionId}', [WorkflowVersionController::class, 'show']);
        Route::put('workflows/{workflowId}/versions/{versionId}', [WorkflowVersionController::class, 'update']);
        Route::post('workflows/{workflowId}/versions/{versionId}/publish', [WorkflowVersionController::class, 'publish']);
        Route::post('workflows/{workflowId}/versions/{versionId}/archive', [WorkflowVersionController::class, 'archive']);
        Route::post('workflows/{workflowId}/versions/{versionId}/clone', [WorkflowVersionController::class, 'cloneVersion']);

        // Workflow Version Steps
        Route::post('workflows/{workflowId}/versions/{versionId}/steps', [WorkflowVersionController::class, 'storeStep']);
        Route::put('workflows/{workflowId}/versions/{versionId}/steps/{stepId}', [WorkflowVersionController::class, 'updateStep']);
        Route::delete('workflows/{workflowId}/versions/{versionId}/steps/{stepId}', [WorkflowVersionController::class, 'destroyStep']);
        Route::patch('workflows/{workflowId}/versions/{versionId}/steps/reorder', [WorkflowVersionController::class, 'reorderSteps']);

        // Workflow Version Fields
        Route::post('workflows/{workflowId}/versions/{versionId}/fields', [WorkflowVersionController::class, 'storeField']);
        Route::put('workflows/{workflowId}/versions/{versionId}/fields/{fieldId}', [WorkflowVersionController::class, 'updateField']);
        Route::delete('workflows/{workflowId}/versions/{versionId}/fields/{fieldId}', [WorkflowVersionController::class, 'destroyField']);
        Route::patch('workflows/{workflowId}/versions/{versionId}/fields/reorder', [WorkflowVersionController::class, 'reorderFields']);

        // Workflow Version Rules
        Route::post('workflows/{workflowId}/versions/{versionId}/rules', [WorkflowVersionController::class, 'storeRule']);
        Route::put('workflows/{workflowId}/versions/{versionId}/rules/{ruleId}', [WorkflowVersionController::class, 'updateRule']);
        Route::delete('workflows/{workflowId}/versions/{versionId}/rules/{ruleId}', [WorkflowVersionController::class, 'destroyRule']);
        Route::post('workflows/{workflowId}/versions/{versionId}/rules/{ruleId}/simulate', [WorkflowVersionController::class, 'simulateRule']);

        // Workflow Version Validation Rules
        Route::get('workflows/{workflowId}/versions/{versionId}/validations', [WorkflowVersionController::class, 'getValidationRules']);
        Route::post('workflows/{workflowId}/versions/{versionId}/validations', [WorkflowVersionController::class, 'storeValidationRule']);
        Route::put('workflows/{workflowId}/versions/{versionId}/validations/{ruleId}', [WorkflowVersionController::class, 'updateValidationRule']);
        Route::delete('workflows/{workflowId}/versions/{versionId}/validations/{ruleId}', [WorkflowVersionController::class, 'destroyValidationRule']);
        Route::patch('workflows/{workflowId}/versions/{versionId}/validations/reorder', [WorkflowVersionController::class, 'reorderValidationRules']);
        Route::post('workflows/{workflowId}/versions/{versionId}/validations/simulate', [WorkflowVersionController::class, 'simulateValidation']);
        Route::post('workflows/{workflowId}/versions/{versionId}/validate-field', [WorkflowVersionController::class, 'validateField']);
        Route::post('workflows/{workflowId}/versions/{versionId}/enterprise/simulate', [WorkflowVersionController::class, 'simulateEnterprise']);

        // Workflow Executions
        Route::get('workflow-executions', [WorkflowExecutionController::class, 'index']);
        Route::post('workflow-executions', [WorkflowExecutionController::class, 'store']);
        Route::get('workflow-executions/{id}', [WorkflowExecutionController::class, 'show']);
        Route::put('workflow-executions/{id}/step', [WorkflowExecutionController::class, 'submitStep']);
        Route::post('workflow-executions/{id}/complete', [WorkflowExecutionController::class, 'complete']);
        Route::post('workflow-executions/{id}/cancel', [WorkflowExecutionController::class, 'cancel']);
        Route::post('workflow-executions/preview', [WorkflowExecutionController::class, 'preview']);

        // Workflow Execution Branch Control
        Route::get('workflow-executions/{id}/branch-state', [WorkflowExecutionController::class, 'getBranchState']);
        Route::post('workflow-executions/{id}/switch-mode', [WorkflowExecutionController::class, 'switchMode']);
        Route::post('workflow-executions/{id}/pause', [WorkflowExecutionController::class, 'pauseExecution']);
        Route::post('workflow-executions/{id}/resume', [WorkflowExecutionController::class, 'resumeExecution']);
        Route::post('workflow-executions/{id}/redirect', [WorkflowExecutionController::class, 'redirectExecution']);
        Route::post('workflow-executions/{id}/save-draft', [WorkflowExecutionController::class, 'saveDraft']);
        Route::get('workflow-versions/{versionId}/field-schema', [WorkflowExecutionController::class, 'getFieldSchema']);

        // Fee Versions
        Route::get('official-fees/{id}/versions', [FeeVersionController::class, 'index']);
        Route::post('official-fees/{id}/versions', [FeeVersionController::class, 'store']);
        Route::get('fees/active', [FeeVersionController::class, 'listActive']);
        Route::get('fees/resolve/{code}', [FeeVersionController::class, 'resolve']);
        Route::post('fees/bulk-resolve', [FeeVersionController::class, 'bulkResolve']);

        Route::post('system/reset', [\App\Http\Controllers\Api\V1\SystemController::class, 'reset']);
        Route::get('system/export', [\App\Http\Controllers\Api\V1\SystemController::class, 'export']);
        Route::post('system/import', [\App\Http\Controllers\Api\V1\SystemController::class, 'import']);
        Route::post('system/logo', [\App\Http\Controllers\Api\V1\SystemController::class, 'uploadLogo']);

        // Help Center
        Route::get('help', [HelpCenterController::class, 'index']);
        Route::get('help/{pageKey}', [HelpCenterController::class, 'getByPageKey']);
        Route::post('help', [HelpCenterController::class, 'store']);
        Route::put('help/{id}', [HelpCenterController::class, 'update']);
        Route::delete('help/{id}', [HelpCenterController::class, 'destroy']);
        Route::patch('help/reorder', [HelpCenterController::class, 'reorder']);
        Route::post('help/seed', [HelpCenterController::class, 'seedSystemArticles']);
    });

    Route::get('health', [HealthController::class, 'index']);
});
