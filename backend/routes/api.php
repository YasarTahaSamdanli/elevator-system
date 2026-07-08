<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BuildingController;
use App\Http\Controllers\Api\V1\ElevatorController;
use App\Http\Controllers\Api\V1\MaterialController;
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
        Route::apiResource('users', UserController::class);
    });
});
