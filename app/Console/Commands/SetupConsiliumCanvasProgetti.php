<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Material;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SetupConsiliumCanvasProgetti extends Command
{
    protected $signature = 'canvas:setup-consilium-progetti
                            {--dry-run : Show planned changes without writing to DB}
                            {--rename-existing=true : Rename the legacy Material from "Canvas 4 — Scheda progetto prioritario" to "Canvas 4 — Scheda Progetto 1"}';

    protected $description = 'Crea i tre Material "Canvas 4 — Scheda Progetto 1/2/3" per Interferenza puntando allo stesso file_path HTML, con UUID indipendenti per slot di storage separati in student_canvas_data.';

    private const COURSE_SLUG = 'consilium';
    private const LEGACY_TITLE_PATTERNS = ['canvas 4 — scheda progetto prioritario', 'canvas 4 - scheda progetto prioritario'];
    private const TARGET_TITLE_PROGETTO_1 = 'Canvas 4 — Scheda Progetto 1';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $renameExisting = filter_var($this->option('rename-existing'), FILTER_VALIDATE_BOOLEAN);

        $this->info('=== Setup Interferenza Canvas Progetti ===');
        $this->line('Mode: ' . ($dryRun ? 'DRY RUN (no writes)' : 'LIVE'));
        $this->line('Rename legacy: ' . ($renameExisting ? 'yes' : 'no'));
        $this->newLine();

        DB::beginTransaction();

        try {
            // 1. Find Interferenza course
            $course = Course::where('slug', self::COURSE_SLUG)->first();
            if (!$course) {
                $this->error('Corso "' . self::COURSE_SLUG . '" non trovato.');
                DB::rollBack();
                return 1;
            }
            $this->info("Course: {$course->name} (id={$course->id})");

            // 2. Find existing Canvas 4 Material (legacy or already-renamed)
            $existing = $this->findCanvas4Material($course);
            if (!$existing) {
                $this->error('Nessun Material Canvas 4 trovato in Interferenza. Atteso uno con title contenente "Canvas 4" o file_path con "canvas-4".');
                DB::rollBack();
                return 1;
            }
            $this->info("Found existing: {$existing->title} (id={$existing->id})");
            $this->line("  module_id: {$existing->module_id}");
            $this->line("  file_path: {$existing->file_path}");
            $this->line("  sort_order: {$existing->sort_order}");

            // 3. Rename legacy title -> "Canvas 4 — Scheda Progetto 1" if needed
            if ($renameExisting && $existing->title !== self::TARGET_TITLE_PROGETTO_1) {
                $oldTitle = $existing->title;
                if (!$dryRun) {
                    $existing->title = self::TARGET_TITLE_PROGETTO_1;
                    $existing->save();
                }
                Log::info("[canvas-progetti] " . ($dryRun ? 'WOULD rename' : 'renamed') . " material {$existing->id}: \"{$oldTitle}\" -> \"" . self::TARGET_TITLE_PROGETTO_1 . '"');
                $this->info(($dryRun ? '  WOULD RENAME -> ' : '  RENAMED -> ') . '"' . self::TARGET_TITLE_PROGETTO_1 . '"');
            } elseif ($existing->title === self::TARGET_TITLE_PROGETTO_1) {
                $this->line("  title already correct, skip rename");
            } else {
                $this->warn("  rename-existing=false; lascio title invariato");
            }

            // 4. Shift any non-progetto materials that occupy the slots needed for Progetti 2 and 3
            $progetto1So = $existing->sort_order;
            $slotsToFree = [$progetto1So + 1, $progetto1So + 2];

            $blockers = Material::where('course_id', $existing->course_id)
                ->whereIn('sort_order', $slotsToFree)
                ->where('id', '!=', $existing->id)
                ->where('title', 'NOT LIKE', 'Canvas 4 — Scheda Progetto %')
                ->orderByDesc('sort_order')
                ->get();

            if ($blockers->isEmpty()) {
                $this->line("  No blockers in slots " . implode(', ', $slotsToFree));
            } else {
                foreach ($blockers as $b) {
                    $old = $b->sort_order;
                    $new = $old + 2;
                    if (!$dryRun) {
                        $b->sort_order = $new;
                        $b->save();
                    }
                    Log::info("[canvas-progetti] " . ($dryRun ? 'WOULD shift' : 'shifted') . " material {$b->id} \"{$b->title}\" sort_order {$old} -> {$new}");
                    $this->info(($dryRun ? '  WOULD SHIFT ' : '  SHIFTED ') . "\"{$b->title}\": {$old} -> {$new}");
                }
            }

            // 5. Create Progetti 2 and 3 if missing
            foreach ([2, 3] as $n) {
                $targetTitle = "Canvas 4 — Scheda Progetto {$n}";
                $already = Material::where('module_id', $existing->module_id)
                    ->where('course_id', $existing->course_id)
                    ->where('title', $targetTitle)
                    ->first();

                if ($already) {
                    $this->line("  Progetto {$n}: already exists (id={$already->id}), skip");
                    continue;
                }

                $newSo = $existing->sort_order + ($n - 1);

                if (!$dryRun) {
                    $newMaterial = Material::create([
                        'module_id' => $existing->module_id,
                        'course_id' => $existing->course_id,
                        'title' => $targetTitle,
                        'description' => "Scheda progetto {$n} di 3 — usa lo stesso template del Progetto 1.",
                        'file_path' => $existing->file_path,
                        'file_type' => 'canvas',
                        'sort_order' => $newSo,
                        'is_downloadable' => false,
                        'is_instructor_only' => false,
                    ]);
                    Log::info("[canvas-progetti] created material \"{$targetTitle}\" with uuid={$newMaterial->id} sort_order={$newSo}");
                    $this->info("  CREATED Progetto {$n}: id={$newMaterial->id} sort_order={$newSo}");
                } else {
                    Log::info("[canvas-progetti] WOULD create material \"{$targetTitle}\" sort_order={$newSo}");
                    $this->info("  WOULD CREATE Progetto {$n}: sort_order={$newSo}");
                }
            }

            // 5. Commit or rollback
            if ($dryRun) {
                DB::rollBack();
                $this->newLine();
                $this->warn('DRY RUN — nessuna scrittura effettuata. Transazione rollbackata.');
                return 0;
            }

            DB::commit();
            $this->newLine();
            $this->info('Operazione completata con successo. Transazione committata.');

            // Summary
            $this->newLine();
            $this->info('=== Stato finale ===');
            $finals = Material::where('course_id', $course->id)
                ->where('file_type', 'canvas')
                ->where('title', 'like', 'Canvas 4 — Scheda Progetto %')
                ->orderBy('sort_order')
                ->get(['id', 'title', 'sort_order']);
            foreach ($finals as $m) {
                $this->line("  {$m->title} | sort_order={$m->sort_order} | uuid={$m->id}");
            }

            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Errore: ' . $e->getMessage());
            Log::error('[canvas-progetti] failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    private function findCanvas4Material(Course $course): ?Material
    {
        // Try by current target title first (idempotent re-run)
        $material = Material::where('course_id', $course->id)
            ->where('file_type', 'canvas')
            ->where('title', self::TARGET_TITLE_PROGETTO_1)
            ->first();
        if ($material) {
            return $material;
        }

        // Fallback: legacy title patterns (case-insensitive)
        foreach (self::LEGACY_TITLE_PATTERNS as $pattern) {
            $material = Material::where('course_id', $course->id)
                ->where('file_type', 'canvas')
                ->whereRaw('LOWER(title) = ?', [$pattern])
                ->first();
            if ($material) {
                return $material;
            }
        }

        // Last resort: file_path heuristic
        return Material::where('course_id', $course->id)
            ->where('file_type', 'canvas')
            ->where('file_path', 'like', '%canvas-4%')
            ->orderBy('sort_order')
            ->first();
    }
}
