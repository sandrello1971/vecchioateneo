@extends('layouts.admin')

@section('title', "Sezioni del manuale: {$material->title}")

@section('content')
<div style="max-width:1100px; margin:0 auto;">

    @if(session('success'))
    <div style="padding:10px 14px; background:rgba(85,177,174,0.12);
                border:1px solid rgba(85,177,174,0.4); border-radius:8px;
                color:#3A8C89; margin-bottom:14px; font-size:0.85rem;">
        ✅ {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div style="padding:10px 14px; background:rgba(226,82,82,0.12);
                border:1px solid rgba(226,82,82,0.4); border-radius:8px;
                color:#C52A2A; margin-bottom:14px; font-size:0.85rem;">
        ⚠️ {{ session('error') }}
    </div>
    @endif

    <div style="margin-bottom:16px; font-size:0.85rem; color:#8A9696;">
        <a href="{{ route('admin.courses.edit', $course->id) }}"
           style="color:#3A8C89; text-decoration:none;">← Corso: {{ $course->name }}</a>
    </div>

    <div style="background:linear-gradient(135deg, rgba(226,138,83,0.08), rgba(226,138,83,0.12));
                border:1px solid rgba(226,138,83,0.3);
                border-radius:12px; padding:20px; margin-bottom:20px;">

        <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
            <div style="font-size:1.4rem;">🎓</div>
            <div style="font-weight:700; color:#1A1F1F; font-size:1.1rem;">
                Mapping sezioni → moduli
            </div>
            <div style="margin-left:auto; padding:4px 12px;
                        background:rgba(226,138,83,0.2); color:#D87840;
                        border-radius:12px; font-size:0.7rem; font-weight:700;">
                {{ $material->title }}
            </div>
        </div>
        <p style="font-size:0.8rem; color:#5A6464; margin:0;">
            Ogni sezione del manuale può essere associata a un modulo del corso.
            Le sezioni associate appaiono nel box "Sezioni del Manuale Formatore"
            del modulo, visibile agli instructor. Le modifiche manuali vengono
            preservate ai successivi upload del manuale.
        </p>
    </div>

    <form method="POST"
          action="{{ route('admin.courses.instructor-materials.sections.update', [$course->id, $material->id]) }}">
        @csrf
        @method('PUT')

        <div style="background:white; border:1px solid #E8F5F5; border-radius:12px;
                    padding:20px; margin-bottom:20px;">

            @php
                $autoCount = $sections->where('module_assigned_manually', false)->whereNotNull('module_id')->count();
                $manualCount = $sections->where('module_assigned_manually', true)->count();
                $orphanCount = $sections->whereNull('module_id')->count();
            @endphp
            <div style="display:flex; gap:14px; margin-bottom:14px; font-size:0.8rem; flex-wrap:wrap;">
                <span><strong>{{ $sections->count() }}</strong> sezioni totali</span>
                <span style="color:#3A8C89;">🤖 <strong>{{ $autoCount }}</strong> auto-mapped</span>
                <span style="color:#D87840;">✋ <strong>{{ $manualCount }}</strong> override manuali</span>
                <span style="color:#8A9696;">⚪ <strong>{{ $orphanCount }}</strong> orfane (nessun modulo)</span>
            </div>

            @if($sections->isEmpty())
            <div style="padding:20px; text-align:center; color:#8A9696; font-size:0.9rem;">
                Nessuna sezione: il manuale non è stato ancora splittato.
                Caricalo o sostituiscilo per generare le sezioni.
            </div>
            @else
            <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                <thead>
                    <tr style="background:#F5F7F7; text-align:left;">
                        <th style="padding:10px; font-weight:600; color:#5A6464; width:60px;">#</th>
                        <th style="padding:10px; font-weight:600; color:#5A6464;">Titolo sezione</th>
                        <th style="padding:10px; font-weight:600; color:#5A6464; width:80px;">Tipo</th>
                        <th style="padding:10px; font-weight:600; color:#5A6464; width:280px;">Modulo associato</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sections as $section)
                    <tr style="border-top:1px solid #E8F5F5;">
                        <td style="padding:10px; color:#8A9696;">
                            {{ $section->sort_order }}
                        </td>
                        <td style="padding:10px;">
                            <div style="font-weight:500; color:#1A1F1F;">
                                {{ $section->title }}
                            </div>
                            <div style="font-size:0.7rem; color:#8A9696; margin-top:2px;">
                                #{{ $section->anchor }} · H{{ $section->heading_level }}
                            </div>
                        </td>
                        <td style="padding:10px;">
                            @if($section->module_assigned_manually)
                                <span style="padding:3px 8px; background:rgba(226,138,83,0.15);
                                             color:#D87840; border-radius:8px; font-size:0.7rem; font-weight:700;">
                                    ✋ MANUALE
                                </span>
                            @elseif($section->module_id)
                                <span style="padding:3px 8px; background:rgba(85,177,174,0.15);
                                             color:#3A8C89; border-radius:8px; font-size:0.7rem; font-weight:700;">
                                    🤖 AUTO
                                </span>
                            @else
                                <span style="padding:3px 8px; background:rgba(138,150,150,0.15);
                                             color:#8A9696; border-radius:8px; font-size:0.7rem; font-weight:700;">
                                    ⚪ NESSUNO
                                </span>
                            @endif
                        </td>
                        <td style="padding:10px;">
                            <select name="assignments[{{ $section->id }}]"
                                    style="width:100%; padding:6px 10px; border:1px solid #E8F5F5;
                                           border-radius:6px; font-size:0.8rem;">
                                <option value="">— Nessun modulo —</option>
                                @foreach($modules as $module)
                                <option value="{{ $module->id }}"
                                    {{ $section->module_id === $module->id ? 'selected' : '' }}>
                                    [{{ $module->sort_order }}] {{ $module->title }}
                                </option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        <div style="display:flex; gap:10px; justify-content:space-between; align-items:center; flex-wrap:wrap;">
            <a href="{{ route('admin.courses.edit', $course->id) }}"
               style="padding:10px 18px; color:#5A6464; text-decoration:none;
                      font-size:0.85rem; font-weight:600;">
                ← Torna al corso
            </a>

            <div style="display:flex; gap:10px;">
                <button type="submit" form="reset-form"
                        onclick="return confirm('Sicuro? Tutti gli override manuali verranno cancellati e il mapping verrà ricalcolato dall\'algoritmo automatico.');"
                        style="padding:10px 18px; background:white; color:#C52A2A;
                               border:1px solid #E28282; border-radius:8px;
                               font-size:0.8rem; font-weight:600; cursor:pointer;">
                    ↻ Reset all'auto-mapping
                </button>

                @if(!$sections->isEmpty())
                <button type="submit"
                        style="padding:10px 24px; background:#55B1AE; color:white;
                               border:none; border-radius:8px; font-size:0.85rem;
                               font-weight:600; cursor:pointer;">
                    💾 Salva modifiche
                </button>
                @endif
            </div>
        </div>
    </form>

    <form id="reset-form" method="POST" style="display:none;"
          action="{{ route('admin.courses.instructor-materials.sections.reset', [$course->id, $material->id]) }}">
        @csrf
    </form>

</div>
@endsection
