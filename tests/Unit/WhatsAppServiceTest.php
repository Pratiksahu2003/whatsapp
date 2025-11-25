<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Message;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    /** @test */
    public function service_requires_credentials_to_send_message()
    {
        $service = new WhatsAppService();

        $result = $service->sendTextMessage('918738871535', 'Test message');

        $this->assertFalse($result['success']);
        $this->assertEquals('MISSING_CREDENTIALS', $result['error_code']);
    }

    /** @test */
    public function service_validates_phone_number_format()
    {
        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token',
            'whatsapp_api_url' => 'https://graph.facebook.com/v24.0'
        ]);

        $service = new WhatsAppService($user);

        // Too short
        $result = $service->sendTextMessage('123', 'Test');
        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_PHONE_FORMAT', $result['error_code']);

        // Too long
        $result = $service->sendTextMessage('12345678901234567', 'Test');
        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_PHONE_FORMAT', $result['error_code']);
    }

    /** @test */
    public function service_normalizes_phone_number()
    {
        Http::fake([
            'graph.facebook.com/v24.0/*/messages' => Http::response([
                'messages' => [['id' => 'wamid.test']],
                'contacts' => [['input' => '1234567890', 'wa_id' => '1234567890']]
            ], 200)
        ]);

        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token',
            'whatsapp_api_url' => 'https://graph.facebook.com/v24.0'
        ]);

        $service = new WhatsAppService($user);

        // Phone number with + and spaces
        $result = $service->sendTextMessage('+91 873-887-1535', 'Test message');

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) {
            return $request['to'] === '918738871535'; // Normalized
        });
    }

    /** @test */
    public function service_saves_message_to_database_on_success()
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

        $service = new WhatsAppService($user);
        $result = $service->sendTextMessage('918738871535', 'Test message', $user->id);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $user->id,
            'message_id' => 'wamid.test123',
            'phone_number' => '918738871535',
            'content' => 'Test message',
            'status' => 'sent'
        ]);
    }

    /** @test */
    public function service_handles_missing_message_id()
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

        $service = new WhatsAppService($user);
        $result = $service->sendTextMessage('918738871535', 'Test message', $user->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('NO_MESSAGE_ID', $result['error_code']);

        // Should save as failed
        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $user->id,
            'phone_number' => '918738871535',
            'status' => 'failed'
        ]);
    }

    /** @test */
    public function service_handles_api_errors()
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

        $service = new WhatsAppService($user);
        $result = $service->sendTextMessage('918738871535', 'Test message', $user->id);

        $this->assertFalse($result['success']);
        $this->assertEquals(131047, $result['error_code']);

        // Should save as failed
        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $user->id,
            'phone_number' => '918738871535',
            'status' => 'failed'
        ]);
    }

    /** @test */
    public function service_warns_when_contact_info_missing()
    {
        Http::fake([
            'graph.facebook.com/v24.0/*/messages' => Http::response([
                'messages' => [['id' => 'wamid.test']],
                'contacts' => [] // Empty contacts
            ], 200)
        ]);

        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token',
            'whatsapp_api_url' => 'https://graph.facebook.com/v24.0'
        ]);

        $service = new WhatsAppService($user);
        $result = $service->sendTextMessage('918738871535', 'Test message', $user->id);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['warning']);
        $this->assertStringContainsString('24 hours', $result['warning']);
    }

    /** @test */
    public function service_processes_status_updates()
    {
        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token'
        ]);

        $message = Message::create([
            'user_id' => $user->id,
            'direction' => 'sent',
            'message_id' => 'wamid.test123',
            'phone_number' => '918738871535',
            'message_type' => 'text',
            'content' => 'Test',
            'status' => 'sent'
        ]);

        $webhookData = [
            'entry' => [[
                'changes' => [[
                    'value' => [
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

        $service = new WhatsAppService($user);
        $result = $service->processStatusUpdate($webhookData);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['processed']);

        // Verify status updated
        $message->refresh();
        $this->assertEquals('delivered', $message->status);
        $this->assertNotNull($message->delivered_at);
    }

    /** @test */
    public function service_processes_incoming_messages()
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
                                'body' => 'Hello'
                            ]
                        ]]
                    ]
                ]]
            ]]
        ];

        $service = new WhatsAppService($user);
        $result = $service->processWebhook($webhookData, $user->id);

        $this->assertTrue($result['success']);

        // Verify message saved
        $this->assertDatabaseHas('whatsapp_messages', [
            'user_id' => $user->id,
            'direction' => 'received',
            'phone_number' => '9876543210',
            'message_id' => 'wamid.received123',
            'content' => 'Hello'
        ]);
    }

    /** @test */
    public function service_handles_invalid_webhook_structure()
    {
        $user = User::factory()->create([
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_access_token' => 'test_token'
        ]);

        $service = new WhatsAppService($user);
        $result = $service->processWebhook(['invalid' => 'data'], $user->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid webhook structure', $result['message']);
    }
}

