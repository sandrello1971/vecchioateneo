@extends('layouts.student')
@section('title', 'Knowledge Base Formatore')

@section('content')
<div style="max-width:1200px; margin:0 auto;">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1 style="font-size:1.5rem; margin:0;">📓 Knowledge Base</h1>
        <a href="{{ route('student.knowledge_base.create') }}"
           style="padding:10px 20px; background:#55B1AE; color:white; border-radius:8px;
                  text-decoration:none; font-size:0.85rem; font-weight:600;">
            + Nuova nota
        </a>
    </div>

    @if(session('success'))
    <div style="padding:10px 14px; background:rgba(85,177,174,0.12); border:1px solid rgba(85,177,174,0.4);
                border-radius:8px; color:#3A8C89; margin-bottom:14px; font-size:0.85rem;">
        ✅ {{ session('success') }}
    </div>
    @endif

    <form method="GET"
          style="background:white; border-radius:12px; padding:16px; margin-bottom:18px;
                 display:grid; grid-template-columns:2fr 1fr 1fr 1fr 1fr auto auto; gap:10px; align-items:end;">
        <div>
            <label style="font-size:0.7rem; color:#8A9696;">Cerca</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="parole nel titolo o nel testo"
                   style="width:100%; padding:7px; border:1px solid #E8F5F5; border-radius:5px; font-size:0.85rem;">
        </div>
        <div>
            <label style="font-size:0.7rem; color:#8A9696;">Tipo</label>
            <select name="kind" style="width:100%; padding:7px; border:1px solid #E8F5F5; border-radius:5px; font-size:0.8rem;">
                <option value="">Tutti</option>
                @foreach($kinds as $k => $info)
                <option value="{{ $k }}" {{ ($filters['kind'] ?? null) === $k ? 'selected' : '' }}>
                    {{ $info['emoji'] }} {{ $info['label'] }}
                </option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:0.7rem; color:#8A9696;">Corso</label>
            <select name="course_id" style="width:100%; padding:7px; border:1px solid #E8F5F5; border-radius:5px; font-size:0.8rem;">
                <option value="">Tutti</option>
                @foreach($courses as $c)
                <option value="{{ $c->id }}" {{ ($filters['course_id'] ?? null) === $c->id ? 'selected' : '' }}>
                    {{ $c->name }}
                </option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:0.7rem; color:#8A9696;">Tag</label>
            <select name="tag" style="width:100%; padding:7px; border:1px solid #E8F5F5; border-radius:5px; font-size:0.8rem;">
                <option value="">Tutti</option>
                @foreach($allTags as $t)
                <option value="{{ $t }}" {{ ($filters['tag'] ?? null) === $t ? 'selected' : '' }}>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.78rem; padding-top:14px;">
                <input type="checkbox" name="mine_only" value="1" {{ !empty($filters['mine_only']) ? 'checked' : '' }}>
                Solo le mie
            </label>
        </div>
        <button type="submit" style="padding:8px 14px; background:#55B1AE; color:white; border:none; border-radius:5px; cursor:pointer; font-size:0.8rem;">Filtra</button>
        <a href="{{ route('student.knowledge_base.index') }}"
           style="padding:8px 14px; color:#8A9696; text-decoration:none; font-size:0.8rem;">Reset</a>
    </form>

    @if($notes->isEmpty())
    <div style="background:white; border-radius:12px; padding:40px; text-align:center; color:#8A9696;">
        Nessuna nota corrisponde ai filtri.
        <br><a href="{{ route('student.knowledge_base.create') }}" style="color:#3A8C89;">Crea la prima nota</a>
    </div>
    @else
    <div style="display:flex; flex-direction:column; gap:10px;">
        @foreach($notes as $note)
        <div style="background:white; border-radius:10px; padding:16px;
                    border-left:4px solid {{ $note->is_shared ? '#E28A53' : '#55B1AE' }};">
            <div style="display:flex; gap:10px; align-items:flex-start;">
                <div style="font-size:1.4rem;">{{ $note->emoji }}</div>
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:700; color:#1A1F1F; font-size:0.95rem; margin-bottom:3px;">
                        {{ $note->title }}
                    </div>
                    <div style="font-size:0.7rem; color:#8A9696; display:flex; gap:8px; flex-wrap:wrap; margin-bottom:6px;">
                        <span>{{ $note->kind_label }}</span>
                        <span>·</span>
                        <span>{{ $note->course->name ?? '?' }}</span>
                        @if($note->module)
                        <span>·</span>
                        <span>📍 {{ $note->module->title }}</span>
                        @endif
                        @if($note->section)
                        <span>·</span>
                        <span>📄 {{ Str::limit($note->section->title, 40) }}</span>
                        @endif
                        @if($note->is_shared)
                        <span>·</span>
                        <span style="color:#D87840;">🔁 Condivisa da {{ $note->instructor->email ?? '?' }}</span>
                        @endif
                    </div>
                    <div style="color:#5A6464; font-size:0.85rem; line-height:1.5; max-height:60px; overflow:hidden;">
                        {{ Str::limit(strip_tags($note->body_markdown), 200) }}
                    </div>
                    @if($note->tags && count($note->tags) > 0)
                    <div style="display:flex; gap:5px; margin-top:8px; flex-wrap:wrap;">
                        @foreach($note->tags as $t)
                        <span style="background:#E8F5F5; padding:2px 8px; border-radius:8px; font-size:0.7rem; color:#3A8C89;">#{{ $t }}</span>
                        @endforeach
                    </div>
                    @endif
                </div>
                <div style="display:flex; flex-direction:column; gap:5px; flex-shrink:0;">
                    @if($note->instructor_id === session('student_id'))
                    <a href="{{ route('student.knowledge_base.edit', $note->id) }}"
                       style="padding:5px 10px; background:#F5F7F7; color:#3A8C89; border-radius:4px; text-decoration:none; font-size:0.7rem;">Modifica</a>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div style="margin-top:18px;">
        {{ $notes->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
