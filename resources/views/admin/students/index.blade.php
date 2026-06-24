@extends('layouts.admin')
@section('title', 'Studenti')
@section('content')

@php $trashedCount = \App\Models\Student::onlyTrashed()->count(); @endphp

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Gestione Studenti</h2>
    <div style="display:flex; gap:10px; align-items:center;">
        @if ($trashedCount > 0)
            <a href="{{ route('admin.students.trashed') }}"
               style="padding:8px 16px; background:white; border:1px solid #E5E7E7; color:#4A5252; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                🗑 Studenti eliminati
                <span style="background:#F5F7F7; color:#1A1F1F; padding:1px 8px; border-radius:10px; font-size:0.75rem;">{{ $trashedCount }}</span>
            </a>
        @endif
        <a href="/admin/students/create" style="padding:8px 20px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">+ Nuovo studente</a>
    </div>
</div>

@if (session('success'))
    <div style="background:#E8F5E9; border-left:4px solid #4CAF50; padding:12px 16px; margin-bottom:16px; border-radius:4px; color:#2E7D32; font-size:0.875rem;">
        {{ session('success') }}
    </div>
@endif

<div style="background:white; border-radius:10px; overflow:hidden;">
    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="background:#F5F7F7;">
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Studente</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Azienda</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Ruolo sistema</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Corsi</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Ultimo accesso</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Stato</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Azioni</th>
            </tr>
        </thead>
        <tbody>
            @foreach($students as $student)
            <tr style="border-bottom:1px solid #F5F7F7;">
                <td style="padding:12px 16px;">
                    <div style="font-weight:600; color:#1A1F1F; font-size:0.875rem;">{{ $student->name }}</div>
                    <div style="color:#8A9696; font-size:0.75rem;">{{ $student->email }}</div>
                </td>
                <td style="padding:12px 16px; color:#4A5252; font-size:0.875rem;">{{ $student->company ?? '—' }}</td>
                <td style="padding:12px 16px;">
                    @if($student->role === 'admin')
                        <span style="padding:3px 10px; background:rgba(197,42,42,0.15); color:#C52A2A; border-radius:10px; font-size:0.7rem; font-weight:700;">ADMIN</span>
                    @elseif($student->isInstructor())
                        <span style="padding:3px 10px; background:rgba(226,138,83,0.15); color:#D87840; border-radius:10px; font-size:0.7rem; font-weight:700;">FORMATORE</span>
                    @else
                        <span style="color:#8A9696; font-size:0.75rem;">Studente</span>
                    @endif
                </td>
                <td style="padding:12px 16px;">
                    <div style="display:flex; flex-wrap:wrap; gap:4px;">
                        @foreach($student->courses()->wherePivot('is_active',true)->get() as $c)
                        <span style="padding:2px 6px; background:#E8F5F5; color:#3A8C89; border-radius:4px; font-size:0.7rem; font-weight:600;">{{ $c->name }}</span>
                        @endforeach
                    </div>
                </td>
                <td style="padding:12px 16px; color:#8A9696; font-size:0.8rem;">
                    {{ $student->last_login_at ? $student->last_login_at->diffForHumans() : 'Mai' }}
                </td>
                <td style="padding:12px 16px;">
                    <span style="padding:3px 8px; border-radius:4px; font-size:0.75rem; font-weight:600;
                        background:{{ $student->is_active ? '#E8F5F5' : '#F5F7F7' }};
                        color:{{ $student->is_active ? '#3A8C89' : '#8A9696' }};">
                        {{ $student->is_active ? 'Attivo' : 'Inattivo' }}
                    </span>
                </td>
                <td style="padding:12px 16px;">
                    <div style="display:flex; gap:8px; align-items:center;">
                        <a href="/admin/students/{{ $student->id }}" style="font-size:0.8rem; color:#55B1AE;">Dettaglio</a>
                        <a href="/admin/students/{{ $student->id }}/edit" style="font-size:0.8rem; color:#8A9696;">Modifica</a>
                        <form method="POST" action="{{ route('admin.students.destroy', $student->id) }}" style="display:inline;"
                              onsubmit="return confirm('Spostare {{ addslashes($student->name) }} nel cestino? Potrai ripristinarlo in seguito.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="background:none; border:none; padding:0; font-size:0.8rem; color:#C52A2A; cursor:pointer;">Cestina</button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $students->links() }}</div>

@endsection
