<?php

namespace App\Livewire;

use Livewire\Component;

class FaqAccordion extends Component
{
    public int $openIndex = -1;

    public array $faqs = [
        [
            'question' => 'Cos\'e il programma Interferenza?',
            'answer' => 'Interferenza e un laboratorio strategico di 1 giornata (7 ore) dedicato a CEO, fondatori e membri del CdA. Attraverso sessioni guidate, esploriamo come l\'intelligenza artificiale puo integrarsi nella visione e nella strategia della tua impresa, producendo una AI Policy e una Roadmap a 90 giorni.',
        ],
        [
            'question' => 'A chi e rivolto Segnale?',
            'answer' => 'Segnale e un percorso intensivo di 3 giornate rivolto a manager operativi e responsabili di funzione. Il programma fornisce competenze pratiche sull\'uso dell\'AI nei processi quotidiani, dalla comprensione degli strumenti alla loro applicazione concreta nel contesto aziendale.',
        ],
        [
            'question' => 'Cosa include Circuito?',
            'answer' => 'Circuito e un programma avanzato incentrato sulla costruzione del Second Brain aziendale con Obsidian. Include la progettazione dell\'architettura informativa, la configurazione degli strumenti di knowledge management e la formazione del team per la gestione autonoma del sistema.',
        ],
        [
            'question' => 'Cos\'e AI Demystified?',
            'answer' => 'AI Demystified e un modulo formativo dedicato all\'etica dell\'intelligenza artificiale e ai bias algoritmici. Affronta temi come la trasparenza dei modelli, l\'impatto sociale delle decisioni automatizzate e le best practice per un utilizzo responsabile dell\'AI in azienda.',
        ],
        [
            'question' => 'Cos\'e il corso Second Brain?',
            'answer' => 'Il corso Second Brain insegna le tecniche di Personal Knowledge Management (PKM) con Obsidian. Pensato per knowledge workers, manager e professionisti, il corso fornisce un metodo strutturato per organizzare, collegare e riutilizzare le proprie conoscenze in modo efficace.',
        ],
        [
            'question' => 'Come posso iscrivermi?',
            'answer' => 'Puoi iscriverti compilando il modulo nella pagina Contatti. Riceverai una risposta entro 24 ore lavorative con tutte le informazioni sul programma scelto, le date disponibili e le modalita di partecipazione.',
        ],
        [
            'question' => 'I programmi sono in presenza o online?',
            'answer' => 'I nostri programmi sono disponibili in modalita mista: in presenza presso la sede del cliente o in location dedicate, e da remoto tramite piattaforme di videoconferenza. La modalita viene concordata in fase di iscrizione in base alle esigenze dell\'organizzazione.',
        ],
        [
            'question' => 'Quanto durano i programmi?',
            'answer' => 'Le durate variano: Interferenza e un laboratorio di 1 giorno (7h), Segnale si sviluppa in 3 giornate intensive, Circuito e un percorso di 4-8 settimane, AI Demystified e un modulo di mezza giornata, e il corso Second Brain prevede 2 giornate di formazione pratica.',
        ],
    ];

    public function toggle(int $index): void
    {
        $this->openIndex = $this->openIndex === $index ? -1 : $index;
    }

    public function render()
    {
        return view('livewire.faq-accordion');
    }
}
