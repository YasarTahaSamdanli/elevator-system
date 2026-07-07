<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BuildingController;
use App\Http\Controllers\Api\V1\ElevatorController;
use App\Http\Controllers\Api\V1\ServiceContractController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WorkOrderChecklistItemController;
use App\Http\Controllers\Api\V1\WorkOrderController;
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
        Route::apiResource('users', UserController::class);
    });
});
