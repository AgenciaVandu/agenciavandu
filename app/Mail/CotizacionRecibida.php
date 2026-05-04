<?php 
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CotizacionRecibida extends Mailable
{
    use Queueable, SerializesModels;

    public $datos; // Aquí se guardará la info del form

    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    public function build()
    {
        return $this->subject('Nueva solicitud de cotización - Vandu')
                    ->view('emails.cotizacion'); // Crearemos esta vista ahorita
    }
}