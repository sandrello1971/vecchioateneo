<?php

namespace App\Models\Concerns;

use App\Models\School;

/**
 * Appartenenza a una scuola (tenant fase 2). Fornisce la relazione `school` e
 * lo scope `forSchool($id)` (table-qualified, join-safe). L'isolamento di
 * tenancy NON è un global scope legato alla sessione (romperebbe admin/CLI):
 * è applicato esplicitamente nei controller via `ResolvesSchoolAccess`,
 * coerente col pattern di fetta 1 (`ResolvesScholaAccess`).
 */
trait BelongsToSchool
{
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function scopeForSchool($query, ?string $schoolId)
    {
        return $query->where($this->getTable() . '.school_id', $schoolId);
    }
}
