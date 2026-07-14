@extends('layouts.student')
@section('title', $map->title)
@section('breadcrumb', $course->name . ' / ' . $map->title)

@push('styles')
<style>
    .cm-viewer { background:#FFFFFF; border:1px solid #D1D5DB; border-radius:8px;
                 width: 100%; height: 74vh; min-height: 560px; max-height: 820px; }
    .vis-manipulation, .vis-edit-mode { display: none !important; }
</style>
@endpush

@section('content')
<div style="max-width:1100px;">
    <a href="{{ route('student.course.concept-maps.index', $course->slug) }}" style="color:#8A9696; font-size:0.85rem;">&larr; Mappe del corso</a>
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px; flex-wrap:wrap; margin-top:4px;">
        <div style="flex:1;">
            <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F;">{{ $map->title }}</h1>
            @if($map->description)
                <p style="font-size:0.85rem; color:#4A5252; margin-top:6px;">{{ $map->description }}</p>
            @endif
            <div style="font-size:0.72rem; color:#8A9696; margin-top:6px;">
                {{ count($map->data['nodes'] ?? []) }} concetti &middot; {{ count($map->data['edges'] ?? []) }} relazioni
            </div>
        </div>
        <div>
            @if($hasFork)
                <a href="{{ route('student.course.concept-map.my', [$course->slug, $map->id]) }}"
                   style="padding:8px 16px; background:#E28A53; color:white;
                          border-radius:8px; text-decoration:none; font-size:0.85rem; font-weight:600;">
                    Apri la mia versione &rarr;
                </a>
            @else
                <form action="{{ route('student.course.concept-map.fork', [$course->slug, $map->id]) }}" method="POST">
                    @csrf
                    <button type="submit"
                            style="padding:8px 16px; background:#E28A53; color:white;
                                   border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                        🍴 Personalizza la tua versione
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div style="padding:10px 14px; background:#D1FAE5; color:#059669; border-radius:6px; font-size:0.85rem; margin-top:10px;">{{ session('success') }}</div>
    @endif

    <p style="font-size:0.75rem; color:#8A9696; margin-top:14px;">
        Suggerimento: clicca su un concetto per visualizzarne la descrizione e seguirne il collegamento (modulo, materiale o link esterno).
    </p>

    <div id="cm-viewer" class="cm-viewer" style="margin-top:12px;"></div>

    <div x-data="{ open:false, node:null }" x-on:cm-node-click.window="node=$event.detail; open=true"
         x-show="open" x-cloak
         style="position:fixed; right:24px; bottom:24px; width:340px;
                background:white; border:1px solid #D1D5DB; border-left:4px solid #55B1AE;
                border-radius:8px; padding:16px; box-shadow:0 6px 24px rgba(0,0,0,0.18); z-index:200;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
            <h3 style="font-size:0.95rem; font-weight:700; color:#3D8B88;" x-text="node?.label"></h3>
            <button type="button" @click="open=false" style="background:transparent; border:none; color:#8A9696; cursor:pointer; font-size:1.1rem; line-height:1;">&times;</button>
        </div>
        <p x-show="node?.description" style="font-size:0.82rem; color:#4A5252; margin-top:8px;" x-text="node?.description"></p>
        <div style="margin-top:10px;">
            <template x-if="node?.link_type === 'module' && node?.link_module_id">
                <a :href="'/learn/course/{{ $course->slug }}/module/' + node.link_module_id"
                   style="display:inline-block; padding:6px 12px; background:#55B1AE; color:white; border-radius:6px; text-decoration:none; font-size:0.78rem; font-weight:600;">
                    Apri modulo &rarr;
                </a>
            </template>
            <template x-if="node?.link_type === 'material' && node?.link_material_id">
                <a :href="'/learn/material/' + node.link_material_id + '/canvas'" target="_blank"
                   style="display:inline-block; padding:6px 12px; background:#55B1AE; color:white; border-radius:6px; text-decoration:none; font-size:0.78rem; font-weight:600;">
                    Apri materiale &rarr;
                </a>
            </template>
            <template x-if="node?.link_type === 'url' && node?.link_url">
                <a :href="node.link_url" target="_blank" rel="noopener"
                   style="display:inline-block; padding:6px 12px; background:#55B1AE; color:white; border-radius:6px; text-decoration:none; font-size:0.78rem; font-weight:600;">
                    Apri link esterno &rarr;
                </a>
            </template>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/vis-network@9.1.9/standalone/umd/vis-network.min.js"></script>
<script src="/js/concept-map-editor.js?v={{ filemtime(public_path('js/concept-map-editor.js')) }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const initial = @json($map->data ?: ['nodes' => [], 'edges' => []]);
        window.NosciteConceptMap.createViewer('#cm-viewer', initial, {
            onNodeClick: function (node) {
                window.dispatchEvent(new CustomEvent('cm-node-click', { detail: node }));
            },
        });
    });
</script>
@endpush
@endsection
