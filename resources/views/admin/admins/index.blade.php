@extends('layouts.admin')
@section('title', 'Amministratori')
@section('content')

<div style="max-width:1000px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Amministratori della piattaforma</h2>
    </div>

    @if(session('success'))
    <div style="background:#E8F5F5; border-left:4px solid #55B1AE; padding:12px 16px; border-radius:8px; margin-bottom:16px; color:#3A8C89; font-size:0.875rem;">
        ✓ {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div style="background:#fff3ec; border-left:4px solid #E28A53; padding:12px 16px; border-radius:8px; margin-bottom:16px; color:#c97a45; font-size:0.875rem;">
        ⚠ {{ session('error') }}
    </div>
    @endif
    @if($errors->any())
    <div style="background:#fff3ec; border-left:4px solid #E28A53; padding:12px 16px; border-radius:8px; margin-bottom:16px; color:#c97a45; font-size:0.875rem;">
        @foreach($errors->all() as $e)
            <div>⚠ {{ $e }}</div>
        @endforeach
    </div>
    @endif

    {{-- LISTA --}}
    <div style="background:white; border-radius:10px; overflow:hidden; margin-bottom:20px;">
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:#F5F7F7;">
                    <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Nome</th>
                    <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Email</th>
                    <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Stato</th>
                    <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Firma certificati</th>
                    <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Creato</th>
                    <th style="padding:12px 16px; text-align:right; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Azioni</th>
                </tr>
            </thead>

            {{-- Un <tbody> per ogni admin con il proprio x-data: lo stato
                 `panel` è strettamente per-riga. Il pannello expand sotto
                 la riga vede solo questo scope, mai quello di altre righe.
                 Più <tbody> dentro una <table> sono HTML valido. --}}
            @forelse($admins as $admin)
            @php $isSelf = strtolower($admin->email) === strtolower((string) session('admin_email')); @endphp
            <tbody x-data="{ panel: null }" style="border-top:1px solid #F5F7F7;">
                <tr>
                    <td style="padding:12px 16px; font-weight:600; color:#1A1F1F; font-size:0.875rem;">
                        {{ $admin->name }}
                        @if($isSelf)
                            <span style="margin-left:6px; padding:2px 6px; background:rgba(85,177,174,0.15); color:#3A8C89; border-radius:4px; font-size:0.7rem; font-weight:600;">tu</span>
                        @endif
                    </td>
                    <td style="padding:12px 16px; color:#4A5252; font-size:0.875rem;">{{ $admin->email }}</td>
                    <td style="padding:12px 16px;">
                        <span style="padding:3px 8px; border-radius:4px; font-size:0.75rem; font-weight:600;
                            background:{{ $admin->is_active ? '#E8F5F5' : '#F5F7F7' }};
                            color:{{ $admin->is_active ? '#3A8C89' : '#8A9696' }};">
                            {{ $admin->is_active ? 'Attivo' : 'Disattivato' }}
                        </span>
                    </td>
                    <td style="padding:12px 16px;">
                        <span style="padding:3px 8px; border-radius:4px; font-size:0.75rem; font-weight:600;
                            background:{{ $admin->can_sign_certificates ? '#E8F5F5' : '#F5F7F7' }};
                            color:{{ $admin->can_sign_certificates ? '#3A8C89' : '#8A9696' }};">
                            {{ $admin->can_sign_certificates ? 'Abilitato' : 'No' }}
                        </span>
                    </td>
                    <td style="padding:12px 16px; color:#8A9696; font-size:0.8rem;">
                        {{ $admin->created_at?->format('d/m/Y') }}
                    </td>
                    <td style="padding:12px 16px; text-align:right; white-space:nowrap;">
                        <button type="button"
                                @click="panel = panel === 'profile' ? null : 'profile'"
                                :style="panel === 'profile' ? 'background:#E8F5F5; color:#3A8C89;' : 'background:transparent; color:#55B1AE;'"
                                style="padding:5px 10px; border:1px solid #55B1AE; border-radius:4px; font-size:0.75rem; cursor:pointer; margin-right:4px;">
                            Modifica
                        </button>
                        <button type="button"
                                @click="panel = panel === 'password' ? null : 'password'"
                                :style="panel === 'password' ? 'background:#fff3ec; color:#c97a45;' : 'background:transparent; color:#E28A53;'"
                                style="padding:5px 10px; border:1px solid #E28A53; border-radius:4px; font-size:0.75rem; cursor:pointer; margin-right:4px;">
                            Password
                        </button>
                        <button type="button"
                                @click="panel = panel === 'signature' ? null : 'signature'"
                                :style="panel === 'signature' ? 'background:#E8F5F5; color:#3A8C89;' : 'background:transparent; color:#55B1AE;'"
                                style="padding:5px 10px; border:1px solid #55B1AE; border-radius:4px; font-size:0.75rem; cursor:pointer; margin-right:4px;">
                            Firma
                        </button>
                        <button type="button"
                                @click="panel = panel === 'toggle' ? null : 'toggle'"
                                style="padding:5px 10px; border:1px solid {{ $admin->is_active ? '#E28A53' : '#55B1AE' }}; background:transparent; color:{{ $admin->is_active ? '#E28A53' : '#3A8C89' }}; border-radius:4px; font-size:0.75rem; cursor:pointer;">
                            {{ $admin->is_active ? 'Disattiva' : 'Riattiva' }}
                        </button>
                    </td>
                </tr>

                {{-- Pannello expand sotto la riga, scoped allo stesso tbody.
                     I form sono renderizzati server-side con i valori di
                     QUESTO $admin: niente JS che ripopola i campi al click. --}}
                <tr x-show="panel !== null" x-cloak>
                    <td colspan="6" style="background:#FAFBFB; padding:16px 20px; border-top:1px dashed #E8F5F5;">

                        {{-- Profilo --}}
                        <div x-show="panel === 'profile'" x-cloak>
                            <div style="font-size:0.8rem; font-weight:700; color:#4A5252; margin-bottom:10px;">Modifica profilo</div>
                            <form method="POST" action="{{ route('admin.admins.update', $admin) }}"
                                  style="display:grid; grid-template-columns:1fr 1fr auto auto; gap:10px; align-items:end;">
                                @csrf @method('PATCH')
                                <div>
                                    <label style="font-size:0.7rem; color:#8A9696;">Nome</label>
                                    <input type="text" name="name" value="{{ $admin->name }}" required maxlength="255"
                                           style="width:100%; padding:7px; border:1px solid #C8D0D0; border-radius:5px; font-size:0.85rem;">
                                </div>
                                <div>
                                    <label style="font-size:0.7rem; color:#8A9696;">Email</label>
                                    <input type="email" name="email" value="{{ $admin->email }}" required
                                           style="width:100%; padding:7px; border:1px solid #C8D0D0; border-radius:5px; font-size:0.85rem;">
                                </div>
                                <button type="button" @click="panel = null"
                                        style="padding:7px 12px; background:transparent; color:#8A9696; border:1px solid #C8D0D0; border-radius:5px; font-size:0.8rem; cursor:pointer;">
                                    Annulla
                                </button>
                                <button type="submit"
                                        style="padding:7px 14px; background:#55B1AE; color:white; border:none; border-radius:5px; font-size:0.8rem; font-weight:600; cursor:pointer;">
                                    Salva
                                </button>
                            </form>
                        </div>

                        {{-- Password --}}
                        <div x-show="panel === 'password'" x-cloak>
                            <div style="font-size:0.8rem; font-weight:700; color:#4A5252; margin-bottom:10px;">
                                Cambia password — <span style="color:#1A1F1F;">{{ $admin->email }}</span>
                            </div>
                            <form method="POST" action="{{ route('admin.admins.password', $admin) }}"
                                  style="display:grid; grid-template-columns:1fr 1fr auto auto; gap:10px; align-items:end;">
                                @csrf @method('PATCH')
                                <div>
                                    <label style="font-size:0.7rem; color:#8A9696;">Nuova password (min 12)</label>
                                    <input type="password" name="password" required minlength="12" autocomplete="new-password"
                                           style="width:100%; padding:7px; border:1px solid #C8D0D0; border-radius:5px; font-size:0.85rem;">
                                </div>
                                <div>
                                    <label style="font-size:0.7rem; color:#8A9696;">Conferma</label>
                                    <input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password"
                                           style="width:100%; padding:7px; border:1px solid #C8D0D0; border-radius:5px; font-size:0.85rem;">
                                </div>
                                <button type="button" @click="panel = null"
                                        style="padding:7px 12px; background:transparent; color:#8A9696; border:1px solid #C8D0D0; border-radius:5px; font-size:0.8rem; cursor:pointer;">
                                    Annulla
                                </button>
                                <button type="submit"
                                        style="padding:7px 14px; background:#E28A53; color:white; border:none; border-radius:5px; font-size:0.8rem; font-weight:600; cursor:pointer;">
                                    Cambia password
                                </button>
                            </form>
                        </div>

                        {{-- Toggle firma certificati --}}
                        <div x-show="panel === 'signature'" x-cloak>
                            <div style="font-size:0.8rem; color:#4A5252; margin-bottom:10px;">
                                @if($admin->can_sign_certificates)
                                    Revocare a <strong>{{ $admin->email }}</strong> l'abilitazione a firmare i certificati?
                                    <br><span style="color:#c97a45; font-size:0.75rem;">⚠ Deve restare almeno un firmatario: se è l'unico, l'operazione viene bloccata server-side (anti-lockout).</span>
                                @else
                                    Abilitare <strong>{{ $admin->email }}</strong> alla firma dei certificati emessi dalla piattaforma?
                                @endif
                            </div>
                            <form method="POST" action="{{ route('admin.admins.signature', $admin) }}"
                                  style="display:flex; gap:10px;">
                                @csrf @method('PATCH')
                                <button type="button" @click="panel = null"
                                        style="padding:7px 14px; background:transparent; color:#8A9696; border:1px solid #C8D0D0; border-radius:5px; font-size:0.8rem; cursor:pointer;">
                                    Annulla
                                </button>
                                <button type="submit"
                                        style="padding:7px 14px; background:{{ $admin->can_sign_certificates ? '#E28A53' : '#55B1AE' }}; color:white; border:none; border-radius:5px; font-size:0.8rem; font-weight:600; cursor:pointer;">
                                    {{ $admin->can_sign_certificates ? 'Revoca firma' : 'Abilita firma' }}
                                </button>
                            </form>
                        </div>

                        {{-- Toggle attivo/disattivo --}}
                        <div x-show="panel === 'toggle'" x-cloak>
                            <div style="font-size:0.8rem; color:#4A5252; margin-bottom:10px;">
                                @if($admin->is_active)
                                    Disattivare <strong>{{ $admin->email }}</strong>? Non potrà più accedere.
                                    @if($isSelf)
                                        <br><span style="color:#c97a45; font-size:0.75rem;">⚠ Stai disattivando il tuo account. Se sei l'unico admin attivo, l'operazione viene bloccata server-side (anti-lockout).</span>
                                    @endif
                                @else
                                    Riattivare <strong>{{ $admin->email }}</strong>? Potrà tornare ad accedere.
                                @endif
                            </div>
                            <form method="POST" action="{{ route('admin.admins.toggle', $admin) }}"
                                  style="display:flex; gap:10px;">
                                @csrf @method('PATCH')
                                <button type="button" @click="panel = null"
                                        style="padding:7px 14px; background:transparent; color:#8A9696; border:1px solid #C8D0D0; border-radius:5px; font-size:0.8rem; cursor:pointer;">
                                    Annulla
                                </button>
                                <button type="submit"
                                        style="padding:7px 14px; background:{{ $admin->is_active ? '#E28A53' : '#55B1AE' }}; color:white; border:none; border-radius:5px; font-size:0.8rem; font-weight:600; cursor:pointer;">
                                    Conferma {{ $admin->is_active ? 'disattivazione' : 'riattivazione' }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            </tbody>
            @empty
            <tbody>
                <tr>
                    <td colspan="6" style="padding:40px; text-align:center; color:#8A9696;">
                        Nessun amministratore. Crea il primo con:
                        <code style="background:#F5F7F7; padding:2px 6px; border-radius:4px;">php artisan atheneum:admin-create</code>
                    </td>
                </tr>
            </tbody>
            @endforelse
        </table>
    </div>

    {{-- FORM CREAZIONE --}}
    <div style="background:white; border-radius:10px; padding:20px;">
        <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:14px;">Nuovo amministratore</h3>
        <form method="POST" action="{{ route('admin.admins.store') }}" style="display:grid; grid-template-columns:1fr 1fr 1fr auto; gap:10px; align-items:end;">
            @csrf
            <div>
                <label style="font-size:0.7rem; color:#8A9696;">Nome *</label>
                <input type="text" name="name" required maxlength="255" value="{{ old('name') }}"
                       style="width:100%; padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
            </div>
            <div>
                <label style="font-size:0.7rem; color:#8A9696;">Email *</label>
                <input type="email" name="email" required value="{{ old('email') }}"
                       style="width:100%; padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
            </div>
            <div>
                <label style="font-size:0.7rem; color:#8A9696;">Password * (min 12)</label>
                <input type="password" name="password" required minlength="12" autocomplete="new-password"
                       style="width:100%; padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.85rem;">
            </div>
            <button type="submit" style="padding:8px 18px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.85rem; font-weight:700; cursor:pointer;">
                Crea admin
            </button>
        </form>
    </div>

    <div style="margin-top:16px; padding:12px 16px; background:#FFFBEB; border:1px solid rgba(226,138,83,0.3); border-radius:8px; font-size:0.78rem; color:#5A6464;">
        <strong>Nota.</strong> La credenziale <code style="background:#F5F7F7; padding:1px 5px; border-radius:3px;">ADMIN_EMAIL</code> /
        <code style="background:#F5F7F7; padding:1px 5px; border-radius:3px;">ADMIN_PASSWORD_HASH</code> nel <code style="background:#F5F7F7; padding:1px 5px; border-radius:3px;">.env</code>
        resta valida come accesso di emergenza (<em>break-glass</em>), anche se il relativo admin DB è disattivato.
    </div>
</div>

@endsection
