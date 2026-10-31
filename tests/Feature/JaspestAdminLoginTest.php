<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JaspestAdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_sign_in_with_username_and_password(): void
    {
        $user = User::factory()->create([
            'username' => 'jas_admin',
            'password' => 'correct-password',
        ]);

        config(['jaspest_admin.editor_user_ids' => [(string) $user->id]]);

        $response = $this->post(route('jaspest.login.submit'), [
            'username' => 'JAS_ADMIN',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('jaspest.admin.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_email_is_not_accepted_as_the_login_identifier(): void
    {
        $user = User::factory()->create([
            'username' => 'jas_admin',
            'email' => 'jas_admin@example.com',
            'password' => 'correct-password',
        ]);

        config(['jaspest_admin.editor_user_ids' => [(string) $user->id]]);

        $response = $this->post(route('jaspest.login.submit'), [
            'email' => 'jas_admin@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_invalid_username_or_password_returns_a_username_error(): void
    {
        $user = User::factory()->create(['username' => 'jas_admin']);
        config(['jaspest_admin.editor_user_ids' => [(string) $user->id]]);

        $response = $this->post(route('jaspest.login.submit'), [
            'username' => 'jas_admin',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}
