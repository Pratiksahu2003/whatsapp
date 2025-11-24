# Laravel WhatsApp API Integration

A Laravel project for integrating with Meta's WhatsApp Business API. This project provides a complete solution for sending and receiving WhatsApp messages through the Meta WhatsApp API.

## Features

- ✅ Send text messages
- ✅ Send media messages (images, videos, documents, audio)
- ✅ Send template messages
- ✅ Receive and process incoming messages via webhook
- ✅ Webhook verification for Meta
- ✅ Comprehensive error handling and logging

## Prerequisites

- PHP >= 8.2
- Composer
- Laravel 12.x
- Meta WhatsApp Business API credentials:
  - Phone Number ID
  - Access Token
  - Verify Token (for webhook)

## CSS/Assets Setup

**Important:** For CSS and JavaScript to load properly, you need to:

1. **Development Mode** (with hot reload):
   ```bash
   npm run dev
   ```
   Keep this running in a separate terminal while developing.

2. **Production Mode** (build assets):
   ```bash
   npm run build
   ```
   Run this after making changes to CSS/JS files.

3. **Clear Laravel caches** (if styles don't update):
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan view:clear
   ```

## Installation

1. **Clone or navigate to the project directory:**
   ```bash
   cd whatsapp
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Copy environment file:**
   ```bash
   cp .env.example .env
   ```

4. **Generate application key:**
   ```bash
   php artisan key:generate
   ```

5. **Configure WhatsApp API credentials in `.env`:**
   ```env
   WHATSAPP_API_URL=https://graph.facebook.com/v18.0
   WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id
   WHATSAPP_ACCESS_TOKEN=your_access_token
   WHATSAPP_VERIFY_TOKEN=your_verify_token
   ```

6. **Run migrations (if needed):**
   ```bash
   php artisan migrate
   ```

7. **Start the development server:**
   ```bash
   php artisan serve
   ```

## Configuration

### Getting WhatsApp API Credentials

1. Go to [Meta for Developers](https://developers.facebook.com/)
2. Create a new app or use an existing one
3. Add WhatsApp product to your app
4. Get your Phone Number ID and Access Token from the WhatsApp API setup
5. Create a Verify Token (any random string) for webhook verification

### Environment Variables

Add these to your `.env` file:

```env
WHATSAPP_API_URL=https://graph.facebook.com/v24.0
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id_here
WHATSAPP_ACCESS_TOKEN=your_access_token_here
WHATSAPP_VERIFY_TOKEN=your_verify_token_here
```

**Note:** This application uses WhatsApp Cloud API v24.0 following the [official Meta documentation](https://developers.facebook.com/documentation/business-messaging/whatsapp/).

## API Endpoints

### Webhook Endpoints

#### Verify Webhook (GET)
```
GET /whatsapp/verify?hub.mode=subscribe&hub.verify_token=YOUR_TOKEN&hub.challenge=CHALLENGE
```
Used by Meta to verify your webhook during setup.

#### Receive Messages (POST)
```
POST /whatsapp/webhook
```
Receives incoming messages from WhatsApp. Make sure to configure this URL in your Meta app settings.

### Send Message Endpoints

#### Send Text Message
```
POST /whatsapp/send
Content-Type: application/json

{
    "to": "1234567890",
    "message": "Hello, this is a test message!"
}
```

#### Send Media Message
```
POST /whatsapp/send-media
Content-Type: application/json

{
    "to": "1234567890",
    "media_url": "https://example.com/image.jpg",
    "type": "image",
    "caption": "Optional caption"
}
```

Supported media types: `image`, `video`, `audio`, `document`

#### Send Template Message
```
POST /whatsapp/send-template
Content-Type: application/json

{
    "to": "1234567890",
    "template_name": "hello_world",
    "language_code": "en_US",
    "parameters": ["John", "Doe"]
}
```

## Webhook Setup

1. **Expose your local server (for development):**
   - Use ngrok: `ngrok http 8000`
   - Or use a service like Cloudflare Tunnel

2. **Configure webhook in Meta App:**
   - Go to your Meta App Dashboard
   - Navigate to WhatsApp > Configuration
   - Set Webhook URL: `https://your-domain.com/whatsapp/webhook`
   - Set Verify Token: (same as WHATSAPP_VERIFY_TOKEN in .env)
   - Subscribe to `messages` field

3. **Test the webhook:**
   - Send a test message from your WhatsApp Business number
   - Check Laravel logs: `storage/logs/laravel.log`

## Usage Examples

### Using the Service Directly

```php
use App\Services\WhatsAppService;

$whatsapp = new WhatsAppService();

// Send text message
$result = $whatsapp->sendTextMessage('1234567890', 'Hello!');

// Send media
$result = $whatsapp->sendMediaMessage(
    '1234567890',
    'https://example.com/image.jpg',
    'image',
    'Check this out!'
);

// Send template
$result = $whatsapp->sendTemplateMessage(
    '1234567890',
    'hello_world',
    'en_US',
    ['John']
);
```

### Using the Controller

```php
use App\Http\Controllers\WhatsAppController;

$controller = new WhatsAppController(new WhatsAppService());

// Via HTTP request
POST /whatsapp/send
{
    "to": "1234567890",
    "message": "Hello!"
}
```

## Project Structure

```
app/
├── Http/
│   └── Controllers/
│       └── WhatsAppController.php    # Main controller for WhatsApp API
├── Services/
│   └── WhatsAppService.php            # WhatsApp API service class
config/
└── services.php                       # Service configuration
routes/
└── web.php                            # API routes
```

## Logging

All WhatsApp API interactions are logged to `storage/logs/laravel.log`. Check this file for:
- Sent messages
- Received webhooks
- API errors
- Exceptions

## Error Handling

The service includes comprehensive error handling:
- API errors are caught and logged
- User-friendly error messages are returned
- All exceptions are logged with stack traces

## Security Notes

- Never commit your `.env` file
- Keep your Access Token secure
- Use HTTPS for webhook endpoints in production
- Validate webhook requests in production (consider adding signature verification)

## Testing

Test your integration:

1. **Test sending a message:**
   ```bash
   curl -X POST http://localhost:8000/whatsapp/send \
     -H "Content-Type: application/json" \
     -d '{"to":"1234567890","message":"Test message"}'
   ```

2. **Test webhook (use a tool like Postman):**
   ```bash
   curl -X POST http://localhost:8000/whatsapp/webhook \
     -H "Content-Type: application/json" \
     -d '{"entry":[{"changes":[{"value":{"messages":[{"from":"1234567890","id":"test123","type":"text","text":{"body":"Hello"}}]}}]}]}'
   ```

## Troubleshooting

### Webhook not receiving messages
- Verify the webhook URL is accessible
- Check that the Verify Token matches
- Ensure you've subscribed to the `messages` field
- Check Laravel logs for errors

### Messages not sending
- Verify Access Token is valid
- Check Phone Number ID is correct
- Ensure recipient number is in international format (without +)
- Check API version in WHATSAPP_API_URL

### CORS Issues
- Add your domain to Meta App settings
- Configure CORS in Laravel if needed

## Resources

- [Meta WhatsApp Business API Documentation](https://developers.facebook.com/docs/whatsapp)
- [Laravel Documentation](https://laravel.com/docs)

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
