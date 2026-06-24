<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

// Materie standard di licei e istituti tecnici italiani. is_custom=false.
// Idempotente (firstOrCreate su name): le materie custom dei docenti non vengono toccate.
class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            // Area comune / linguistica-letteraria
            'Lingua e letteratura italiana', 'Latino', 'Greco', 'Storia', 'Geografia',
            'Geostoria', 'Filosofia', 'Storia dell\'arte', 'Disegno e storia dell\'arte',
            // Lingue straniere
            'Inglese', 'Francese', 'Spagnolo', 'Tedesco',
            // Area scientifica
            'Matematica', 'Fisica', 'Scienze naturali', 'Chimica', 'Biologia',
            'Scienze della Terra',
            // Scienze umane / giuridico-economico
            'Scienze umane', 'Diritto ed economia', 'Diritto', 'Economia politica',
            'Economia aziendale', 'Relazioni internazionali',
            // Istituti tecnici — informatica / telecomunicazioni
            'Informatica', 'Sistemi e reti',
            'Tecnologie e progettazione di sistemi informatici e di telecomunicazioni',
            'Telecomunicazioni', 'Gestione progetto e organizzazione d\'impresa',
            'Tecnologie informatiche', 'Scienze e tecnologie applicate',
            // Istituti tecnici — elettronica / meccanica
            'Elettronica ed elettrotecnica', 'Elettrotecnica', 'Meccanica, macchine ed energia',
            // Istituti tecnici — chimica / ambiente
            'Chimica analitica e strumentale',
            'Biologia, microbiologia e tecnologie di controllo ambientale',
            // Istituti tecnici — turismo
            'Discipline turistiche e aziendali', 'Geografia turistica',
            // Trasversali
            'Scienze motorie e sportive', 'Religione cattolica / Attività alternativa',
            'Educazione civica',
        ];

        foreach ($subjects as $name) {
            Subject::firstOrCreate(['name' => $name], ['is_custom' => false]);
        }
    }
}
