# TutorMind API Microservices

This directory contains microservices for TutorMind's AI-powered features.

## Architecture

```
TutorMind
├── server_mysql.php          # Main chat service (legacy endpoint)
└── api/
    ├── image.php             # Image generation microservice
    ├── chat.php              # (Future) Dedicated chat endpoint
    └── audio.php             # (Future) TTS/STT service
```

## Services

### 📸 Image Generation Service (`/api/image.php`)

Handles AI image generation using Google's Imagen API.

**Endpoint:** `POST /api/image.php`

**Request:**
```json
{
  "prompt": "A detailed description of the image to generate",
  "options": {
    "aspectRatio": "1:1",  // Options: "1:1", "16:9", "9:16", "4:3", "3:4"
    "sampleCount": 1
  }
}
```

**Success Response:**
```json
{
  "success": true,
  "imageData": "data:image/png;base64,...",
  "mimeType": "image/png",
  "prompt": "The prompt used"
}
```

**Error Response:**
```json
{
  "success": false,
  "error": "Error message describing what went wrong"
}
```

**Features:**
- ✅ Comprehensive error handling
- ✅ Detailed logging for debugging
- ✅ Support for multiple aspect ratios
- ✅ Billing and quota error detection
- ✅ Internal call verification

**Common Errors:**
- `Image generation requires billing` - Enable billing in Google Cloud Console
- `Rate limit exceeded` - Too many requests, wait and retry
- `API key does not have permission` - Check API key permissions

## How It Works

### AI-Driven Image Generation Flow

1. **User Request** 
   - User: "Draw a cute cat"

2. **Chat Service Processing**
   - `server_mysql.php` sends request to Gemini
   - Gemini detects image generation intent
   - Returns function call: `generate_image(prompt="cute cartoon cat")`

3. **Function Call Handler**
   - Chat service detects function call
   - Makes internal HTTP request to `/api/image.php`

4. **Image Service**
   - Calls Imagen API
   - Returns base64 image data or error

5. **Response to User**
   - Chat service embeds image in markdown
   - User sees image in chat

## Configuration

All services use the same configuration:
- `config-sql.ini` (primary)
- `config.ini` (fallback)

Required config:
```ini
GEMINI_API_KEY=your_api_key_here
```

## Error Handling

Each service implements:
- **Try-catch blocks** for graceful failures
- **Detailed logging** to PHP error log
- **User-friendly messages** explaining issues
- **Fallback behavior** when services fail

## Security

- ✅ Internal call verification via headers
- ✅ POST-only endpoints
- ✅ API key validation
- ✅ Input sanitization
- ⚠️ TODO: Rate limiting per user
- ⚠️ TODO: Authentication for external calls

## Development

### Testing Image Service Directly

```bash
curl -X POST http://localhost/api/image.php \
  -H "Content-Type: application/json" \
  -d '{"prompt": "A beautiful sunset over mountains"}'
```

### Checking Logs

Windows (XAMPP):
```
C:\xampp\php\logs\php_error_log
C:\xampp\apache\logs\error.log
```

### Adding New Services

1. Create new PHP file in `/api/`
2. Follow same structure:
   - Header for content type
   - Config loading
   - Main function
   - Try-catch with logging
   - JSON response
3. Update this README

## Future Enhancements

- [ ] Move chat logic to `/api/chat.php`
- [ ] Add text-to-speech service
- [ ] Add speech-to-text service
- [ ] Implement rate limiting
- [ ] Add caching layer
- [ ] Create admin dashboard
- [ ] Add monitoring/metrics

## Troubleshooting

### Image generation fails
1. Check error log for detailed message
2. Verify billing is enabled in Google Cloud
3. Check API key has Imagen permissions
4. Test image service directly (see above)

### Service timeout
- Increase `CURLOPT_TIMEOUT` in calling service
- Check network connectivity
- Verify service is running

### 404 errors
- Check `.htaccess` configuration
- Verify file paths are correct
- Ensure Apache can access `/api/` directory
