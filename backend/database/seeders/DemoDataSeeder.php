<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Company;
use App\Models\Elevator;
use App\Models\Material;
use App\Models\ServiceContract;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\WorkOrderStockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Compact showcase dataset for the real demo account only.
 *
 * Login: yasarsamdanli1@gmail.com
 */
class DemoDataSeeder extends Seeder
{
    private const TARGET_EMAIL = 'yasarsamdanli1@gmail.com';

    public function run(): void
    {
        Model::unguarded(fn () => $this->seedDemoData());
    }

    private function seedDemoData(): void
    {
        $this->call(DefaultRoleSeeder::class);

        $owner = User::withoutGlobalScopes()->where('email', self::TARGET_EMAIL)->first();

        if (! $owner) {
            $this->command?->warn('Target demo user not found: '.self::TARGET_EMAIL);

            return;
        }

        $company = Company::withoutGlobalScopes()->find($owner->company_id);

        if (! $company) {
            $this->command?->warn('Target demo company not found.');

            return;
        }

        $technician = User::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $company->id, 'email' => 'teknisyen+'.$company->id.'@asansor.test'],
            [
                'name' => 'Demo Teknisyen',
                'phone' => '+90 532 000 00 21',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
        );
        $technician->syncRoles(['Technician']);

        $this->ensureOperationalShowcase($company, $technician);
        $this->ensureInventoryShowcase($company, $owner, $technician);

        $this->command?->info('Compact demo data ready for '.$owner->email);
    }

    private function ensureOperationalShowcase(Company $company, User $technician): void
    {
        if (WorkOrder::withoutGlobalScopes()->where('company_id', $company->id)->exists()) {
            return;
        }

        $buildings = [
            ['Merkez Plaza', 'DEMO-001', 'İstanbul', 'Kadıköy', 'Ali Yılmaz'],
            ['Güneş Apartmanı', 'DEMO-002', 'İstanbul', 'Üsküdar', 'Elif Kaya'],
        ];

        $elevators = [];

        foreach ($buildings as [$name, $code, $city, $district, $manager]) {
            $building = Building::create([
                'company_id' => $company->id,
                'name' => $name,
                'code' => $code,
                'address' => 'Demo Sok. No: 1',
                'city' => $city,
                'district' => $district,
                'manager_name' => $manager,
                'is_active' => true,
            ]);

            foreach ([['A Blok', 'active'], ['B Blok', 'maintenance']] as [$elevatorName, $status]) {
                $elevators[] = Elevator::factory()->create([
                    'building_id' => $building->id,
                    'name' => $elevatorName,
                    'status' => $status,
                ]);
            }
        }

        $statuses = ['planned', 'assigned', 'in_progress', 'completed', 'draft', 'completed'];
        $types = ['maintenance', 'fault', 'inspection', 'repair', 'maintenance', 'repair'];
        $priorities = ['normal', 'high', 'critical', 'normal', 'low', 'high'];

        foreach (array_slice($elevators, 0, 3) as $i => $elevator) {
            $contract = ServiceContract::factory()->create([
                'elevator_id' => $elevator->id,
                'contract_number' => sprintf('CNT-DEMO-%03d', $i + 1),
                'start_date' => '2026-01-01',
                'end_date' => $i === 0 ? '2026-07-20' : '2026-12-31',
                'status' => 'active',
                'monthly_fee' => 2500 + $i * 300,
                'notes' => null,
            ]);

            for ($j = 0; $j < 2; $j++) {
                $index = $i * 2 + $j;
                $status = $statuses[$index];

                WorkOrder::factory()->create([
                    'service_contract_id' => $contract->id,
                    'type' => $types[$index],
                    'status' => $status,
                    'priority' => $priorities[$index],
                    'scheduled_at' => now()->addDays($index - 2),
                    'started_at' => in_array($status, ['in_progress', 'completed'], true) ? now()->subHours(5) : null,
                    'completed_at' => $status === 'completed' ? now()->subHours(1) : null,
                    'assigned_user_id' => $status === 'draft' ? null : $technician->id,
                    'description' => 'Demo iş emri - stok ve bakım takibi.',
                ]);
            }
        }
    }

    private function ensureInventoryShowcase(Company $company, User $owner, User $technician): void
    {
        $mainWarehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Merkez Depo'],
            ['type' => 'main', 'is_active' => true],
        );

        $vehicleWarehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Demo Araç Deposu'],
            ['type' => 'vehicle', 'user_id' => $technician->id, 'is_active' => true],
        );

        $materials = $this->ensureMaterials($company, $owner, $mainWarehouse);
        $this->ensureVehicleTransfer($company, $owner, $mainWarehouse, $vehicleWarehouse, $materials);
        $this->ensureWorkOrderMaterials($company, $materials);
    }

    /**
     * @return array<string, Material>
     */
    private function ensureMaterials(Company $company, User $owner, Warehouse $mainWarehouse): array
    {
        $catalog = [
            ['FREN-001', 'Fren Balatası', 'piece', 'Mekanik', 4, 850, 8],
            ['FOTO-001', 'Kapı Fotoseli', 'piece', 'Güvenlik', 5, 450, 3],
            ['YAG-001', 'Kılavuz Ray Yağı', 'liter', 'Sarf', 8, 95, 12],
            ['KART-002', 'Kumanda Kartı', 'piece', 'Elektronik', 2, 3200, 3],
            ['ROLE-024', '24V Röle', 'piece', 'Elektrik', 6, 180, 10],
            ['KAPI-SET', 'Kapı Kilit Seti', 'set', 'Güvenlik', 2, 1250, 4],
        ];

        $materials = [];

        foreach ($catalog as [$code, $name, $unit, $category, $min, $price, $quantity]) {
            $material = Material::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'name' => $name,
                    'unit' => $unit,
                    'category' => $category,
                    'min_stock_level' => $min,
                    'default_unit_price' => $price,
                    'is_active' => true,
                ],
            );

            $materials[$code] = $material;

            StockMovement::withoutGlobalScopes()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'material_id' => $material->id,
                    'warehouse_id' => $mainWarehouse->id,
                    'type' => 'purchase_in',
                    'note' => 'Demo açılış stoğu',
                ],
                [
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'occurred_at' => now()->subDays(12),
                    'created_by' => $owner->id,
                ],
            );
        }

        return $materials;
    }

    /**
     * @param  array<string, Material>  $materials
     */
    private function ensureVehicleTransfer(
        Company $company,
        User $owner,
        Warehouse $mainWarehouse,
        Warehouse $vehicleWarehouse,
        array $materials,
    ): void {
        foreach (array_slice(array_values($materials), 0, 3) as $material) {
            $exists = StockMovement::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('warehouse_id', $vehicleWarehouse->id)
                ->where('material_id', $material->id)
                ->where('type', 'transfer_in')
                ->exists();

            if ($exists) {
                continue;
            }

            $group = (string) Str::uuid();
            $note = 'Demo araç zimmet transferi';

            StockMovement::create([
                'company_id' => $company->id,
                'material_id' => $material->id,
                'warehouse_id' => $mainWarehouse->id,
                'type' => 'transfer_out',
                'quantity' => 1,
                'unit_price' => $material->default_unit_price,
                'transfer_group_uuid' => $group,
                'occurred_at' => now()->subDays(5),
                'created_by' => $owner->id,
                'note' => $note,
            ]);

            StockMovement::create([
                'company_id' => $company->id,
                'material_id' => $material->id,
                'warehouse_id' => $vehicleWarehouse->id,
                'type' => 'transfer_in',
                'quantity' => 1,
                'unit_price' => $material->default_unit_price,
                'transfer_group_uuid' => $group,
                'occurred_at' => now()->subDays(5),
                'created_by' => $owner->id,
                'note' => $note,
            ]);
        }
    }

    /**
     * @param  array<string, Material>  $materials
     */
    private function ensureWorkOrderMaterials(Company $company, array $materials): void
    {
        $materialList = array_values($materials);

        $completedOrders = WorkOrder::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('status', 'completed')
            ->limit(2)
            ->get();

        foreach ($completedOrders as $index => $workOrder) {
            $material = $materialList[$index % count($materialList)];

            if ($workOrder->items()->where('material_id', $material->id)->doesntExist()) {
                $workOrder->items()->create([
                    'material_id' => $material->id,
                    'quantity' => $index + 1,
                    'unit_price' => $material->default_unit_price,
                    'note' => 'Demo kullanılan parça',
                ]);
            }

            app(WorkOrderStockService::class)->issueMaterialsForCompletion($workOrder);
        }

        $openOrders = WorkOrder::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('status', ['planned', 'assigned', 'in_progress'])
            ->limit(3)
            ->get();

        foreach ($openOrders as $index => $workOrder) {
            $material = $materialList[($index + 2) % count($materialList)];

            if ($workOrder->items()->where('material_id', $material->id)->doesntExist()) {
                $workOrder->items()->create([
                    'material_id' => $material->id,
                    'quantity' => 1,
                    'unit_price' => $material->default_unit_price,
                    'note' => 'Planlanan demo parça',
                ]);
            }
        }
    }
}
