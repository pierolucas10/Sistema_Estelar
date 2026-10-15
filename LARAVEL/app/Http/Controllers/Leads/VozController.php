<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Prospección por voz: el sistema VOZ (PHP aparte, con su propia BD)
 * mostrado dentro del módulo de Leads, sin pedir un segundo login.
 */
class VozController extends Controller
{
    /** Segundos de validez del token de ingreso (VOZ acepta hasta 120). */
    private const VIDA_TOKEN = 60;

    public function index()
    {
        return view('leads.voz', [
            'configurado' => $this->configurado(),
        ]);
    }

    /**
     * Firma un token de un solo uso con el usuario actual y redirige a VOZ.
     * Se genera en cada carga para que el token nunca quede escrito en el HTML.
     */
    public function entrar(Request $request)
    {
        abort_unless($this->configurado(), 503, 'Prospección por voz no está configurada (VOZ_URL / VOZ_SSO_SECRET).');

        $user  = $request->user();
        $ahora = now()->timestamp;

        $payload = [
            'email'  => mb_strtolower(trim($user->email)),
            'nombre' => $user->name,
            'rol'    => $user->hasAnyRole(config('services.voz.roles_admin')) ? 'admin' : 'asesor',
            'iat'    => $ahora,
            'exp'    => $ahora + self::VIDA_TOKEN,
            'jti'    => bin2hex(random_bytes(16)),
        ];

        $cuerpo = $this->b64url(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $firma  = $this->b64url(hash_hmac('sha256', $cuerpo, config('services.voz.sso_secret'), true));

        return redirect()
            ->away(config('services.voz.url') . '/sso.php?token=' . $cuerpo . '.' . $firma)
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function configurado(): bool
    {
        return config('services.voz.url') !== ''
            && strlen((string) config('services.voz.sso_secret')) >= 32;
    }

    private function b64url(string $binario): string
    {
        return rtrim(strtr(base64_encode($binario), '+/', '-_'), '=');
    }
}
