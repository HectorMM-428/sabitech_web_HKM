<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de confirmación de una reserva (R13): charla, fecha, horario, ubicación,
 * referencia e información útil. Deja claro que NO registra la asistencia (§4.4).
 */
class ReservationConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Reservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reserva confirmada: '.$this->reservation->talk->title,
        );
    }

    public function content(): Content
    {
        $talk = $this->reservation->talk;
        $zone = config('app.display_timezone');
        $start = $talk->starts_at->setTimezone($zone);
        $end = $talk->ends_at->setTimezone($zone);

        return new Content(
            markdown: 'mail.reservation-confirmed',
            with: [
                'name' => $this->reservation->user->name,
                'title' => $talk->title,
                'speaker' => $talk->speaker->name,
                'date' => $start->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY'),
                'time' => $start->format('H:i').'–'.$end->format('H:i'),
                'room' => $talk->room->name,
                'location' => $talk->room->location_details,
                'reference' => $this->reservation->reference,
                'reservationsUrl' => rtrim(config('app.frontend_url'), '/').'/mis-reservas',
            ],
        );
    }
}
