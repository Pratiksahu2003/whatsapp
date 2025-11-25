<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function dashboard_requires_authentication()
    {
        $response = $this->get(route('whatsapp.dashboard'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function authenticated_user_can_access_dashboard()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('whatsapp.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('whatsapp.dashboard');
    }

    /** @test */
    public function send_message_requires_authentication()
    {
        $response = $this->postJson(route('whatsapp.send'), [
            'to' => '918738871535',
            'message' => 'Test message'
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function send_message_requires_whatsapp_credentials()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('whatsapp.send'), [
            'to' => '918738871535',
            'message' => 'Test message'
        ]);

        $response->assertStatus(403);
        $response->assertSee('Please configure your WhatsApp credentials in settings.');
    }

    /** @test */
    public function send_message_validates_phone_number_format()
    {
        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token',
            'whatsapp_api_url' => 'https://graph.facebook.com/v24.0'
        ]);

        $response = $this->actingAs($user)->postJson(route('whatsapp.send'), [
            'to' => 'invalid',
            'message' => 'Test message'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to']);
    }

    /** @test */
    public function send_message_to_specific_phone_number()
    {
        Http::fake([
            'graph.facebook.com/v24.0/*/messages' => Http::response([
                'messages' => [['id' => 'wamid.test123']],
                'contacts' => [['input' => '918738871535', 'wa_id' => '918738871535']]
            ], 200)
        ]);

        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token',
            'whatsapp_api_url' => 'https://graph.facebook.com/v24.0'
        ]);

        $response = $this->actingAs($user)->postJson(route('whatsapp.send'), [
            'to' => '918738871535',
            'message' => 'Test message to specific number'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Message sent successfully'
        ]);

        // Verify message was saved to database with correct phone number
        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $user->id,
            'phone_number' => '918738871535',
            'content' => 'Test message to specific number',
            'direction' => 'sent',
            'status' => 'sent'
        ]);

        Http::assertSent(function ($request) {
            return $request['to'] === '918738871535' &&
                   $request['text']['body'] === 'Test message to specific number';
        });
    }

    /** @test */
    public function send_message_validates_message_required()
    {
        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token',
            'whatsapp_api_url' => 'https://graph.facebook.com/v24.0'
        ]);

        $response = $this->actingAs($user)->postJson(route('whatsapp.send'), [
            'to' => '1234567890',
            'message' => ''
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }

    /** @test */
    public function send_message_successfully_sends_to_whatsapp_api()
    {
        Http::fake([
            'graph.facebook.com/v24.0/*/messages' => Http::response([
                'messages' => [['id' => 'wamid.test123']],
                'contacts' => [['input' => '918738871535', 'wa_id' => '918738871535']]
            ], 200)
        ]);

        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token',
            'whatsapp_api_url' => 'https://graph.facebook.com/v24.0'
        ]);

        $response = $this->actingAs($user)->postJson(route('whatsapp.send'), [
            'to' => '918738871535',
            'message' => 'Test message'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Message sent successfully'
        ]);

        // Verify message was saved to database
        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $user->id,
            'phone_number' => '918738871535',
            'content' => 'Test message',
            'direction' => 'sent',
            'status' => 'sent'
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v24.0/123456789/messages' &&
                   $request->hasHeader('Authorization', 'Bearer test_token') &&
                   $request['to'] === '918738871535' &&
                   $request['text']['body'] === 'Test message';
        });
    }

    /** @test */
    public function send_message_handles_api_error()
    {
        Http::fake([
            'graph.facebook.com/v24.0/*/messages' => Http::response([
                'error' => [
                    'message' => 'Invalid recipient',
                    'code' => 131047
                ]
            ], 400)
        ]);

        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token',
            'whatsapp_api_url' => 'https://graph.facebook.com/v24.0'
        ]);

        $response = $this->actingAs($user)->postJson(route('whatsapp.send'), [
            'to' => '918738871535',
            'message' => 'Test message'
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false
        ]);

        // Verify failed message was saved to database
        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $user->id,
            'phone_number' => '918738871535',
            'status' => 'failed'
        ]);
    }

    /** @test */
    public function send_message_handles_missing_message_id()
    {
        Http::fake([
            'graph.facebook.com/v24.0/*/messages' => Http::response([
                'messages' => [],
                'contacts' => []
            ], 200)
        ]);

        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token',
            'whatsapp_api_url' => 'https://graph.facebook.com/v24.0'
        ]);

        $response = $this->actingAs($user)->postJson(route('whatsapp.send'), [
            'to' => '918738871535',
            'message' => 'Test message'
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'error_code' => 'NO_MESSAGE_ID'
        ]);
    }

    /** @test */
    public function webhook_verification_requires_valid_token()
    {
        $user = User::factory()->create([
            'whatsapp_verify_token' => 'my_secret_token'
        ]);

        $response = $this->get(route('whatsapp.verify', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'wrong_token',
            'hub_challenge' => 'test_challenge'
        ]));

        $response->assertStatus(403);
    }

    /** @test */
    public function webhook_verification_returns_challenge_on_success()
    {
        $user = User::factory()->create([
            'whatsapp_verify_token' => 'my_secret_token'
        ]);

        $response = $this->get(route('whatsapp.verify', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'my_secret_token',
            'hub_challenge' => 'test_challenge_123'
        ]));

        $response->assertStatus(200);
        $response->assertSeeText('test_challenge_123');
        $this->assertEquals('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
    }

    /** @test */
    public function webhook_verification_requires_subscribe_mode()
    {
        $user = User::factory()->create([
            'whatsapp_verify_token' => 'my_secret_token'
        ]);

        $response = $this->get(route('whatsapp.verify', [
            'hub_mode' => 'unsubscribe',
            'hub_verify_token' => 'my_secret_token',
            'hub_challenge' => 'test_challenge'
        ]));

        $response->assertStatus(403);
    }

    /** @test */
    public function webhook_endpoint_processes_status_updates()
    {
        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token'
        ]);

        // Create a sent message first
        $message = Message::create([
            'user_id' => $user->id,
            'direction' => 'sent',
            'message_id' => 'wamid.test123',
            'phone_number' => '918738871535',
            'message_type' => 'text',
            'content' => 'Test message',
            'status' => 'sent'
        ]);

        $webhookData = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => '123456789'
                        ],
                        'statuses' => [[
                            'id' => 'wamid.test123',
                            'status' => 'delivered',
                            'timestamp' => time(),
                            'recipient_id' => '918738871535'
                        ]]
                    ]
                ]]
            ]]
        ];

        $response = $this->postJson(route('whatsapp.webhook'), $webhookData);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        // Verify message status was updated
        $this->assertDatabaseHas('whatsapp_messages', [
            'id' => $message->id,
            'status' => 'delivered'
        ]);
    }

    /** @test */
    public function webhook_endpoint_processes_incoming_messages()
    {
        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token'
        ]);

        $webhookData = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => '123456789'
                        ],
                        'messages' => [[
                            'from' => '9876543210',
                            'id' => 'wamid.received123',
                            'type' => 'text',
                            'timestamp' => time(),
                            'text' => [
                                'body' => 'Hello from user'
                            ]
                        ]]
                    ]
                ]]
            ]]
        ];

        $response = $this->postJson(route('whatsapp.webhook'), $webhookData);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        // Verify received message was saved
        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $user->id,
            'direction' => 'received',
            'phone_number' => '9876543210',
            'message_id' => 'wamid.received123',
            'content' => 'Hello from user'
        ]);
    }

    /** @test */
    public function webhook_endpoint_returns_200_even_on_processing_error()
    {
        // Invalid webhook structure
        $webhookData = [
            'invalid' => 'data'
        ];

        $response = $this->postJson(route('whatsapp.webhook'), $webhookData);

        // Should still return 200 to prevent Meta retries
        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
    }

    /** @test */
    public function sync_pending_messages_requires_authentication()
    {
        $response = $this->postJson(route('whatsapp.sync-pending'));

        $response->assertStatus(401);
    }

    /** @test */
    public function authenticated_user_can_sync_pending_messages()
    {
        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token'
        ]);

        // Create a pending message
        Message::create([
            'user_id' => $user->id,
            'direction' => 'sent',
            'phone_number' => '918738871535',
            'message_type' => 'text',
            'content' => 'Test',
            'status' => 'sent',
            'created_at' => now()->subHours(2)
        ]);

        $response = $this->actingAs($user)->postJson(route('whatsapp.sync-pending'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'pending_count'
            ]
        ]);
    }
}

