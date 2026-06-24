<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $admins = array_map(
            'strtolower',
            (array) config('atheneum.admins', [])
        );

        $affected = DB::table('students')
            ->where('auto_enroll_all_courses', true)
            ->when(!empty($admins), fn ($q) =>
                $q->whereNotIn(DB::raw('lower(email)'), $admins))
            ->pluck('email')
            ->all();

        $count = DB::table('students')
            ->where('auto_enroll_all_courses', true)
            ->when(!empty($admins), fn ($q) =>
                $q->whereNotIn(DB::raw('lower(email)'), $admins))
            ->update(['auto_enroll_all_courses' => false]);

        Log::warning('[remediation] reset auto_enroll_all_courses', [
            'reset_count'      => $count,
            'kept_for_admins'  => $admins,
            'reset_for_emails' => $affected,
        ]);
    }

    public function down(): void
    {
        // Irreversibile per natura (non sappiamo lo stato originale
        // di ogni riga). down() volutamente no-op.
    }
};
