<?php

namespace App\Services\Schola;

use App\Mail\StudentInviteMail;
use App\Models\ClassStudent;
use App\Models\ImportBatch;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Import massivo studenti (P14) — cultura a gate come P13. Credenziali DUALI
 * (§8.1): con email → invito set-password; senza email → username interno
 * generato + password temporanea. data_nascita obbligatoria (§8.2, minori).
 * Tutto vincolato alla scuola.
 */
class StudentImportService
{
    private const REQUIRED = ['nome', 'cognome', 'data_nascita', 'classe'];

    /** Dry-run: NESSUNA scrittura. */
    public function analyze(string $csvContent, School $school): array
    {
        [$delimiter, $header, $records] = $this->parse($csvContent);

        $missing = array_diff(self::REQUIRED, $header);
        if ($missing) {
            return ['header_error' => 'Intestazione non valida. Richieste: nome, cognome, data_nascita, classe (email e consenso opzionali). Mancanti: '
                . implode(', ', $missing) . '.', 'delimiter' => $delimiter, 'summary' => [], 'rows' => []];
        }

        $idx = array_flip($header);
        $hasConsentCol = isset($idx['consenso']);
        $existingClasses = $this->schoolClassMap($school);
        $seen = [];

        $rows = [];
        $summary = ['total' => 0, 'valid' => 0, 'attach' => 0, 'duplicate' => 0, 'conflict' => 0, 'error' => 0,
            'minors' => 0, 'without_email' => 0, 'classes_to_create' => []];

        foreach ($records as $i => $cells) {
            $nome = trim($cells[$idx['nome']] ?? '');
            $cognome = trim($cells[$idx['cognome']] ?? '');
            $email = mb_strtolower(trim($cells[$idx['email']] ?? ''));
            $birth = trim($cells[$idx['data_nascita']] ?? '');
            $className = trim($cells[$idx['classe']] ?? '');
            $consent = $hasConsentCol && $this->truthy(trim($cells[$idx['consenso']] ?? ''));

            $row = [
                'line' => $i + 2, 'nome' => $nome, 'cognome' => $cognome, 'email' => $email,
                'username_base' => null, 'birth_date' => null, 'class_name' => $className,
                'class_status' => 'none', 'is_minor' => false, 'consent' => $consent,
                'status' => 'valid', 'message' => '',
            ];

            // Validazione
            $birthDate = $this->parseDate($birth);
            if ($nome === '' || $cognome === '' || $className === '') {
                $row['status'] = 'error';
                $row['message'] = 'Campi obbligatori mancanti (nome, cognome, classe).';
            } elseif (!$birthDate) {
                $row['status'] = 'error';
                $row['message'] = 'Data di nascita mancante o non valida (YYYY-MM-DD).';
            } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $row['status'] = 'error';
                $row['message'] = 'Email non valida.';
            } elseif ($email !== '' && isset($seen[$email])) {
                $row['status'] = 'error';
                $row['message'] = 'Email ripetuta nel file (riga ' . $seen[$email] . ').';
            }

            if ($row['status'] !== 'error') {
                $row['birth_date'] = $birthDate->toDateString();
                $row['is_minor'] = $birthDate->age < 18;
                if ($row['is_minor']) {
                    $summary['minors']++;
                }
                if ($email !== '') {
                    $seen[$email] = $row['line'];
                } else {
                    $summary['without_email']++;
                    $row['username_base'] = $this->usernameBase($nome, $cognome, $school);
                }

                // Risoluzione classe
                $key = mb_strtolower($className);
                if (isset($existingClasses[$key])) {
                    $row['class_status'] = 'existing';
                } else {
                    $row['class_status'] = 'to_create';
                    $summary['classes_to_create'][$className] = true;
                }

                // Dedup / aggancio (identità multi-contesto). Blocco SOLO se altra scuola.
                if ($email !== '') {
                    $existing = Student::where('email', $email)->first();
                    if ($existing) {
                        if ($existing->school_id && $existing->school_id !== $school->id) {
                            $row['status'] = 'conflict';
                            $row['message'] = 'Account già esistente in un\'altra scuola: non spostato.';
                        } elseif ($existing->school_id === $school->id && $existing->role === 'student') {
                            $row['status'] = 'duplicate';
                            $row['message'] = 'Studente già presente in questa scuola.';
                        } else {
                            $row['status'] = 'attach';
                            $row['message'] = 'Account esistente senza scuola: agganciato e iscritto alla classe (iscrizioni corsi preservate).';
                        }
                    }
                } else {
                    $existing = Student::where('username', $row['username_base'])
                        ->where('school_id', $school->id)->where('role', 'student')->first();
                    if ($existing) {
                        $row['status'] = 'duplicate';
                        $row['message'] = 'Studente (username) già presente in questa scuola.';
                    }
                }
            }

            $summary['total']++;
            $summary[$row['status']]++;
            $rows[] = $row;
        }

        $summary['classes_to_create'] = array_keys($summary['classes_to_create']);

        return ['header_error' => null, 'delimiter' => $delimiter, 'summary' => $summary, 'rows' => $rows];
    }

    /**
     * Inserimento SINGOLO (form): stessa logica del massivo via analyze()+commit().
     * NON crea classi mancanti (il form sceglie una classe esistente). Ritorna
     * anche le eventuali credenziali generate (riga senza email).
     *
     * @param array{nome:string,cognome:string,email:?string,birth_date:string,classe:string,consent?:bool} $fields
     * @return array{result:array, row:?array}
     */
    public function commitSingle(array $fields, School $school): array
    {
        $csv = ImportCsv::oneRow(
            ['nome', 'cognome', 'email', 'data_nascita', 'classe', 'consenso'],
            [
                $fields['nome'] ?? '', $fields['cognome'] ?? '', $fields['email'] ?? '',
                $fields['birth_date'] ?? '', $fields['classe'] ?? '',
                !empty($fields['consent']) ? 'si' : '',
            ]
        );

        $analysis = $this->analyze($csv, $school);
        $batch = ImportBatch::create([
            'school_id' => $school->id, 'created_by' => session('student_id'),
            'type' => 'students', 'status' => 'previewed',
            'source_filename' => 'Inserimento manuale',
            'summary' => $analysis['summary'], 'rows' => $analysis['rows'],
        ]);

        // createMissingClasses=false: il form offre solo classi esistenti.
        $result = $this->commit($batch, $school, false, 'update');
        $clean = $result;
        unset($clean['generated']);
        $batch->update(['status' => 'committed', 'summary' => array_merge($batch->summary ?? [], ['result' => $clean])]);

        return ['result' => $result, 'row' => $analysis['rows'][0] ?? null];
    }

    /**
     * Applica un batch previewed. Idempotente. Ritorna anche le credenziali
     * generate (username + password in CHIARO, una tantum) per le righe senza
     * email — il chiamante le mostra/esporta una sola volta.
     *
     * @return array{created:int,updated:int,skipped:int,classes_created:int,generated:array}
     */
    public function commit(ImportBatch $batch, School $school, bool $createMissingClasses, string $duplicateAction = 'update'): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'classes_created' => 0, 'generated' => []];
        $rows = $batch->rows ?? [];

        // Risoluzione/creazione classi (una volta per nome).
        $classMap = $this->schoolClassMap($school); // lower(name) => id
        foreach ($rows as $row) {
            if (!in_array($row['status'] ?? '', ['valid', 'attach', 'duplicate'], true)) {
                continue;
            }
            $key = mb_strtolower($row['class_name']);
            if (!isset($classMap[$key]) && $createMissingClasses) {
                $class = SchoolClass::create([
                    'school_id' => $school->id, 'teacher_id' => null, 'name' => $row['class_name'],
                    'subject_id' => null, 'school_year' => $this->currentSchoolYear(),
                    'invite_code' => SchoolClass::generateInviteCode(),
                    'invite_enabled' => false, 'requires_approval' => false, 'is_archived' => false,
                ]);
                $classMap[$key] = $class->id;
                $result['classes_created']++;
            }
        }

        foreach ($rows as $row) {
            $status = $row['status'] ?? 'error';
            if (!in_array($status, ['valid', 'attach', 'duplicate'], true)) {
                continue;
            }
            if ($status === 'duplicate' && $duplicateAction === 'skip') {
                $result['skipped']++;
                continue;
            }

            $classId = $classMap[mb_strtolower($row['class_name'])] ?? null;
            if (!$classId) {
                $result['skipped']++; // classe mancante e creazione non confermata
                continue;
            }

            DB::transaction(function () use ($row, $school, $classId, $duplicateAction, &$result) {
                $student = $this->resolveOrCreate($row, $school, $duplicateAction, $result);
                if (!$student) {
                    return; // skipped (conflitto / duplicate-skip già contato)
                }
                $this->enroll($student, $classId, $row['consent'] ?? false);
            });
        }

        return $result;
    }

    private function resolveOrCreate(array $row, School $school, string $duplicateAction, array &$result): ?Student
    {
        $hasEmail = ($row['email'] ?? '') !== '';

        // Cerca esistente (email o username) per idempotenza.
        $existing = $hasEmail
            ? Student::where('email', $row['email'])->first()
            : Student::where('username', $row['username_base'])->where('school_id', $school->id)->where('role', 'student')->first();

        if ($existing) {
            if ($existing->school_id && $existing->school_id !== $school->id) {
                $result['skipped']++; // conflitto cross-scuola → mai assorbito
                return null;
            }
            // AGGANCIO: set school_id; promuovi a 'student' SOLO se senza ruolo
            // (non declassare un professore); iscrizioni corsi preservate.
            $updates = ['school_id' => $school->id];
            if ($existing->role === null) {
                $updates['role'] = 'student';
            }
            $existing->update($updates);
            $result['updated']++;
            return $existing; // iscrizione/consenso assicurati dal chiamante
        }

        $tempPassword = 'Nsc' . now()->format('y') . '!' . Str::upper(Str::random(5));
        $attrs = [
            'name' => trim($row['nome'] . ' ' . $row['cognome']),
            'password' => $tempPassword, 'role' => 'student', 'school_id' => $school->id,
            'birth_date' => $row['birth_date'], 'is_active' => true, 'must_change_password' => true,
        ];

        if ($hasEmail) {
            $student = Student::create($attrs + ['email' => $row['email']]);
            Mail::to($student->email)->queue(new StudentInviteMail($student, $tempPassword, $school));
        } else {
            $username = $this->uniqueUsername($row['username_base']);
            $student = Student::create($attrs + ['username' => $username]);
            // Credenziale in chiaro: una tantum, mai più ripescabile (hash a DB).
            $result['generated'][] = ['name' => $student->name, 'username' => $username, 'password' => $tempPassword];
        }

        $result['created']++;
        return $student;
    }

    private function enroll(Student $student, string $classId, bool $consent): void
    {
        $enrollment = ClassStudent::firstOrCreate(
            ['school_class_id' => $classId, 'student_id' => $student->id],
            ['status' => 'active', 'approved_at' => now()] // iscrizione diretta, NIENTE codice invito
        );
        if ($consent && !$enrollment->consent_at) {
            $enrollment->update(['consent_at' => now()]);
        }
    }

    // ===== helper =====

    private function usernameBase(string $nome, string $cognome, School $school): string
    {
        return Str::slug($nome . ' ' . $cognome, '.') . '.' . $school->slug;
    }

    private function uniqueUsername(string $base): string
    {
        $candidate = $base;
        $n = 1;
        while (Student::where('username', $candidate)->exists()) {
            $candidate = $base . '.' . (++$n);
        }
        return $candidate;
    }

    private function parseDate(string $value): ?Carbon
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            $d = Carbon::createFromFormat('Y-m-d', $value);
            return $d->format('Y-m-d') === $value ? $d : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function truthy(string $v): bool
    {
        return in_array(mb_strtolower($v), ['1', 'si', 'sì', 'true', 'yes', 'x', 'y'], true);
    }

    private function currentSchoolYear(): string
    {
        $now = now();
        $start = $now->month >= 9 ? $now->year : $now->year - 1;
        return $start . '/' . ($start + 1);
    }

    /** @return array<string,string> lower(name) => class_id (non archiviate) */
    private function schoolClassMap(School $school): array
    {
        return SchoolClass::where('school_id', $school->id)->where('is_archived', false)
            ->get(['id', 'name'])->mapWithKeys(fn ($c) => [mb_strtolower(trim($c->name)) => $c->id])->all();
    }

    /** @return array{0:string,1:array,2:array} */
    private function parse(string $content): array
    {
        $content = ltrim($content, "\xEF\xBB\xBF");
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', $content), fn ($l) => trim($l) !== ''));
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
}
