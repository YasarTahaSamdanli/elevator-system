<?php

use App\Http\Controllers\Api\V1\AccountTransactionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BuildingController;
use App\Http\Controllers\Api\V1\ElevatorController;
use App\Http\Controllers\Api\V1\ElevatorInspectionController;
use App\Http\Controllers\Api\V1\InspectionFindingController;
use App\Http\Controllers\Api\V1\InspectionImportController;
use App\Http\Controllers\Api\V1\MaterialController;
use App\Http\Controllers\Api\V1\PrintJobController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\ServiceContractController;
use App\Http\Controllers\Api\V1\StockMovementController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WarehouseController;
use App\Http\Controllers\Api\V1\WorkOrderChecklistItemController;
use App\Http\Controllers\Api\V1\WorkOrderController;
use App\Http\Controllers\Api\V1\WorkOrderItemController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::apiResource('buildings', BuildingController::class);
        Route::apiResource('elevators', ElevatorController::class);
        Route::apiResource('service-contracts', ServiceContractController::class);
        Route::post('elevator-inspections/{elevator_inspection}/work-order', [ElevatorInspectionController::class, 'createWorkOrder']);
        Route::patch('elevator-inspections/{elevator_inspection}/findings/{finding}', [InspectionFindingController::class, 'update'])
            ->scopeBindings();
        Route::apiResource('elevator-inspections', ElevatorInspectionController::class);
        Route::get('inspection-imports/{inspection_import}/pdf', [InspectionImportController::class, 'downloadPdf']);
        Route::post('inspection-imports/{inspection_import}/match', [InspectionImportController::class, 'match']);
        Route::post('inspection-imports/{inspection_import}/retry', [InspectionImportController::class, 'retry']);
        Route::post('inspection-imports/{inspection_import}/ignore', [InspectionImportController::class, 'ignore']);
        Route::apiResource('inspection-imports', InspectionImportController::class)
            ->only(['index', 'show', 'store', 'destroy']);
        Route::get('print-jobs/{print_job}/file', [PrintJobController::class, 'downloadFile']);
        Route::apiResource('print-jobs', PrintJobController::class)
            ->only(['index', 'show', 'store', 'update']);
        // Before apiResource so "summary" is not captured as a {work_order} uuid.
        Route::get('work-orders/summary', [WorkOrderController::class, 'summary']);
        Route::apiResource('work-orders', WorkOrderController::class);
        Route::patch('work-orders/{work_order}/checklist-items/{checklist_item}', [WorkOrderChecklistItemController::class, 'update'])
            ->scopeBindings();
        Route::post('work-orders/{work_order}/items', [WorkOrderItemController::class, 'store']);
        Route::patch('work-orders/{work_order}/items/{item}', [WorkOrderItemController::class, 'update']);
        Route::delete('work-orders/{work_order}/items/{item}', [WorkOrderItemController::class, 'destroy']);
        Route::apiResource('materials', MaterialController::class);
        Route::apiResource('warehouses', WarehouseController::class);
        Route::post('stock-movements/transfers', [StockMovementController::class, 'transfer']);
        Route::apiResource('stock-movements', StockMovementController::class)->only(['index', 'show', 'store']);
        Route::get('account-transactions/summary', [AccountTransactionController::class, 'summary']);
        // Ledger is append-only: no update/destroy — corrections are reverse entries.
        Route::apiResource('account-transactions', AccountTransactionController::class)->only(['index', 'show', 'store']);
        Route::apiResource('payment-methods', PaymentMethodController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('users', UserController::class);
    });
});
