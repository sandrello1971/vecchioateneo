<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Database\Seeder;

class ModulesSeeder extends Seeder
{
    public function run(): void
    {
        $primus = Course::where('slug', 'primus')->first();
        $moduliPrimus = [
            ['title' => 'Il mondo che non aspetta', 'description' => 'La fotografia della trasformazione AI nelle PMI italiane. Dati, casi reali, demo dal vivo.', 'duration_minutes' => 45, 'sort_order' => 1],
            ['title' => 'Il prezzo dell\'invisibilita', 'description' => 'Calcolo personalizzato del costo mensile di non agire. Canvas interattivo.', 'duration_minutes' => 60, 'sort_order' => 2],
            ['title' => 'La tua azienda nell\'AI', 'description' => 'Identificazione dei processi dove l\'AI genera valore nei prossimi 90 giorni.', 'duration_minutes' => 60, 'sort_order' => 3],
            ['title' => 'La tua mappa e il tuo percorso', 'description' => 'Mappa di Maturita Digitale personalizzata + percorso Noscite consigliato.', 'duration_minutes' => 35, 'sort_order' => 4],
        ];
        foreach ($moduliPrimus as $m) {
            Module::firstOrCreate(
                ['course_id' => $primus->id, 'title' => $m['title']],
                array_merge($m, ['course_id' => $primus->id, 'is_active' => true])
            );
        }

        $consilium = Course::where('slug', 'consilium')->first();
        $moduliConsilium = [
            ['title' => 'Scenario AI per PMI', 'description' => 'Opportunita, rischi e casi d\'uso. Demo prompt generico vs contestualizzato.', 'duration_minutes' => 90, 'sort_order' => 1],
            ['title' => 'Mappatura processi e casi d\'uso AI', 'description' => 'Canvas 1-2-3: mappa processi, schede caso d\'uso, matrice impatto/fattibilita.', 'duration_minutes' => 120, 'sort_order' => 2],
            ['title' => 'Selezione progetti prioritari', 'description' => 'Canvas 4: 3 progetti con owner, KPI e fabbisogno formativo.', 'duration_minutes' => 90, 'sort_order' => 3],
            ['title' => 'AI Usage Policy e Roadmap 90 giorni', 'description' => 'Canvas 5-6: policy essenziale e roadmap operativa in 3 fasi.', 'duration_minutes' => 120, 'sort_order' => 4],
        ];
        foreach ($moduliConsilium as $m) {
            Module::firstOrCreate(
                ['course_id' => $consilium->id, 'title' => $m['title']],
                array_merge($m, ['course_id' => $consilium->id, 'is_active' => true])
            );
        }

        $initium = Course::where('slug', 'initium')->first();
        $moduliInitium = [
            ['title' => 'Capire l\'AI — logica, dati e limiti', 'description' => 'LLM, allucinazioni, bias cognitivi, panorama AI 2026, Human-AI Security Awareness.', 'duration_minutes' => 240, 'sort_order' => 1],
            ['title' => 'Prompt Engineering e Perplexity', 'description' => 'Framework COFT, tecniche avanzate, prompting sicuro, Perplexity come ricerca verificata.', 'duration_minutes' => 240, 'sort_order' => 2],
            ['title' => 'Claude e ChatGPT in azienda', 'description' => 'Profilo comparativo, workflow combinato Perplexity→Claude→ChatGPT, matrice dati.', 'duration_minutes' => 240, 'sort_order' => 3],
            ['title' => 'Vibe Coding e Copilot 365', 'description' => 'Automazioni Python, checklist sicurezza codice AI, Copilot 365 Agent Mode.', 'duration_minutes' => 240, 'sort_order' => 4],
            ['title' => 'Second Brain, Data Governance e Private AI', 'description' => 'Metodo CODE, AI Usage Policy, Private AI con Ollama, roadmap 90 giorni.', 'duration_minutes' => 240, 'sort_order' => 5],
            ['title' => 'Esame di certificazione', 'description' => 'Assessment pratico open book 3h. Soglia: 70/100. Certified AI Productivity User.', 'duration_minutes' => 180, 'sort_order' => 6],
        ];
        foreach ($moduliInitium as $m) {
            Module::firstOrCreate(
                ['course_id' => $initium->id, 'title' => $m['title']],
                array_merge($m, ['course_id' => $initium->id, 'is_active' => true])
            );
        }

        $structura = Course::where('slug', 'structura')->first();
        $moduliStructura = [
            ['title' => 'Metodo CODE e fondamenti del Second Brain', 'description' => 'Costo caos informativo, metodo CODE, case study PMI con risultati misurabili.', 'duration_minutes' => 240, 'sort_order' => 1],
            ['title' => 'Setup Obsidian e Vault Aziendale', 'description' => 'Architettura 8 cartelle, plugin core e community, privacy by design GDPR.', 'duration_minutes' => 240, 'sort_order' => 2],
            ['title' => 'Template e Organizzazione Avanzata', 'description' => '4 template Templater, dashboard Bases, tag e link bidirezionali.', 'duration_minutes' => 240, 'sort_order' => 3],
            ['title' => 'AI e Automazioni nel Vault', 'description' => 'AI come analista del vault, RAG su Obsidian, QuickAdd e Periodic Notes.', 'duration_minutes' => 240, 'sort_order' => 4],
            ['title' => 'Collaborazione e Governance del Vault', 'description' => '4 ruoli, naming convention, Obsidian Sync E2E, onboarding 5 giorni.', 'duration_minutes' => 240, 'sort_order' => 5],
            ['title' => 'Certificazione e Piano d\'Azione', 'description' => 'Assessment pratico 3h open book. Certified Second Brain Implementer.', 'duration_minutes' => 240, 'sort_order' => 6],
        ];
        foreach ($moduliStructura as $m) {
            Module::firstOrCreate(
                ['course_id' => $structura->id, 'title' => $m['title']],
                array_merge($m, ['course_id' => $structura->id, 'is_active' => true])
            );
        }

        $agents = Course::where('slug', 'ai-agents-mcp')->first();
        $moduliAgents = [
            ['title' => 'L1 M1 — Il cambio di paradigma', 'description' => 'Chatbot vs agente vs RPA, loop ReAct, MCP standard de facto, 5 casi PMI italiane.', 'duration_minutes' => 45, 'sort_order' => 1],
            ['title' => 'L1 M2 — Come ragiona un agente', 'description' => 'Tool, risorse, memoria. Regola semaforo verde/giallo/rosso. Anti-pattern pericolosi.', 'duration_minutes' => 45, 'sort_order' => 2],
            ['title' => 'L1 M3 — Agenti in azienda', 'description' => '4 scenari PMI con ROI, 3 livelli integrazione MCP, caso Travel Agency -89%.', 'duration_minutes' => 75, 'sort_order' => 3],
            ['title' => 'L1 M4 — Rischi, governance e quando fermarsi', 'description' => '3 rischi sistemici, matrice HITL, GDPR agentivo, checklist prontezza 10 domande.', 'duration_minutes' => 45, 'sort_order' => 4],
            ['title' => 'L2 Blocco A — Fondamenta MCP', 'description' => 'Protocollo MCP, demo live MCPHub Noscite, anatomia agente aziendale.', 'duration_minutes' => 120, 'sort_order' => 5],
            ['title' => 'L2 Blocco B — Canvas Architetto dell\'Agente', 'description' => 'Canvas 7 sezioni su caso reale: obiettivo, dati, tool, trigger, output, HITL, KPI.', 'duration_minutes' => 120, 'sort_order' => 6],
            ['title' => 'L2 Blocco C — Demo MCPHub', 'description' => 'Demo live interrogazione dati aziendali via agente in produzione.', 'duration_minutes' => 60, 'sort_order' => 7],
            ['title' => 'L2 Blocco D — Make vs Buy e Piano 90 giorni', 'description' => 'Framework Make vs Buy, 8 criteri vendor selection, Canvas Piano 90 giorni.', 'duration_minutes' => 60, 'sort_order' => 8],
        ];
        foreach ($moduliAgents as $m) {
            Module::firstOrCreate(
                ['course_id' => $agents->id, 'title' => $m['title']],
                array_merge($m, ['course_id' => $agents->id, 'is_active' => true])
            );
        }
    }
}
