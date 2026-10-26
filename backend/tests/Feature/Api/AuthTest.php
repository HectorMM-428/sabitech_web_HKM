<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Registro, login, logout y sesión (A04–A07). Pruebas T03 y parte de T04. */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function lucia(): User
    {
        return User::factory()->create(['email' => 'lucia@sabitech.test', 'password' => 'Clave-segura-2026']);
    }

    #[Test]
    public function t03_login_valido_inicia_sesion(): void
    {
        $user = $this->lucia();

        $this->postJson('/api/v1/auth/login', ['email' => 'LUCIA@sabitech.test', 'password' => 'Clave-segura-2026'])
            ->assertOk()
            ->assertExactJson(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => 'lucia@sabitech.test', 'role' => 'attendee']]);

        $this->assertAuthenticatedAs($user, 'web');
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    #[Test]
    public function t03_login_invalido_no_revela_si_el_email_existe(): void
    {
        $this->lucia();

        $wrongPassword = $this->postJson('/api/v1/auth/login', ['email' => 'lucia@sabitech.test', 'password' => 'otra-clave-1'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->json('error.fields');

        $unknownEmail = $this->postJson('/api/v1/auth/login', ['email' => 'nadie@sabitech.test', 'password' => 'otra-clave-1'])
            ->assertUnprocessable()
            ->json('error.fields');

        $this->assertSame($wrongPassword, $unknownEmail);
        $this->assertGuest('web');
    }

    #[Test]
    public function el_login_se_bloquea_tras_5_intentos_por_minuto(): void
    {
        $this->lucia();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'lucia@sabitech.test', 'password' => 'mal-'.$i])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'lucia@sabitech.test', 'password' => 'Clave-segura-2026'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS')
            ->assertHeader('Retry-After');
    }

    #[Test]
    public function el_registro_crea_un_asistente_con_la_contrasena_cifrada(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '  Irene Navarro ',
            'email' => 'Irene@Sabitech.test',
            'password' => 'Clave-segura-2026',
            'password_confirmation' => 'Clave-segura-2026',
            'role' => 'admin', // intento de escalada: se ignora
        ])->assertCreated()
            ->assertJsonPath('data.role', 'attendee')
            ->assertJsonPath('data.email', 'irene@sabitech.test')
            ->assertJsonPath('data.name', 'Irene Navarro');

        $user = User::findOrFail($response->json('data.id'));
        $this->assertNotSame('Clave-segura-2026', $user->password);
        $this->assertTrue(Hash::check('Clave-segura-2026', $user->password));
        $this->assertAuthenticatedAs($user, 'web');
    }

    #[Test]
    public function el_registro_valida_los_datos_en_el_servidor(): void
    {
        $this->lucia();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'L',
            'email' => 'lucia@sabitech.test',
            'password' => 'corta',
            'password_confirmation' => 'distinta',
        ])->assertUnprocessable()
            ->assertJsonPath('error.fields.name.0', 'El nombre debe tener al menos 2 caracteres.')
            ->assertJsonPath('error.fields.email.0', 'Ya existe una cuenta con este email. Inicia sesión.')
            ->assertJsonPath('error.fields.password.0', 'Las contraseñas no coinciden.');
    }

    #[Test]
    public function el_logout_invalida_la_sesion(): void
    {
        $user = $this->lucia();
        $this->postJson('/api/v1/auth/login', ['email' => 'lucia@sabitech.test', 'password' => 'Clave-segura-2026'])->assertOk();

        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertGuest('web');

        // Tras salir, la sesión ya no sirve.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');
        $this->assertNotNull($user->fresh());
    }

    #[Test]
    public function sin_sesion_las_rutas_privadas_responden_401(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertExactJson(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Tu sesión ha caducado. Vuelve a iniciar sesión.', 'fields' => null]]);
    }

    #[Test]
    public function el_endpoint_csrf_de_sanctum_entrega_la_cookie_xsrf(): void
    {
        $this->get('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
    }
}
