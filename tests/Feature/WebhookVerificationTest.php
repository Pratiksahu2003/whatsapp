<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function webhook_verification_is_publicly_accessible()
    {
        // Should not require authentication
        $response = $this->get(route('whatsapp.verify', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'test_token',
            'hub_challenge' => 'test_challenge'
        ]));

        // Should return 403 if token doesn't match, but endpoint is accessible
        $response->assertStatus(403);
    }

    /** @test */
    public function webhook_verification_succeeds_with_correct_token()
    {
        $user = User::factory()->create([
            'whatsapp_verify_token' => 'my_secret_token_123'
        ]);

        $response = $this->get(route('whatsapp.verify', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'my_secret_token_123',
            'hub_challenge' => 'challenge_string_456'
        ]));

        $response->assertStatus(200);
        $response->assertSeeText('challenge_string_456');
        $this->assertEquals('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
    }

    /** @test */
    public function webhook_verification_fails_with_incorrect_token()
    {
        $user = User::factory()->create([
            'whatsapp_verify_token' => 'correct_token'
        ]);

        $response = $this->get(route('whatsapp.verify', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'wrong_token',
            'hub_challenge' => 'test_challenge'
        ]));

        $response->assertStatus(403);
    }

    /** @test */
    public function webhook_verification_handles_case_insensitive_token()
    {
        $user = User::factory()->create([
            'whatsapp_verify_token' => 'MySecretToken'
        ]);

        $response = $this->get(route('whatsapp.verify', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'mysecrettoken', // lowercase
            'hub_challenge' => 'test_challenge'
        ]));

        $response->assertStatus(200);
    }

    /** @test */
    public function webhook_verification_trims_whitespace()
    {
        $user = User::factory()->create([
            'whatsapp_verify_token' => '  my_token  '
        ]);

        $response = $this->get(route('whatsapp.verify', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'my_token', // without spaces
            'hub_challenge' => 'test_challenge'
        ]));

        $response->assertStatus(200);
    }

    /** @test */
    public function webhook_verification_requires_subscribe_mode()
    {
        $user = User::factory()->create([
            'whatsapp_verify_token' => 'test_token'
        ]);

        $response = $this->get(route('whatsapp.verify', [
            'hub_mode' => 'unsubscribe',
            'hub_verify_token' => 'test_token',
            'hub_challenge' => 'test_challenge'
        ]));

        $response->assertStatus(403);
    }

    /** @test */
    public function webhook_verification_requires_challenge_parameter()
    {
        $user = User::factory()->create([
            'whatsapp_verify_token' => 'test_token'
        ]);

        $response = $this->get(route('whatsapp.verify', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'test_token'
            // Missing hub_challenge
        ]));

        $response->assertStatus(403);
    }

    /** @test */
    public function webhook_verification_works_with_multiple_users()
    {
        $user1 = User::factory()->create([
            'whatsapp_verify_token' => 'token1'
        ]);

        $user2 = User::factory()->create([
            'whatsapp_verify_token' => 'token2'
        ]);

        // Should match user1's token
        $response = $this->get(route('whatsapp.verify', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'token1',
            'hub_challenge' => 'challenge1'
        ]));

        $response->assertStatus(200);
        $response->assertSeeText('challenge1');

        // Should match user2's token
        $response = $this->get(route('whatsapp.verify', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'token2',
            'hub_challenge' => 'challenge2'
        ]));

        $response->assertStatus(200);
        $response->assertSeeText('challenge2');
    }

    /** @test */
    public function webhook_verification_accepts_get_and_post()
    {
        $user = User::factory()->create([
            'whatsapp_verify_token' => 'test_token'
        ]);

        // GET request
        $response = $this->get(route('whatsapp.verify', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'test_token',
            'hub_challenge' => 'get_challenge'
        ]));

        $response->assertStatus(200);

        // POST request
        $response = $this->post(route('whatsapp.verify', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'test_token',
            'hub_challenge' => 'post_challenge'
        ]));

        $response->assertStatus(200);
    }
}

