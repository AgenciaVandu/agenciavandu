<?php 

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CotizacionRecibida extends Mailable
{
    use Queueable, SerializesModels;

    // Esta propiedad pública estará disponible automáticamente en la vista Blade del correo
    public $datos; 

    /**
     * Crear una nueva instancia del mensaje.
     *
     * @param array $datos Información capturada del formulario
     */
    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    /**
     * Construir el mensaje.
     */
    public function build()
    {
        // El asunto ahora incluye el nombre para identificar rápido quién escribe
        return $this->subject('🔥 Nueva Cotización: ' . ($this->datos['name'] ?? 'Cliente Nuevo') . ' - Vandu')
                    ->view('emails.contactanos');
    }
}