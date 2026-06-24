{{-- KaTeX self-hosted (asset locali) condiviso: rende le formule LaTeX del corpo
     lezione ($…$, $$…$$, \(…\), \[…\]) IDENTICO per studente e anteprima docente. --}}
@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/katex/katex.min.css') }}">
@endpush
@push('scripts')
<script defer src="{{ asset('vendor/katex/katex.min.js') }}"></script>
<script defer src="{{ asset('vendor/katex/contrib/auto-render.min.js') }}"></script>
<script>
window.addEventListener('load', function () {
    if (typeof renderMathInElement !== 'function') return;
    document.querySelectorAll('.lesson-body').forEach(function (el) {
        renderMathInElement(el, {
            delimiters: [
                {left: '$$', right: '$$', display: true},
                {left: '$', right: '$', display: false},
                {left: '\\(', right: '\\)', display: false},
                {left: '\\[', right: '\\]', display: true},
            ],
            ignoredClasses: ['note-inline', 'note-teacher', 'note-tab'],
            throwOnError: false,
        });
    });
});
</script>
@endpush
