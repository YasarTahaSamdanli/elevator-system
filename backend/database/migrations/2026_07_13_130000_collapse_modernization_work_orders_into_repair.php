<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Collapse the old "modernization" bucket into the office's four work
     * lanes: maintenance, fault, inspection, repair (revision).
     */
    public function up(): void
    {
        DB::table('work_orders')
            ->where('type', 'modernization')
            ->update(['type' => 'repair']);

        DB::table('checklist_templates')
            ->where('work_order_type', 'modernization')
            ->update(['work_order_type' => 'repair']);

        $this->restrictEnums(['maintenance', 'fault', 'inspection', 'repair']);
    }

    public function down(): void
    {
        $this->restrictEnums(['maintenance', 'fault', 'inspection', 'modernization', 'repair']);
    }

    /**
     * Laravel's enum column maps to driver-specific DDL. SQLite test databases
     * are always freshly migrated from the edited create migrations, so there is
     * nothing to alter there.
     *
     * @param  list<string>  $values
     */
    private function restrictEnums(array $values): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        $quoted = collect($values)->map(fn (string $value) => "'{$value}'")->implode(', ');

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE work_orders DROP CONSTRAINT IF EXISTS work_orders_type_check');
            DB::statement("ALTER TABLE work_orders ADD CONSTRAINT work_orders_type_check CHECK (type IN ({$quoted}))");
            DB::statement('ALTER TABLE checklist_templates DROP CONSTRAINT IF EXISTS checklist_templates_work_order_type_check');
            DB::statement("ALTER TABLE checklist_templates ADD CONSTRAINT checklist_templates_work_order_type_check CHECK (work_order_type IN ({$quoted}))");

            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE work_orders MODIFY type ENUM({$quoted}) NOT NULL");
            DB::statement("ALTER TABLE checklist_templates MODIFY work_order_type ENUM({$quoted}) NOT NULL");
        }
    }
};
