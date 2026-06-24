<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// P25.B-a.2 — I claim/proposte con content_source='student' usano module_id come ancora,
// non block_id (che è del lato formatore/course_sources). Rendo block_id NULLABLE su
// entrambe le tabelle P25. ALTER su tabelle dell'agente (NON su modules/students):
// nessun dato esterno toccato. L'esclusività block_id↔module_id per content_source è
// garantita nel codice (agente/applicator).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE freshness_claims ALTER COLUMN block_id DROP NOT NULL');
        DB::statement('ALTER TABLE update_proposals ALTER COLUMN block_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE freshness_claims ALTER COLUMN block_id SET NOT NULL');
        DB::statement('ALTER TABLE update_proposals ALTER COLUMN block_id SET NOT NULL');
    }
};
