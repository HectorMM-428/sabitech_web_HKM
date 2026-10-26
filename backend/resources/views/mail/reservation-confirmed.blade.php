<x-mail::message>
# Tu plaza está reservada

Hola, {{ $name }}:

Tienes plaza en la charla **{{ $title }}**, de {{ $speaker }}.

<x-mail::table>
| | |
| :--- | :--- |
| **Fecha** | {{ $date }} |
| **Horario** | {{ $time }} (hora peninsular) |
| **Sala** | {{ $room }}@if($location) · {{ $location }}@endif |
| **Referencia** | {{ $reference }} |
</x-mail::table>

**Para entrar:** el día de la charla abre «Mis reservas» y pulsa «Mostrar acreditación». El código QR se renueva cada 30 segundos; enséñalo al personal de acceso. Si no puedes usar el móvil, di tu nombre o la referencia en la entrada.

<x-mail::button :url="$reservationsUrl">
Ver mis reservas
</x-mail::button>

Si finalmente no puedes asistir, cancela la reserva antes de que empiece la charla para que otra persona pueda ocupar la plaza.

Este correo confirma tu reserva; **no registra tu asistencia**. La asistencia se registra al validar tu acreditación en la entrada.

Jornadas tecnológicas SABITECH
</x-mail::message>
