@extends('layouts.admin')

@section('title', 'Studenti eliminati')

@section('content')
@php use App\Models\Student; @endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Studenti eliminati</h1>
            <p class="text-sm text-gray-600 mt-1">
                Studenti spostati nel cestino. Puoi ripristinarli o eliminarli definitivamente dal database.
            </p>
        </div>
        <a href="{{ route('admin.students.index') }}"
           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            ← Torna agli studenti attivi
        </a>
    </div>

    @if (session('success'))
        <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
            <p class="text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif

    @if ($students->isEmpty())
        <div class="bg-white shadow rounded-lg p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V3a1 1 0 011-1h4a1 1 0 011 1v4"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Cestino vuoto</h3>
            <p class="mt-1 text-sm text-gray-500">Nessuno studente eliminato.</p>
        </div>
    @else
        <div class="bg-white shadow overflow-hidden sm:rounded-lg" x-data="{ confirmingForce: null, confirmText: '' }">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ruolo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Eliminato il</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach ($students as $student)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $student->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $student->email }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ Student::SYSTEM_ROLES[$student->role] ?? $student->role }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $student->deleted_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <form method="POST" action="{{ route('admin.students.restore', $student->id) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="inline-flex items-center px-3 py-1.5 border border-green-300 text-xs font-medium rounded text-green-700 bg-green-50 hover:bg-green-100">
                                        Ripristina
                                    </button>
                                </form>

                                <button type="button"
                                        @click="confirmingForce = '{{ $student->id }}'; confirmText = ''"
                                        class="inline-flex items-center px-3 py-1.5 border border-red-300 text-xs font-medium rounded text-red-700 bg-red-50 hover:bg-red-100">
                                    Elimina definitivamente
                                </button>
                            </td>
                        </tr>

                        <tr x-show="confirmingForce === '{{ $student->id }}'" x-cloak x-transition>
                            <td colspan="5" class="px-6 py-0">
                                <div class="bg-red-50 border-l-4 border-red-400 p-4 my-2 rounded">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <div class="ml-3 flex-1">
                                            <h3 class="text-sm font-medium text-red-800">
                                                Conferma eliminazione definitiva
                                            </h3>
                                            <div class="mt-2 text-sm text-red-700">
                                                <p>
                                                    Stai per eliminare definitivamente
                                                    <strong>{{ $student->name }}</strong> ({{ $student->email }}).
                                                </p>
                                                <p class="mt-2">Questa azione cancellerà anche tutti i suoi dati associati:</p>
                                                <ul class="list-disc list-inside mt-1 ml-2 space-y-0.5">
                                                    <li>Iscrizioni ai corsi</li>
                                                    <li>Tentativi quiz</li>
                                                    <li>Certificati rilasciati</li>
                                                    <li>Conversazioni Minerva</li>
                                                    <li>Note, documenti, progressi moduli</li>
                                                </ul>
                                                <p class="mt-2 font-semibold">
                                                    L'azione è irreversibile e adempie al diritto all'oblio GDPR.
                                                </p>
                                            </div>
                                            <div class="mt-4">
                                                <label class="block text-sm font-medium text-red-800">
                                                    Per confermare, scrivi <code class="bg-red-100 px-1.5 py-0.5 rounded text-red-900">ELIMINA</code> qui sotto:
                                                </label>
                                                <input type="text"
                                                       x-model="confirmText"
                                                       class="mt-1 block w-48 border-red-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                                       placeholder="ELIMINA"
                                                       autocomplete="off">
                                            </div>
                                            <div class="mt-4 flex space-x-2">
                                                <form method="POST"
                                                      action="{{ route('admin.students.force-delete', $student->id) }}"
                                                      class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            :disabled="confirmText !== 'ELIMINA'"
                                                            :class="confirmText === 'ELIMINA' ? 'bg-red-600 hover:bg-red-700 cursor-pointer' : 'bg-red-300 cursor-not-allowed'"
                                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white">
                                                        Sì, elimina definitivamente
                                                    </button>
                                                </form>
                                                <button type="button"
                                                        @click="confirmingForce = null; confirmText = ''"
                                                        class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                                    Annulla
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $students->links() }}
        </div>
    @endif

</div>

<style>[x-cloak]{display:none!important}</style>
@endsection
