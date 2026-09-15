<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz ('/') exige sesión (grupo de rutas bajo middleware 'auth', ver routes/web.php) --
     * un visitante sin autenticar debe ser redirigido al login, no ver un 200.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
