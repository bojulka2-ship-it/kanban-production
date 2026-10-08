<?php

namespace Tests\Feature;

use App\Models\User;

class AuthFlowTest extends BoardTestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/users')->assertRedirect('/login');
        $this->get('/password')->assertRedirect('/login');
    }

    public function test_login_page_opens_for_guest(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_login_with_valid_credentials(): void
    {
        $this->post('/login', [
            'email' => 'manager@test.local',
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($this->manager);
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $this->from('/login')->post('/login', [
            'email' => 'manager@test.local',
            'password' => 'wrong-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('credentials');

        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $this->employee->update(['is_active' => false]);

        $this->from('/login')->post('/login', [
            'email' => 'emp1@test.local',
            'password' => 'password',
        ])->assertRedirect('/login')->assertSessionHasErrors('status');

        $this->assertGuest();
    }

    public function test_logout_clears_session(): void
    {
        $this->actingAs($this->employee)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $this->actingAs($this->employee)->get('/login')->assertRedirect('/');
    }

    public function test_passwords_are_stored_hashed(): void
    {
        $raw = $this->employee->getRawOriginal('password');

        $this->assertNotSame('password', $raw);
        $this->assertTrue(password_get_info($raw)['algo'] !== null);
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $this->actingAs($this->employee)
            ->from('/password')
            ->put('/password', [
                'current_password' => 'not-my-password',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ])
            ->assertRedirect('/password')
            ->assertSessionHasErrors('current_password');
    }

    public function test_password_change_with_correct_current_password(): void
    {
        $this->actingAs($this->employee)
            ->from('/password')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ])
            ->assertRedirect('/password')
            ->assertSessionHas('success');

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-secret-123', $this->employee->fresh()->password));
    }
}
