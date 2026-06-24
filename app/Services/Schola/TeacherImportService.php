<?php

namespace App\Services\Schola;

use App\Mail\TeacherInviteMail;
use App\Models\ImportBatch;
use App\Models\ProfessorSubject;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Import massivo docenti (P13) — cultura a gate: `analyze()` è dry-run e NON
 * scrive; `commit()` applica un batch già "previewed" su conferma, idempotente
 * per email. Tenancy: tutto vincolato alla scuola passata.
 */
class TeacherImportService
{
    private const REQUIRED = ['nome', 'cognome', 'email', 'materie'];

    /**
     * Dry-run: analizza il CSV per la scuola, NESSUNA scrittura.
     *
     * @return array{header_error:?string, delimiter:string, summary:array, rows:array}
     */
    public function analyze(string $csvContent, School $school): array
    {
        [$delimiter, $header, $records] = $this->parse($csvContent);

        $missing = array_diff(self::REQUIRED, $header);
        if ($missing) {
            return [
                'header_error' => 'Intestazione CSV non valida. Colonne richieste: ' . implode(', ', self::REQUIRED)
                    . '. Mancanti: ' . implode(', ', $missing) . '.',
                'delimiter' => $delimiter, 'summary' => [], 'rows' => [],
            ];
        }

        $idx = array_flip($header);
        $subjectMap = $this->subjectMap();
        $seenEmails = [];

        $rows = [];
        $summary = ['total' => 0, 'valid' => 0, 'attach' => 0, 'duplicate' => 0, 'conflict' => 0, 'error' => 0, 'unknown_subjects' => 0];

        foreach ($records as $line => $cells) {
            $nome = trim($cells[$idx['nome']] ?? '');
            $cognome = trim($cells[$idx['cognome']] ?? '');
            $email = mb_strtolower(trim($cells[$idx['email']] ?? ''));
            $materieRaw = trim($cells[$idx['materie']] ?? '');

            $row = [
                'line' => $line + 2, // +1 header, +1 base-1
                'nome' => $nome, 'cognome' => $cognome, 'email' => $email,
                'materie_raw' => $materieRaw, 'subject_ids' => [], 'unknown' => [],
                'status' => 'valid', 'message' => '',
            ];

            // Validazione formato
            if ($nome === '' || $cognome === '' || $email === '') {
                $row['status'] = 'error';
                $row['message'] = 'Campi obbligatori mancanti (nome, cognome, email).';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $row['status'] = 'error';
                $row['message'] = 'Email non valida.';
            } elseif (isset($seenEmails[$email])) {
                $row['status'] = 'error';
                $row['message'] = 'Email ripetuta nel file (riga ' . $seenEmails[$email] . ').';
            }

            if ($row['status'] !== 'error') {
                $seenEmails[$email] = $row['line'];

                // Risoluzione materie (mai create in silenzio)
                foreach ($this->splitSubjects($materieRaw) as $name) {
                    $key = mb_strtolower($name);
                    if (isset($subjectMap[$key])) {
                        $row['subject_ids'][] = $subjectMap[$key];
                    } else {
                        $row['unknown'][] = $name;
                    }
                }
                if ($row['unknown']) {
                    $summary['unknown_subjects']++;
                }

                // Esistenza account: AGGANCIO (identità multi-contesto) invece
                // di blocco. Si blocca SOLO se l'email è di un'ALTRA scuola.
                $existing = Student::where('email', $email)->first();
                if ($existing) {
                    if ($existing->school_id && $existing->school_id !== $school->id) {
                        $row['status'] = 'conflict';
                        $row['message'] = 'Account già esistente in un\'altra scuola: non spostato.';
                    } elseif ($existing->school_id === $school->id && $existing->role === 'professor') {
                        $row['status'] = 'duplicate';
                        $row['message'] = 'Docente già presente in questa scuola.';
                    } else {
                        // school_id null (corsista / professore libero / formatore)
                        // oppure stessa scuola con altro cappello (es. segreteria).
                        $row['status'] = 'attach';
                        $row['message'] = $existing->school_id
                            ? 'Account già in questa scuola: aggiunta la capacità docente (altri ruoli preservati).'
                            : 'Account esistente senza scuola: agganciato come docente (iscrizioni corsi preservate).';
                    }
                }
            }

            $summary['total']++;
            $summary[$row['status']]++;
            $rows[] = $row;
        }

        return ['header_error' => null, 'delimiter' => $delimiter, 'summary' => $summary, 'rows' => $rows];
    }

    /**
     * Inserimento SINGOLO (form): stessa identica logica del massivo — costruisce
     * una riga CSV e passa per analyze()+commit(). Nessuna logica duplicata.
     *
     * @param array{nome:string,cognome:string,email:string,materie:array<int,string>} $fields
     * @return array{result:array, row:?array}
     */
    public function commitSingle(array $fields, School $school): array
    {
        $csv = ImportCsv::oneRow(
            ['nome', 'cognome', 'email', 'materie'],
            [$fields['nome'] ?? '', $fields['cognome'] ?? '', $fields['email'] ?? '', implode('|', $fields['materie'] ?? [])]
        );

        $analysis = $this->analyze($csv, $school);
        $batch = ImportBatch::create([
            'school_id' => $school->id, 'created_by' => session('student_id'),
            'type' => 'professors', 'status' => 'previewed',
            'source_filename' => 'Inserimento manuale',
            'summary' => $analysis['summary'], 'rows' => $analysis['rows'],
        ]);

        $result = $this->commit($batch, $school, 'update');
        $batch->update(['status' => 'committed', 'summary' => array_merge($batch->summary ?? [], ['result' => $result])]);

        return ['result' => $result, 'row' => $analysis['rows'][0] ?? null];
    }

    /**
     * Applica un batch "previewed". Idempotente per email. Solo righe valide o
     * duplicate (secondo $duplicateAction). Tenancy: scrive solo nella scuola.
     *
     * @return array{created:int, updated:int, skipped:int}
     */
    public function commit(ImportBatch $batch, School $school, string $duplicateAction = 'update'): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach (($batch->rows ?? []) as $row) {
            $status = $row['status'] ?? 'error';
            if (!in_array($status, ['valid', 'attach', 'duplicate'], true)) {
                continue; // error/conflict mai applicati
            }
            if ($status === 'duplicate' && $duplicateAction === 'skip') {
                $result['skipped']++;
                continue;
            }

            DB::transaction(function () use ($row, $school, &$result) {
                $existing = Student::where('email', $row['email'])->first();

                // Re-check al momento dell'apply: blocco SOLO se di un'altra scuola.
                if ($existing) {
                    if ($existing->school_id && $existing->school_id !== $school->id) {
                        $result['skipped']++; // conflitto cross-scuola → mai spostato
                        return;
                    }
                    // AGGANCIO: aggiunge la capacità docente, preservando il resto
                    // (role solo promosso a professor; is_secretary/iscrizioni intatti).
                    $existing->update(['school_id' => $school->id, 'role' => 'professor']);
                    $this->syncSubjects($existing, $row['subject_ids'] ?? [], $school);
                    $result['updated']++;
                    return;
                }

                $tempPassword = 'Nsc' . now()->format('y') . '!' . Str::upper(Str::random(5));
                $teacher = Student::create([
                    'name' => trim($row['nome'] . ' ' . $row['cognome']),
                    'email' => $row['email'],
                    'password' => $tempPassword,
                    'role' => 'professor',
                    'school_id' => $school->id,
                    'is_active' => true,
                    'must_change_password' => true,
                ]);
                $this->syncSubjects($teacher, $row['subject_ids'] ?? [], $school);

                Mail::to($teacher->email)->queue(new TeacherInviteMail($teacher, $tempPassword, $school));
                $result['created']++;
            });
        }

        return $result;
    }

    private function syncSubjects(Student $teacher, array $subjectIds, School $school): void
    {
        foreach (array_unique($subjectIds) as $subjectId) {
            ProfessorSubject::firstOrCreate([
                'teacher_id' => $teacher->id,
                'subject_id' => $subjectId,
                'school_id' => $school->id,
            ]);
        }
    }

    /** @return array{0:string,1:array,2:array} [delimiter, header, records] */
    private function parse(string $content): array
    {
        $content = ltrim($content, "\xEF\xBB\xBF"); // BOM UTF-8
        $lines = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', $content),
            fn ($l) => trim($l) !== ''
        ));
        if (empty($lines)) {
            return [',', [], []];
        }

        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(fn ($h) => mb_strtolower(trim($h)), str_getcsv($lines[0], $delimiter));

        $records = [];
        foreach (array_slice($lines, 1) as $i => $line) {
            $records[$i] = str_getcsv($line, $delimiter);
        }

        return [$delimiter, $header, $records];
    }

    /** @return array<string,string> nome_materia_lower => subject_id */
    private function subjectMap(): array
    {
        return Subject::all()->mapWithKeys(fn ($s) => [mb_strtolower(trim($s->name)) => $s->id])->all();
    }

    /** @return array<int,string> */
    private function splitSubjects(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode('|', $raw)), fn ($s) => $s !== ''));
    }
}
