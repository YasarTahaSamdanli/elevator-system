<?php

namespace Database\Seeders;

use App\Models\ChecklistTemplate;
use App\Models\Company;
use Illuminate\Database\Seeder;

class DefaultChecklistTemplateSeeder extends Seeder
{
    /**
     * Standard periodic maintenance checklist, seeded per company for the
     * "maintenance" work order type. Idempotent: existing templates are
     * left untouched.
     *
     * @var list<string>
     */
    private const MAINTENANCE_ITEMS = [
        'Kabin içi aydınlatma ve butonların kontrolü',
        'Alarm ve interkom (acil haberleşme) testi',
        'Kapı fotoseli / ışık perdesi kontrolü',
        'Kat ve kabin kapısı kilit mekanizması kontrolü',
        'Fren sistemi testi',
        'Halat ve askı elemanlarının aşınma kontrolü',
        'Paraşüt sistemi ve hız regülatörü kontrolü',
        'Kat seviyeleme hassasiyeti kontrolü',
        'Makine dairesi temizlik ve düzen kontrolü',
        'Yağ seviyesi ve kaçak kontrolü',
        'Acil durum aküsü / kurtarma sistemi testi',
        'Kuyu dibi temizlik ve kontrolü',
    ];

    public function run(): void
    {
        // Seeders run unauthenticated, so the company scope is inactive and
        // company_id must be set explicitly for every company.
        Company::query()->each(function (Company $company): void {
            $exists = ChecklistTemplate::query()
                ->where('company_id', $company->id)
                ->where('work_order_type', 'maintenance')
                ->exists();

            if ($exists) {
                return;
            }

            $template = new ChecklistTemplate([
                'name' => 'Periyodik Bakım Kontrol Listesi',
                'work_order_type' => 'maintenance',
                'is_active' => true,
            ]);
            $template->company_id = $company->id;
            $template->save();

            foreach (self::MAINTENANCE_ITEMS as $index => $label) {
                $template->items()->create([
                    'position' => $index + 1,
                    'label' => $label,
                ]);
            }
        });
    }
}
