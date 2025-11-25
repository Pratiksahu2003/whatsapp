<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MessageSendingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function user_can_send_text_message()
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
            'message' => 'Hello, this is a test message!'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Message sent successfully'
        ]);

        // Verify message in database
        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $user->id,
            'phone_number' => '918738871535',
            'content' => 'Hello, this is a test message!',
            'direction' => 'sent',
            'status' => 'sent'
        ]);
    }

    /** @test */
    public function message_sending_shows_warning_when_contact_info_missing()
    {
        Http::fake([
            'graph.facebook.com/v24.0/*/messages' => Http::response([
                'messages' => [['id' => 'wamid.test123']],
                'contacts' => [] // Empty contacts - outside 24-hour window
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
            'success' => true
        ]);

        // Should have warning
        $this->assertNotNull($response->json('warning'));
        $this->assertStringContainsString('24 hours', $response->json('warning'));
    }

    /** @test */
    public function message_sending_fails_without_credentials()
    {
        $user = User::factory()->create();
        // No WhatsApp credentials

        $response = $this->actingAs($user)->postJson(route('whatsapp.send'), [
            'to' => '918738871535',
            'message' => 'Test message'
        ]);

        $response->assertStatus(403);
        $response->assertSee('Please configure your WhatsApp credentials in settings.');
    }

    /** @test */
    public function message_sending_validates_required_fields()
    {
        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token'
        ]);

        // Missing phone number
        $response = $this->actingAs($user)->postJson(route('whatsapp.send'), [
            'message' => 'Test message'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to']);

        // Missing message
        $response = $this->actingAs($user)->postJson(route('whatsapp.send'), [
            'to' => '918738871535'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }

    /** @test */
    public function message_sending_validates_phone_number_format()
    {
        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token'
        ]);

        // Invalid format (contains letters)
        $response = $this->actingAs($user)->postJson(route('whatsapp.send'), [
            'to' => 'abc123',
            'message' => 'Test message'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to']);
    }

    /** @test */
    public function message_sending_handles_api_errors_gracefully()
    {
        Http::fake([
            'graph.facebook.com/v24.0/*/messages' => Http::response([
                'error' => [
                    'message' => 'Recipient phone number not registered',
                    'code' => 131026
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
            'success' => false,
            'error_code' => 131026
        ]);

        // Should save as failed
        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $user->id,
            'phone_number' => '918738871535',
            'status' => 'failed'
        ]);
    }

    /** @test */
    public function message_sending_logs_activity()
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

        // Verify message was sent successfully
        $response->assertStatus(200);
        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $user->id,
            'phone_number' => '918738871535'
        ]);
    }

    /** @test */
    public function message_sending_creates_conversation_id()
    {
        Http::fake([
            'graph.facebook.com/v24.0/*/messages' => Http::response([
                'messages' => [['id' => 'wamid.test123']],
                'contacts' => [['input' => '1234567890', 'wa_id' => '1234567890']]
            ], 200)
        ]);

        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token',
            'whatsapp_api_url' => 'https://graph.facebook.com/v24.0'
        ]);

        $this->actingAs($user)->postJson(route('whatsapp.send'), [
            'to' => '918738871535',
            'message' => 'Test message'
        ]);

        $message = Message::where('user_id', $user->id)->first();
        $this->assertNotNull($message->conversation_id);
        $this->assertStringContainsString($user->id, $message->conversation_id);
        $this->assertStringContainsString('918738871535', $message->conversation_id);
    }

    /** @test */
    public function message_sending_sets_correct_timestamps()
    {
        Http::fake([
            'graph.facebook.com/v24.0/*/messages' => Http::response([
                'messages' => [['id' => 'wamid.test123']],
                'contacts' => [['input' => '1234567890', 'wa_id' => '1234567890']]
            ], 200)
        ]);

        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token',
            'whatsapp_api_url' => 'https://graph.facebook.com/v24.0'
        ]);

        $this->actingAs($user)->postJson(route('whatsapp.send'), [
            'to' => '918738871535',
            'message' => 'Test message'
        ]);

        $message = Message::where('user_id', $user->id)->first();
        $this->assertNotNull($message->sent_at);
        $this->assertNull($message->delivered_at); // Not delivered yet
        $this->assertNull($message->read_at); // Not read yet
    }
}

