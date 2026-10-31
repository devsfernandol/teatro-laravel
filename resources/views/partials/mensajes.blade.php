@if (session('exito'))
    <div class="alerta alerta--exito" role="status">{{ session('exito') }}</div>
@endif
@if (session('aviso'))
    <div class="alerta alerta--aviso" role="status">{{ session('aviso') }}</div>
@endif
@if ($errors->any())
    <div class="alerta alerta--error" role="alert">
        @if ($errors->count() === 1)
            {{ $errors->first() }}
        @else
            <p>Revise los siguientes datos:</p>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
