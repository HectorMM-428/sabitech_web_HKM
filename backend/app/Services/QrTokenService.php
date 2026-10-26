<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Tokens del QR temporal (diseño, sección 5; AD-06).
 *
 * Formato:  base64url(payload) . "." . base64url(HMAC-SHA256(clave, payload))
 * Payload:  {"v":1, "rid":<reserva>, "tid":<charla>, "iat":<emisión>, "exp":<iat+30>}
 *
 * - La firma HMAC impide fabricar o modificar un token sin la clave del servidor.
 * - `iat` y `exp` los fija el servidor con SU reloj; el navegador nunca decide la validez.
 * - Regla exacta: válido si  ahora_servidor <= exp + leeway  (exp = iat + ttl).
 *   Con ttl = 30 s y leeway = 5 s, un token vale como máximo 35 s.
 * - No es necesario guardar los tokens: la asistencia única la garantiza la base de datos.
 */
class QrTokenService
{
    public const VERSION = 1;

    /**
     * @return array{token: string, issued_at: CarbonImmutable, expires_at: CarbonImmutable}
     */
    public function issue(Reservation $reservation): array
    {
        $issuedAt = CarbonImmutable::now();
        $expiresAt = $issuedAt->addSeconds($this->ttl());

        $payload = json_encode([
            'v' => self::VERSION,
            'rid' => $reservation->id,
            'tid' => $reservation->talk_id,
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        $token = self::base64url($payload).'.'.self::base64url($this->sign($payload));

        return ['token' => $token, 'issued_at' => $issuedAt, 'expires_at' => $expiresAt];
    }

    /**
     * Comprueba formato, firma y caducidad (pasos 2 a 4 de la validación).
     *
     * @return array{rid: int, tid: int, iat: int, exp: int}
     *
     * @throws BusinessRuleException INVALID o EXPIRED (422)
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw self::invalid();
        }

        $payload = self::base64urlDecode($parts[0]);
        $signature = self::base64urlDecode($parts[1]);
        if ($payload === null || $signature === null) {
            throw self::invalid();
        }

        // Comparación en tiempo constante: no da pistas sobre la firma correcta.
        if (! hash_equals($this->sign($payload), $signature)) {
            throw self::invalid();
        }

        $data = json_decode($payload, true);
        if (! is_array($data) || ($data['v'] ?? null) !== self::VERSION) {
            throw self::invalid();
        }
        foreach (['rid', 'tid', 'iat', 'exp'] as $key) {
            if (! is_int($data[$key] ?? null)) {
                throw self::invalid();
            }
        }

        $now = CarbonImmutable::now()->getTimestamp();

        // Un token «del futuro» no lo emite este servidor: se rechaza.
        if ($data['iat'] > $now + $this->leeway()) {
            throw self::invalid();
        }

        if ($now > $data['exp'] + $this->leeway()) {
            throw new BusinessRuleException('EXPIRED', 'El código ha caducado. Pide a la persona que lo actualice.', 422);
        }

        return ['rid' => $data['rid'], 'tid' => $data['tid'], 'iat' => $data['iat'], 'exp' => $data['exp']];
    }

    public function ttl(): int
    {
        return (int) config('sabitech.qr.ttl_seconds', 30);
    }

    public function leeway(): int
    {
        return (int) config('sabitech.qr.leeway_seconds', 5);
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->key(), true);
    }

    private function key(): string
    {
        $configured = (string) config('sabitech.qr.signing_key');
        if ($configured === '') {
            throw new RuntimeException('Falta QR_SIGNING_KEY: el QR no puede firmarse.');
        }

        $decoded = base64_decode($configured, true);

        return ($decoded !== false && strlen($decoded) >= 32) ? $decoded : $configured;
    }

    private static function invalid(): BusinessRuleException
    {
        return new BusinessRuleException('INVALID', 'El código no es válido.', 422);
    }

    private static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            return null;
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
