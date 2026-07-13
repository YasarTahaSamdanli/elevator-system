<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\StorePaymentMethodRequest;
use App\Http\Requests\Ledger\UpdatePaymentMethodRequest;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentMethodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $paymentMethods = ListQuery::for(PaymentMethod::query(), $request)
            ->filterable(['is_active'])
            ->searchable(['name'])
            ->sortable(['name', 'created_at', 'updated_at'], 'name')
            ->dateRange('created_at')
            ->paginate();

        return ApiResponse::paginated($paymentMethods, PaymentMethodResource::class);
    }

    public function store(StorePaymentMethodRequest $request): JsonResponse
    {
        $paymentMethod = PaymentMethod::create($request->validated());

        return ApiResponse::success(
            data: new PaymentMethodResource($paymentMethod),
            message: 'Payment method created successfully.',
            status: 201,
        );
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod->update($request->validated());

        return ApiResponse::success(
            data: new PaymentMethodResource($paymentMethod->fresh()),
            message: 'Payment method updated successfully.',
        );
    }

    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        // The ledger is immutable and references payment methods; a method
        // that has been used can only be deactivated, never removed.
        if ($paymentMethod->transactions()->exists()) {
            throw ValidationException::withMessages([
                'payment_method' => ['A payment method with recorded transactions cannot be deleted; deactivate it instead.'],
            ]);
        }

        $paymentMethod->delete();

        return ApiResponse::success(
            message: 'Payment method deleted successfully.',
        );
    }
}
