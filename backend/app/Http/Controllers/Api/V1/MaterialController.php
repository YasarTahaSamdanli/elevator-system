<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Material\StoreMaterialRequest;
use App\Http\Requests\Material\UpdateMaterialRequest;
use App\Http\Resources\MaterialResource;
use App\Models\Material;
use App\Models\StockMovement;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $outboundTypes = self::sqlList(StockMovement::OUTBOUND_TYPES);
        $signedStock = "COALESCE(SUM(CASE WHEN stock_movements.type IN ($outboundTypes) THEN -stock_movements.quantity ELSE stock_movements.quantity END), 0)";

        $query = Material::query()
            ->leftJoin('stock_movements', 'materials.id', '=', 'stock_movements.material_id')
            ->select('materials.*')
            ->selectRaw("$signedStock as stock_on_hand")
            ->groupBy('materials.id');

        $materials = ListQuery::for($query, $request)
            ->filterable(['unit', 'category', 'is_active'])
            ->searchable(['code', 'name', 'category', 'unit'])
            ->sortable(['code', 'name', 'category', 'unit', 'min_stock_level', 'default_unit_price', 'created_at', 'updated_at'])
            ->dateRange('created_at')
            ->paginate();

        return ApiResponse::paginated($materials, MaterialResource::class);
    }

    public function show(Material $material): JsonResponse
    {
        $material->setAttribute('stock_on_hand', $this->stockOnHand($material));

        return ApiResponse::success(data: new MaterialResource($material));
    }

    public function store(StoreMaterialRequest $request): JsonResponse
    {
        $material = Material::create($request->validated());

        return ApiResponse::success(
            data: new MaterialResource($material),
            message: 'Material created successfully.',
            status: 201,
        );
    }

    public function update(UpdateMaterialRequest $request, Material $material): JsonResponse
    {
        $material->update($request->validated());

        return ApiResponse::success(
            data: new MaterialResource($material->fresh()),
            message: 'Material updated successfully.',
        );
    }

    public function destroy(Material $material): JsonResponse
    {
        $material->delete();

        return ApiResponse::success(message: 'Material deleted successfully.');
    }

    private function stockOnHand(Material $material): string
    {
        $outboundTypes = self::sqlList(StockMovement::OUTBOUND_TYPES);

        return (string) StockMovement::query()
            ->where('material_id', $material->id)
            ->sum(DB::raw("CASE WHEN type IN ($outboundTypes) THEN -quantity ELSE quantity END"));
    }

    /**
     * @param  list<string>  $values
     */
    private static function sqlList(array $values): string
    {
        return "'".implode("', '", $values)."'";
    }
}
