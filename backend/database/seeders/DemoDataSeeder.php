<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Company;
use App\Models\Elevator;
use App\Models\ServiceContract;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local development / demo dataset. Idempotent: skips entirely when the
 * demo owner already exists. Run with:
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Login: demo@asansor.test / password
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (User::withoutGlobalScopes()->where('email', 'demo@asansor.test')->exists()) {
            return;
        }

        $this->call(DefaultRoleSeeder::class);

        $company = Company::create([
            'name' => 'Demo Asansör Bakım Ltd.',
            'tax_number' => '1234567890',
            'phone' => '+90 212 000 00 00',
            'email' => 'info@asansor.test',
            'city' => 'İstanbul',
            'district' => 'Şişli',
            'is_active' => true,
        ]);

        $owner = $this->user($company, 'Yaşar Taha Şamdanlı', 'demo@asansor.test', 'Company Owner');
        $mehmet = $this->user($company, 'Mehmet Kaya', 'mehmet@asansor.test', 'Technician');
        $ayse = $this->user($company, 'Ayşe Demir', 'ayse@asansor.test', 'Technician');
        $emre = $this->user($company, 'Emre Yıldız', 'emre@asansor.test', 'Technician');
        $this->user($company, 'Zeynep Aksoy', 'zeynep@asansor.test', 'Office Staff');

        $buildings = [
            ['Nurol Tower', 'IST-001', 'İstanbul', 'Şişli', 'Hakan Öztürk'],
            ['Palladium Residence', 'IST-002', 'İstanbul', 'Ataşehir', 'Selin Arı'],
            ['Kızılay İş Merkezi', 'ANK-014', 'Ankara', 'Çankaya', 'Murat Çelik'],
        ];

        $elevators = [];

        foreach ($buildings as [$name, $code, $city, $district, $manager]) {
            $building = Building::create([
                'company_id' => $company->id,
                'name' => $name,
                'code' => $code,
                'address' => 'Demo Cad. No: 1',
                'city' => $city,
                'district' => $district,
                'manager_name' => $manager,
                'is_active' => true,
            ]);

            foreach ([['A1 - Kuzey', 'active'], ['A2 - Güney', 'maintenance']] as [$elevatorName, $status]) {
                $elevators[] = Elevator::factory()->create([
                    'building_id' => $building->id,
                    'name' => $elevatorName,
                    'status' => $status,
                ]);
            }
        }

        $technicians = [$mehmet, $ayse, $emre];
        $types = ['maintenance', 'fault', 'inspection', 'repair', 'modernization'];
        $statuses = ['planned', 'assigned', 'in_progress', 'completed', 'draft'];
        $priorities = ['normal', 'high', 'critical', 'low'];

        foreach ($elevators as $i => $elevator) {
            // Factories derive the denormalized company_id from the parent
            // chain (afterMaking hook); plain create() would leave it null.
            $contract = ServiceContract::factory()->create([
                'elevator_id' => $elevator->id,
                'contract_number' => sprintf('CNT-2026-%03d', $i + 1),
                'start_date' => '2026-01-01',
                'end_date' => $i === 0 ? '2026-07-20' : '2026-12-31', // one expiring soon
                'status' => 'active',
                'monthly_fee' => 2500 + $i * 250,
                'notes' => null,
            ]);

            for ($j = 0; $j < 2; $j++) {
                $status = $statuses[($i + $j) % count($statuses)];

                WorkOrder::factory()->create([
                    'service_contract_id' => $contract->id,
                    'type' => $types[($i + $j) % count($types)],
                    'status' => $status,
                    'priority' => $priorities[($i + $j) % count($priorities)],
                    'scheduled_at' => now()->addDays($i + $j - 3),
                    'started_at' => in_array($status, ['in_progress', 'completed'], true) ? now()->subHours(5) : null,
                    'completed_at' => $status === 'completed' ? now()->subHours(1) : null,
                    'assigned_user_id' => $status === 'draft' ? null : $technicians[($i + $j) % 3]->id,
                    'description' => 'Demo iş emri — periyodik kontrol ve saha müdahalesi.',
                ]);
            }
        }

        $this->command?->info('Demo data ready. Login: '.$owner->email.' / password');
    }

    private function user(Company $company, string $name, string $email, string $role): User
    {
        $user = User::create([
            'company_id' => $company->id,
            'name' => $name,
            'email' => $email,
            'phone' => '+90 532 000 00 '.str_pad((string) random_int(10, 99), 2, '0'),
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $user->syncRoles([$role]);

        return $user;
    }
}
