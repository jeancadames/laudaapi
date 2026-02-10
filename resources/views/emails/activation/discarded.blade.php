@extends('emails.layouts.lauda')

@section('content')
<span class="preheader" style="display:none; visibility:hidden; opacity:0; color:transparent; height:0; width:0; overflow:hidden;">
    Tu solicitud de activación expiró.
</span>

<p class="text" style="margin:0 0 16px 0;">
    Hola {{ $activation->name }},
</p>

<p class="text" style="margin:0 0 16px 0;">
    El enlace de activación expiró y tu solicitud fue cerrada automáticamente.
</p>

<p class="text" style="margin:0 0 16px 0;">
    Si todavía deseas activar tu prueba, por favor envía una nueva solicitud desde nuestro sitio.
</p>

<p class="text" style="margin:32px 0 0 0;">
    — Equipo LaudaAPI
</p>
@endsection