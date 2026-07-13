<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\AccountSummaryRequest;
use App\Http\Requests\Ledger\StoreAccountTransactionRequest;
use App\Http\Resources\AccountTransactionResource;
use App\Models\AccountTransaction;
use App\Models\Building;
use App\Models\Elevator;
use App\Models\PaymentMethod;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AccountTransactionController extends Controller
{
    private const RELATIONS = ['building', 'elevator', 'workOrder', 'paymentMethod', 'collectedBy'];

    public function index(Request $request): JsonResponse
    {
        $transactions = ListQuery::for(AccountTransaction::query()->with(self::RELATIONS), $request)
            ->filterable([
                'type',
                'building_uuid' => fn (Builder $query, mixed $value) => $query->whereHas(
                    'building',
                    fn (Builder $building) => $building->where('uuid', $value),
                ),
                'elevator_uuid' => fn (Builder $query, mixed $value) => $query->whereHas(
                    'elevator',
                    fn (Builder $elevator) => $elevator->where('uuid', $value),
                ),
            ])
            ->searchable([
                'description',
                'payer_name',
                'building' => ['name', 'code', 'address', 'city', 'district', 'manager_name', 'manager_phone'],
                'elevator' => ['serial_number', 'qr_identifier', 'name', 'manufacturer', 'model', 'registration_number'],
                'serviceContract' => ['contract_number', 'notes'],
                'workOrder' => ['work_order_number', 'description', 'notes'],
                'paymentMethod' => ['name'],
                'collectedBy' => ['name', 'email', 'phone'],
            ])
            ->sortable(['occurred_at', 'amount', 'type', 'created_at'], '-occurred_at')
            ->dateRange('occurred_at', 'created_at')
            ->paginate();

        return ApiResponse::paginated($transactions, AccountTransactionResource::class);
    }

    public function show(AccountTransaction $accountTransaction): JsonResponse
    {
        return ApiResponse::success(
            data: new AccountTransactionResource($accountTransaction->load(self::RELATIONS)),
        );
    }

    /**
     * Manual ledger entry: payments (tahsilat), opening balances and
     * adjustments. Charges from completed work orders and monthly accruals
     * are posted automatically — the ledger stays append-only either way.
     */
    public function store(StoreAccountTransactionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $building = Building::where('uuid', $data['building_uuid'])->firstOrFail();
        unset($data['building_uuid']);
        $data['building_id'] = $building->id;

        if (! empty($data['elevator_uuid'])) {
            $elevator = Elevator::where('uuid', $data['elevator_uuid'])->firstOrFail();

            if ($elevator->building_id !== $building->id) {
                throw ValidationException::withMessages([
                    'elevator_uuid' => ['The elevator does not belong to the given building.'],
                ]);
            }

            $data['elevator_id'] = $elevator->id;
        }
        unset($data['elevator_uuid']);

        if (! empty($data['work_order_uuid'])) {
            $data['work_order_id'] = WorkOrder::where('uuid', $data['work_order_uuid'])->firstOrFail()->id;
        }
        unset($data['work_order_uuid']);

        if (! empty($data['payment_method_uuid'])) {
            $data['payment_method_id'] = PaymentMethod::where('uuid', $data['payment_method_uuid'])->firstOrFail()->id;
        }
        unset($data['payment_method_uuid']);

        if ($data['type'] === 'payment') {
            $data['collected_by'] = Auth::id();
        }

        $data['created_by'] = Auth::id();

        $transaction = AccountTransaction::create($data);

        return ApiResponse::success(
            data: new AccountTransactionResource($transaction->load(self::RELATIONS)),
            message: 'Account transaction created successfully.',
            status: 201,
        );
    }

    /**
     * Per-type totals + running balance, optionally narrowed to one
     * building/elevator and a date window — the "Hesap Dökümü" box of the
     * customer's old desktop app.
     */
    public function summary(AccountSummaryRequest $request): JsonResponse
    {
        $params = $request->validated();

        $query = AccountTransaction::query();

        if (isset($params['building_uuid'])) {
            $query->whereHas('building', fn (Builder $building) => $building->where('uuid', $params['building_uuid']));
        }

        if (isset($params['elevator_uuid'])) {
            $query->whereHas('elevator', fn (Builder $elevator) => $elevator->where('uuid', $params['elevator_uuid']));
        }

        if (isset($params['occurred_at_from'])) {
            $query->whereDate('occurred_at', '>=', $params['occurred_at_from']);
        }

        if (isset($params['occurred_at_to'])) {
            $query->whereDate('occurred_at', '<=', $params['occurred_at_to']);
        }

        /** @var array<string, string> $rawTotals */
        $rawTotals = $query
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->all();

        $totals = [];
        $charges = 0.0;
        $credits = 0.0;

        foreach ([...AccountTransaction::CHARGE_TYPES, ...AccountTransaction::CREDIT_TYPES] as $type) {
            $totals[$type] = round((float) ($rawTotals[$type] ?? 0), 2);
        }

        foreach (AccountTransaction::CHARGE_TYPES as $type) {
            $charges += $totals[$type];
        }

        foreach (AccountTransaction::CREDIT_TYPES as $type) {
            $credits += $totals[$type];
        }

        return ApiResponse::success(data: [
            'totals' => $totals,
            'charges_total' => round($charges, 2),
            'credits_total' => round($credits, 2),
            'balance' => round($charges - $credits, 2),
        ]);
    }
}
