<?php

namespace App\Http\Controllers\Acceso;

use App\Http\Controllers\Controller;
use App\Models\AccesoDispositivo;
use App\Models\Cliente;
use App\Support\Cuentas;
use App\Support\Fitness\Torniquete;
use Illuminate\Http\Request;

/**
 * Lo que llama un equipo Control iD (iDFace) en modo online. En el equipo se registra como servidor la dirección
 * {app}/api/controlid/{token}; el equipo le agrega /new_qrcode.fcgi, /device_is_alive.fcgi, etc.
 * Respuesta de acceso: {"result": {"event": 7 (permitido) | 6 (negado), "actions": [...], "message": "..."}}
 */
class ControlIdController extends Controller
{
    public function __invoke(Request $request, string $token, string $evento)
    {
        $d = AccesoDispositivo::sinCuenta()->where('token', $token)->where('activo', true)->first();
        if (! $d || ! $d->cuenta?->activa || ! $d->cuenta->tiene('fitness')) return response('', 404);
        Cuentas::activar($d->cuenta);
        $d->forceFill(['ultimo_contacto_at' => now()])->saveQuietly();

        $portal = (int) $request->input('portal_id', 1);
        return match ($evento) {
            'new_qrcode'          => $this->responder(Torniquete::qr($d, (string) $request->input('qrcode_value', '')), $d, $portal),
            // Rostro o huella que el equipo identificó con su propio registro: el id del usuario en el equipo es el id del atleta
            'new_user_identified' => $this->identificado($request, $d, $portal),
            'new_card'            => $this->responder(Torniquete::qr($d, (string) $request->input('card_value', ''), 'tarjeta'), $d, $portal),
            'new_user_id_and_password', 'new_biometric_image', 'new_biometric_template', 'new_uhf_tag'
                                  => $this->responder(['permitido' => false, 'mensaje' => 'Muestra el QR de tu credencial.', 'cliente' => null], $d, $portal, 3),
            'new_rex_log'         => $this->boton($d),
            'session_is_valid'    => response()->json(['session_is_valid' => true]),
            default               => response('', 200),   // device_is_alive y cualquier otro aviso
        };
    }

    private function identificado(Request $request, AccesoDispositivo $d, int $portal)
    {
        $c = Cliente::find((int) $request->input('user_id'));
        if (! $c || (int) $request->input('event') === 3) {
            return $this->responder(['permitido' => false, 'mensaje' => 'No te reconocí. Muestra el QR de tu credencial.', 'cliente' => null], $d, $portal, 3);
        }
        return $this->responder(Torniquete::atleta($d, $c, 'rostro'), $d, $portal);
    }

    private function boton(AccesoDispositivo $d)
    {
        Torniquete::registrar($d, 'boton', true, 'boton', 'Recepción abrió con el botón.');
        return response('', 200);
    }

    private function responder(array $r, AccesoDispositivo $d, int $portal, ?int $evento = null)
    {
        $c = $r['cliente'] ?? null;
        $res = [
            'event'      => $evento ?? ($r['permitido'] ? 7 : 6),
            'user_id'    => $c?->id ?? 0,
            'user_name'  => $c?->nombre ?? '',
            'user_image' => false,
            'portal_id'  => $portal,
            'actions'    => $r['permitido'] ? [['action' => $d->accion, 'parameters' => $d->parametros]] : [],
            'message'    => $r['mensaje'],
        ];
        return response()->json(['result' => $res]);
    }
}
