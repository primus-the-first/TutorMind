# TutorMind API Documentation

This document provides comprehensive documentation for all API endpoints in the TutorMind platform.

## Table of Contents

- [Authentication](#authentication)
- [Core Chat API](#core-chat-api)
- [Conversation Management](#conversation-management)
- [User Settings](#user-settings)
- [Image Generation](#image-generation)
- [Text-to-Speech](#text-to-speech)
- [Knowledge Base](#knowledge-base)
- [User Onboarding](#user-onboarding)
- [Analytics](#analytics)
- [Learning Strategies](#learning-strategies)
- [Session Context](#session-context)
- [Account Management](#account-management)
- [Database Schema](#database-schema)
- [Error Handling](#error-handling)

---

## Authentication

All API endpoints require authentication via PHP sessions. Users must be logged in to access any API functionality.

### Session Middleware

**File**: [check_auth.php](check_auth.php)

Automatically validates user sessions before processing any API request. If the user is not authenticated, the request is rejected.

### Login

**Endpoint**: `POST /auth_mysql.php?action=login`

**Request Body**:
```json
{
  "username": "string",
  "password": "string",
  "remember_me": "boolean (optional)"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Login successful",
  "user_id": 123
}
```

**Error Response**:
```json
{
  "success": false,
  "error": "Invalid credentials"
}
```

### Register

**Endpoint**: `POST /auth_mysql.php?action=register`

**Request Body**:
```json
{
  "username": "string",
  "email": "string",
  "password": "string",
  "confirm_password": "string",
  "first_name": "string (optional)",
  "last_name": "string (optional)"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Registration successful",
  "user_id": 123
}
```

### Logout

**Endpoint**: `GET /server_mysql.php?action=logout`

Destroys the user session and redirects to the login page.

---

## Core Chat API

**Base File**: [server_mysql.php](server_mysql.php)

### Send Message

**Endpoint**: `POST /server_mysql.php`

**Request Body**:
```json
{
  "message": "string",
  "conversation_id": "integer (optional)",
  "file_upload": "base64 encoded file (optional)",
  "file_type": "string (optional)"
}
```

**Response**:
```json
{
  "success": true,
  "response": "HTML formatted AI response",
  "conversation_id": 123,
  "model_response": {
    "role": "model",
    "parts": [
      {
        "text": "AI response text"
      }
    ]
  }
}
```

**Supported File Types**:
- PDF (`.pdf`)
- Word Documents (`.doc`, `.docx`)
- PowerPoint (`.ppt`, `.pptx`)
- Images (`.jpg`, `.jpeg`, `.png`, `.gif`, `.webp`)

**Processing Flow**:
1. User message is received
2. File (if present) is processed and content extracted
3. Message sent to Google Gemini API
4. Response formatted with Markdown → HTML conversion
5. Message saved to database with caching
6. Response returned to client

---

## Conversation Management

### Get Conversation History

**Endpoint**: `GET /server_mysql.php?action=history`

**Description**: Retrieves a list of all conversations for the authenticated user.

**Response**:
```json
{
  "success": true,
  "history": [
    {
      "id": 1,
      "title": "Math homework help"
    },
    {
      "id": 2,
      "title": "Science questions"
    }
  ]
}
```

### Get Specific Conversation

**Endpoint**: `GET /server_mysql.php?action=get_conversation&id=123`

**Parameters**:
- `id` (integer, required) - Conversation ID

**Response**:
```json
{
  "success": true,
  "conversation": {
    "id": 123,
    "title": "Math homework help",
    "chat_history": [
      {
        "role": "user",
        "parts": [
          {
            "text": "What is calculus?"
          }
        ]
      },
      {
        "role": "model",
        "parts": [
          {
            "text": "<p>Calculus is...</p>"
          }
        ]
      }
    ]
  }
}
```

**Performance Optimizations**:
- Returns only the most recent 15 messages initially
- Uses cached HTML for model responses (avoids re-parsing Markdown)
- Strips heavy base64 image data from history (shows placeholder instead)

### Clear Conversation History

**Endpoint**: `POST /api/clear_history.php`

**Description**: Deletes all conversations and messages for the authenticated user.

**Response**:
```json
{
  "success": true,
  "message": "All conversations cleared"
}
```

---

## User Settings

**File**: [api/user_settings.php](api/user_settings.php)

### Get User Settings

**Endpoint**: `GET /api/user_settings.php`

**Response**:
```json
{
  "success": true,
  "settings": {
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "username": "johndoe",
    "learning_level": "university",
    "response_style": "detailed",
    "email_notifications": true,
    "study_reminders": true,
    "feature_announcements": false,
    "weekly_summary": true,
    "data_sharing": false,
    "dark_mode": true,
    "font_size": "medium",
    "chat_density": "comfortable",
    "legibility": 100
  }
}
```

### Update User Settings

**Endpoint**: `POST /api/user_settings.php`

**Request Body**:
```json
{
  "dark_mode": true,
  "font_size": "large",
  "legibility": 120,
  "response_style": "concise"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Settings updated successfully"
}
```

**Allowed Fields**:
- `first_name`, `last_name`, `email`, `username`
- `learning_level` - Values: `primary`, `secondary`, `university`, `professional`
- `response_style` - Values: `concise`, `detailed`, `step-by-step`
- `email_notifications`, `study_reminders`, `feature_announcements`, `weekly_summary` (boolean)
- `data_sharing` (boolean)
- `dark_mode` (boolean)
- `font_size` - Values: `small`, `medium`, `large`
- `chat_density` - Values: `compact`, `comfortable`, `spacious`
- `legibility` - Integer (80-150, percentage scale)

---

## Image Generation

**File**: [api/image.php](api/image.php)

### Generate Image

**Endpoint**: `POST /api/image.php`

**Request Body**:
```json
{
  "prompt": "A medieval castle on a hilltop at sunset",
  "aspectRatio": "1:1"
}
```

**Request Headers**:
```
X-INTERNAL-CALL: tutormind
```

**Response**:
```json
{
  "success": true,
  "imageData": "base64_encoded_image_data",
  "mimeType": "image/webp"
}
```

**Error Response**:
```json
{
  "success": false,
  "error": "Image generation failed: [error details]"
}
```

**Configuration**:
- **Model**: `imagen-4.0-ultra-generate-001`
- **API**: Google Imagen
- **Output Format**: WebP (30% smaller than JPEG)
- **Aspect Ratios**: `1:1`, `16:9`, `4:3`, etc.

---

## Text-to-Speech

**File**: [api/tts.php](api/tts.php)

### Convert Text to Speech

**Endpoint**: `POST /api/tts.php`

**Request Body**:
```json
{
  "text": "The text to convert to speech"
}
```

**Response**:
```json
{
  "success": true,
  "audio": "base64_encoded_audio_data",
  "format": "mp3"
}
```

**Fallback Response** (if API key not configured):
```json
{
  "success": true,
  "fallback": true,
  "text": "The text to convert to speech"
}
```

**Configuration**:
- **API**: ElevenLabs
- **Voice**: Natural-sounding AI voice
- **Text Length Limit**: 2000 characters (truncated with "..." if longer)
- **Text Cleaning**: Removes markdown, code blocks, and special formatting

---

## Knowledge Base

**File**: [api/knowledge.php](api/knowledge.php)

### Search Web for Information (RAG)

**Endpoint**: `POST /api/knowledge.php`

**Description**: Performs web search using SerpAPI and returns relevant information for Retrieval-Augmented Generation (RAG).

**Request Body**:
```json
{
  "query": "Latest developments in quantum computing",
  "num_results": 5
}
```

**Response**:
```json
{
  "success": true,
  "results": [
    {
      "title": "Quantum Computing Breakthrough 2026",
      "snippet": "Scientists have achieved...",
      "link": "https://example.com/article",
      "source": "Science Daily"
    }
  ],
  "cached": false
}
```

**Features**:
- Web search integration via SerpAPI
- Result caching in database
- Deduplication of search results
- Source attribution for AI responses

---

## User Onboarding

**File**: [api/user_onboarding.php](api/user_onboarding.php)

### Save Onboarding Data

**Endpoint**: `POST /api/user_onboarding.php`

**Request Body**:
```json
{
  "field_of_study": "Computer Science",
  "learning_goals": "Improve programming skills",
  "age_group": "18-24",
  "experience_level": "intermediate",
  "preferred_subjects": ["Math", "Science", "Programming"],
  "study_schedule": "evening"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Onboarding data saved successfully"
}
```

### Get Onboarding Status

**Endpoint**: `GET /api/user_onboarding.php`

**Response**:
```json
{
  "success": true,
  "completed": true,
  "data": {
    "field_of_study": "Computer Science",
    "learning_goals": "Improve programming skills"
  }
}
```

---

## Analytics

**File**: [api/analytics.php](api/analytics.php)

### Track User Activity

**Endpoint**: `POST /api/analytics.php`

**Request Body**:
```json
{
  "event": "message_sent",
  "metadata": {
    "conversation_id": 123,
    "message_length": 150,
    "has_file": false
  }
}
```

**Response**:
```json
{
  "success": true,
  "message": "Event tracked"
}
```

### Get User Analytics

**Endpoint**: `GET /api/analytics.php`

**Response**:
```json
{
  "success": true,
  "analytics": {
    "total_messages": 245,
    "total_conversations": 18,
    "average_messages_per_day": 12,
    "most_active_subject": "Mathematics",
    "learning_streak": 7
  }
}
```

---

## Learning Strategies

**File**: [api/learning_strategies.php](api/learning_strategies.php)

### Get Personalized Learning Strategy

**Endpoint**: `GET /api/learning_strategies.php?subject=Mathematics`

**Parameters**:
- `subject` (string, optional) - The subject to get strategies for

**Response**:
```json
{
  "success": true,
  "strategies": [
    {
      "title": "Spaced Repetition",
      "description": "Review material at increasing intervals",
      "effectiveness": "high",
      "recommended_for": ["Mathematics", "Languages"]
    },
    {
      "title": "Active Recall",
      "description": "Test yourself frequently on material",
      "effectiveness": "very high",
      "recommended_for": ["All subjects"]
    }
  ]
}
```

---

## Session Context

**File**: [api/session_context.php](api/session_context.php)

### Save Session Context

**Endpoint**: `POST /api/session_context.php`

**Description**: Saves user context for maintaining conversation state across sessions.

**Request Body**:
```json
{
  "current_topic": "Calculus derivatives",
  "difficulty_level": "intermediate",
  "last_question": "What is the chain rule?",
  "follow_up_needed": true
}
```

**Response**:
```json
{
  "success": true,
  "message": "Context saved"
}
```

### Get Session Context

**Endpoint**: `GET /api/session_context.php`

**Response**:
```json
{
  "success": true,
  "context": {
    "current_topic": "Calculus derivatives",
    "difficulty_level": "intermediate",
    "last_question": "What is the chain rule?",
    "follow_up_needed": true
  }
}
```

---

## Account Management

### Delete Account

**File**: [api/delete_account.php](api/delete_account.php)

**Endpoint**: `POST /api/delete_account.php`

**Description**: Permanently deletes the user's account and all associated data.

**Request Body**:
```json
{
  "confirm": true,
  "password": "user_password"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Account deleted successfully"
}
```

**Data Deleted**:
- User account
- All conversations
- All messages
- User settings
- Session tokens
- Analytics data

---

## Database Schema

### Tables

#### `users`
Stores user account information and authentication details.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | User ID |
| `username` | VARCHAR(50) | Unique username |
| `email` | VARCHAR(100) | User email address |
| `password_hash` | VARCHAR(255) | Hashed password (Argon2ID) |
| `first_name` | VARCHAR(50) | User's first name |
| `last_name` | VARCHAR(50) | User's last name |
| `google_id` | VARCHAR(255) | Google OAuth ID (nullable) |
| `created_at` | TIMESTAMP | Account creation timestamp |
| `updated_at` | TIMESTAMP | Last update timestamp |
| `learning_level` | ENUM | `primary`, `secondary`, `university`, `professional` |
| `response_style` | ENUM | `concise`, `detailed`, `step-by-step` |

**Indexes**:
- PRIMARY KEY (`id`)
- UNIQUE KEY (`username`)
- UNIQUE KEY (`email`)
- INDEX (`google_id`)

#### `conversations`
Stores metadata for each conversation/chat session.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Conversation ID |
| `user_id` | INT (FK) | References `users.id` |
| `title` | VARCHAR(255) | Conversation title (auto-generated or custom) |
| `created_at` | TIMESTAMP | Creation timestamp |
| `updated_at` | TIMESTAMP | Last message timestamp |

**Indexes**:
- PRIMARY KEY (`id`)
- INDEX (`user_id`, `updated_at`) - For fetching user's recent conversations

#### `messages`
Stores individual messages in conversations.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Message ID |
| `conversation_id` | INT (FK) | References `conversations.id` |
| `role` | ENUM | `user`, `model` |
| `content` | TEXT | JSON-encoded message parts |
| `content_html` | TEXT | Cached HTML-formatted response (nullable) |
| `created_at` | TIMESTAMP | Message timestamp |

**Indexes**:
- PRIMARY KEY (`id`)
- INDEX (`conversation_id`, `created_at`) - For fetching messages in order

**Performance Optimization**:
- `content_html` column caches formatted HTML to avoid re-parsing Markdown on every load

#### `user_settings`
Stores user preferences and accessibility settings.

| Column | Type | Description |
|--------|------|-------------|
| `user_id` | INT (PK, FK) | References `users.id` |
| `dark_mode` | BOOLEAN | Dark mode enabled |
| `font_size` | ENUM | `small`, `medium`, `large` |
| `chat_density` | ENUM | `compact`, `comfortable`, `spacious` |
| `legibility` | INT | Legibility scale (80-150) |
| `email_notifications` | BOOLEAN | Email notifications enabled |
| `study_reminders` | BOOLEAN | Study reminders enabled |
| `feature_announcements` | BOOLEAN | Feature announcements enabled |
| `weekly_summary` | BOOLEAN | Weekly summary enabled |
| `data_sharing` | BOOLEAN | Analytics data sharing |

#### `user_tokens`
Stores "remember me" persistent login tokens.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Token ID |
| `user_id` | INT (FK) | References `users.id` |
| `token` | VARCHAR(64) | SHA256 hashed token |
| `expires_at` | TIMESTAMP | Token expiration |
| `created_at` | TIMESTAMP | Creation timestamp |

**Security**:
- Tokens are hashed using SHA256
- Automatic expiration
- Single-use tokens (invalidated after use)

#### `knowledge_base`
Caches web search results for RAG system.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Entry ID |
| `query` | VARCHAR(255) | Search query |
| `results` | TEXT | JSON-encoded search results |
| `created_at` | TIMESTAMP | Cache timestamp |
| `expires_at` | TIMESTAMP | Cache expiration |

**Indexes**:
- PRIMARY KEY (`id`)
- INDEX (`query`, `expires_at`) - For cache lookups

#### `login_attempts`
Tracks failed login attempts for rate limiting.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Attempt ID |
| `ip_address` | VARCHAR(45) | IPv4 or IPv6 address |
| `username` | VARCHAR(50) | Attempted username |
| `attempt_time` | TIMESTAMP | Attempt timestamp |
| `success` | BOOLEAN | Whether attempt succeeded |

**Indexes**:
- PRIMARY KEY (`id`)
- INDEX (`ip_address`, `attempt_time`)
- INDEX (`username`, `attempt_time`)

#### `feedback`
Stores user feedback for model improvement.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK) | Feedback ID |
| `user_id` | INT (FK) | References `users.id` |
| `message_id` | INT (FK) | References `messages.id` (nullable) |
| `rating` | INT | Rating (1-5) |
| `comment` | TEXT | User comment |
| `created_at` | TIMESTAMP | Feedback timestamp |

---

## Error Handling

### Standard Error Response Format

All API endpoints return errors in a consistent format:

```json
{
  "success": false,
  "error": "Human-readable error message"
}
```

### HTTP Status Codes

| Code | Meaning | Usage |
|------|---------|-------|
| `200` | OK | Request successful |
| `400` | Bad Request | Missing or invalid parameters |
| `401` | Unauthorized | Authentication required |
| `403` | Forbidden | Insufficient permissions |
| `404` | Not Found | Resource not found |
| `405` | Method Not Allowed | Invalid HTTP method |
| `429` | Too Many Requests | Rate limit exceeded |
| `500` | Internal Server Error | Server-side error |

### Common Error Scenarios

**Authentication Errors**:
```json
{
  "success": false,
  "error": "You must be logged in to access this resource"
}
```

**Validation Errors**:
```json
{
  "success": false,
  "error": "Invalid input: email is required"
}
```

**Rate Limiting**:
```json
{
  "success": false,
  "error": "Too many login attempts. Please try again in 15 minutes."
}
```

**API Quota Exceeded**:
```json
{
  "success": false,
  "error": "API quota exceeded. Please try again later."
}
```

---

## Rate Limiting

### Login Attempts

**File**: [rate_limiter.php](rate_limiter.php)

**Rules**:
- Maximum 5 failed login attempts per IP address within 15 minutes
- Maximum 5 failed login attempts per username within 15 minutes
- Lockout duration: 15 minutes

**Implementation**:
- Tracks attempts in `login_attempts` table
- Automatically clears old attempts after 15 minutes
- Returns `429 Too Many Requests` when limit exceeded

### API Request Limits

**Future Enhancement**: Implement per-user API request limits to prevent abuse.

---

## Security Features

### CSRF Protection

**File**: [csrf.php](csrf.php)

All POST requests require a valid CSRF token.

**Token Generation**:
```php
$csrf_token = generateCsrfToken();
```

**Token Validation**:
```php
validateCsrfToken($_POST['csrf_token']);
```

### SQL Injection Prevention

All database queries use PDO prepared statements:

```php
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
```

### Password Security

- Hashing algorithm: **Argon2ID** (strongest available in PHP)
- Passwords never stored in plain text
- Automatic salting during hashing

### XSS Prevention

- All user input sanitized with `htmlspecialchars()`
- Markdown content parsed safely with Parsedown library
- Code blocks properly escaped in HTML output

---

## Performance Optimizations

### Database Optimizations

1. **Singleton Pattern**: Single database connection per request
2. **Prepared Statements**: Cached query execution plans
3. **Indexes**: Strategic indexes on frequently queried columns
4. **Message HTML Caching**: Avoid re-parsing Markdown

### API Optimizations

1. **Lazy Loading**: Fetch only 15 recent messages initially
2. **Image Data Stripping**: Remove heavy base64 images from history
3. **WebP Format**: 30% smaller images than JPEG
4. **Debug Logging Disabled**: Saves disk I/O in production

### Frontend Optimizations

1. **MathJax**: On-demand loading for equations
2. **Code Highlighting**: Lazy-loaded syntax highlighting
3. **Progressive Loading**: Load older messages on scroll

---

## Debugging

### Debug Mode

Set `DEBUG_MODE = true` in [server_mysql.php](server_mysql.php#L15) to enable detailed logging.

**Debug Logs Location**:
- PHP error log (configured in `php.ini`)
- Custom log file: `debug_log.txt` (if configured)

### Common Debugging Steps

1. **Check PHP Error Log**:
   ```bash
   tail -f /path/to/php/error.log
   ```

2. **Enable MySQL Query Logging**:
   ```sql
   SET GLOBAL general_log = 'ON';
   SET GLOBAL general_log_file = '/var/log/mysql/query.log';
   ```

3. **Browser Console**:
   - Check for JavaScript errors
   - Monitor network requests
   - Inspect API responses

---

## API Versioning

**Current Version**: v1 (implicit)

**Future Considerations**:
- Implement versioned endpoints (e.g., `/api/v2/image.php`)
- Maintain backward compatibility
- Deprecation notices for old endpoints

---

## Support

For API-related questions or issues:
- GitHub Issues: https://github.com/yourusername/TutorMind/issues
- Email: api-support@tutormind.example.com

---

**Last Updated**: January 2026
**Maintained By**: TutorMind Development Team
