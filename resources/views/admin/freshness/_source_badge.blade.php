@php($isStudent = ($source ?? 'instructor') === 'student')
<span style="font-size:0.66rem; font-weight:700; text-transform:uppercase; letter-spacing:0.07em; padding:2px 9px; border-radius:10px;
    {{ $isStudent
        ? 'background:#3B2E5A; color:#EDE7F6; border:1px solid #3B2E5A;'
        : 'background:#0E3F3D; color:#D6F0EE; border:1px solid #0E3F3D;' }}">
    {{ $isStudent ? 'STUDENTE' : 'FORMATORE' }}
</span>
