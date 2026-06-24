<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

class CoursesSeeder extends Seeder
{
    public function run(): void
    {
        Course::firstOrCreate(
            ['slug' => 'primus'],
            [
                'name' => 'PRIMUS',
                'short_description' => 'Prima di tutto il perche',
                'description' => 'Introduzione all\'AI per imprenditori. 4 ore per capire se e perche l\'AI riguarda la tua PMI. Output: Mappa di Maturita Digitale personalizzata.',
                'color' => '#8A9696',
                'icon' => '✦',
                'duration_hours' => 4,
                'certification_name' => 'Attestato di partecipazione Noscite',
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        Course::firstOrCreate(['slug' => 'consilium'], [
            'name' => 'CONSILIUM',
            'short_description' => 'Strategia AI per PMI',
            'description' => 'Laboratorio direzionale 7 ore per imprenditori e board.',
            'color' => '#55B1AE',
            'icon' => '🎯',
            'duration_hours' => 7,
            'certification_name' => 'Certified AI Strategy Director',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Course::firstOrCreate(['slug' => 'initium'], [
            'name' => 'INITIUM',
            'short_description' => 'Fondamenta AI Operativa',
            'description' => '20 ore + 3h esame per manager e team operativi.',
            'color' => '#3A8C89',
            'icon' => '🚀',
            'duration_hours' => 23,
            'certification_name' => 'Certified AI Productivity User',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        Course::firstOrCreate(['slug' => 'structura'], [
            'name' => 'STRUCTURA',
            'short_description' => 'Second Brain Aziendale',
            'description' => '24 ore per knowledge worker e PM con Obsidian.',
            'color' => '#E28A53',
            'icon' => '🧠',
            'duration_hours' => 24,
            'certification_name' => 'Certified Second Brain Implementer',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        Course::firstOrCreate(['slug' => 'ai-agents-mcp'], [
            'name' => 'AI AGENTS & MCP',
            'short_description' => 'Agenti AI in Azienda',
            'description' => '~9 ore su governance agenti AI e protocollo MCP.',
            'color' => '#1A1F1F',
            'icon' => '⚡',
            'duration_hours' => 9,
            'certification_name' => 'Certified AI Agent Governance Practitioner',
            'is_active' => true,
            'sort_order' => 4,
        ]);

        // Aggiorna sort_order/icon/color per coerenza
        Course::where('slug', 'consilium')->update(['sort_order' => 1, 'icon' => '🎯', 'color' => '#55B1AE']);
        Course::where('slug', 'initium')->update(['sort_order' => 2, 'icon' => '🚀', 'color' => '#3A8C89']);
        Course::where('slug', 'structura')->update(['sort_order' => 3, 'icon' => '🧠', 'color' => '#E28A53']);
        Course::where('slug', 'ai-agents-mcp')->update(['sort_order' => 4, 'icon' => '⚡', 'color' => '#1A1F1F']);
    }
}
