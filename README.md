# TutorMind

> AI-Powered Personal Tutoring Platform - Your 24/7 Learning Companion

TutorMind is an intelligent educational platform that provides personalized tutoring and learning assistance across all subjects. Built with PHP and powered by Google Gemini AI, it offers real-time explanations, step-by-step problem solving, and multi-modal learning support.

## Features

- **AI-Powered Chat Interface** - Real-time conversations with AI tutors for instant help
- **Multi-Modal Input Support** - Text, images, PDFs, Word documents, and PowerPoint files
- **Image Generation** - AI-powered visual content creation using Google Imagen
- **Text-to-Speech** - Audio responses for accessibility and auditory learners
- **Knowledge Base (RAG)** - Web search integration for up-to-date information
- **Learning Analytics** - Track your progress, learning patterns, and performance
- **Personalized Onboarding** - 9-step wizard to customize your learning experience
- **User Authentication** - Google OAuth and traditional login/registration
- **Conversation History** - Persistent message storage for continuous learning sessions
- **Dark Mode & Accessibility** - Customizable themes and legibility settings
- **Mathematical Equation Rendering** - MathJax support for STEM subjects

## Technology Stack

### Frontend
- HTML5, CSS3, JavaScript (ES6+)
- MathJax for mathematical notation
- Markdown rendering with syntax highlighting
- Responsive design with mobile support

### Backend
- PHP 7.4+
- MySQL/MariaDB database
- PDO with prepared statements
- Composer for dependency management

### External APIs
- **Google Gemini API** - Primary AI model for tutoring
- **Google Imagen** - AI image generation
- **ElevenLabs** - Text-to-speech functionality
- **SerpAPI** - Web search capabilities
- **DeepSeek** - Alternative AI model support

### PHP Libraries
- `erusev/parsedown` - Markdown parsing
- `smalot/pdfparser` - PDF text extraction
- `phpoffice/phpword` - Word document processing
- `phpoffice/phppresentation` - PowerPoint processing

## Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache web server (with mod_rewrite enabled)
- Composer (PHP dependency manager)
- XAMPP, WAMP, or similar local development environment

### Step 1: Clone the Repository

```bash
git clone https://github.com/yourusername/TutorMind.git
cd TutorMind
```

### Step 2: Install Dependencies

```bash
composer install
```

### Step 3: Configure Database

1. Create a MySQL database:
```sql
CREATE DATABASE tutormind CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import the database schema:
```bash
mysql -u root -p tutormind < database/schema.sql
```

3. Run migrations:
```bash
# Apply all migrations in order
mysql -u root -p tutormind < migrations/alter_users_table.sql
mysql -u root -p tutormind < migrations/001_add_user_settings_and_personalization.sql
mysql -u root -p tutormind < migrations/002_split_user_names.sql
# Continue with remaining migrations...
```

### Step 4: Configure Environment

1. Copy the configuration template:
```bash
cp config-sql.ini.example config-sql.ini
```

2. Edit `config-sql.ini` with your credentials:
```ini
; API Keys
GEMINI_API_KEY = "your_gemini_api_key_here"
SERP_API_KEY = "your_serpapi_key_here"
ELEVEN_LABS_API = "your_elevenlabs_key_here"
DEEPSEEK_API_KEY = "your_deepseek_key_here"
QUIZ_API_KEY = "your_quiz_api_key_here"

[database]
host = "127.0.0.1"
port = "3306"
dbname = "tutormind"
user = "your_db_user"
password = "your_db_password"
driver = "mysql"
```

### Step 5: Configure Apache

Ensure `.htaccess` is enabled and mod_rewrite is active. Your virtual host should point to the project root directory.

Example Apache configuration:
```apache
<VirtualHost *:80>
    ServerName tutormind.local
    DocumentRoot "C:/xampp/htdocs/TutorMind"

    <Directory "C:/xampp/htdocs/TutorMind">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Step 6: Set Permissions

Ensure the web server has write permissions for data storage:
```bash
chmod -R 755 data/
chmod -R 755 migrations/
```

### Step 7: Access the Application

1. Start your web server (Apache, MySQL)
2. Navigate to `http://localhost/TutorMind` or your configured domain
3. You should see the landing page
4. Create an account or login with Google OAuth

## API Keys Setup

### Required API Keys

#### 1. Google Gemini API
- Visit: https://makersuite.google.com/app/apikey
- Create a new API key
- Add to `config-sql.ini` as `GEMINI_API_KEY`

#### 2. SerpAPI (Web Search)
- Visit: https://serpapi.com/
- Sign up for a free account (100 searches/month)
- Add to `config-sql.ini` as `SERP_API_KEY`

#### 3. ElevenLabs (Text-to-Speech)
- Visit: https://elevenlabs.io/
- Create account and get API key
- Add to `config-sql.ini` as `ELEVEN_LABS_API`

#### 4. DeepSeek (Optional - Alternative AI)
- Visit: https://platform.deepseek.com/
- Generate API key
- Add to `config-sql.ini` as `DEEPSEEK_API_KEY`

## Project Structure

```
TutorMind/
├── index.html              # Landing page
├── login.html              # Login page
├── register.html           # Registration page
├── tutor_mysql.php         # Main chat interface
├── server_mysql.php        # Core backend API
├── auth_mysql.php          # Authentication logic
├── db_mysql.php            # Database connection (singleton)
├── check_auth.php          # Session middleware
├── csrf.php                # CSRF protection
├── rate_limiter.php        # Login rate limiting
├── onboarding.php          # User onboarding wizard
├── config-sql.ini          # Configuration (DO NOT COMMIT)
│
├── /api/                   # Microservices
│   ├── image.php          # AI image generation
│   ├── tts.php            # Text-to-speech
│   ├── knowledge.php      # Web search + RAG
│   ├── learning_strategies.php
│   ├── user_settings.php
│   ├── analytics.php
│   ├── session_context.php
│   └── clear_history.php
│
├── /migrations/            # Database migrations
│   ├── alter_users_table.sql
│   ├── 001_add_user_settings_and_personalization.sql
│   └── 002_split_user_names.sql
│
├── /admin/                 # Admin features
│   └── feedback.php        # User feedback dashboard
│
├── /vendor/                # Composer dependencies
├── /assets/                # Images and media
├── /data/                  # User data storage
│
├── style.css               # Main stylesheet
├── landing.css             # Landing page styles
├── ui-overhaul.css         # Chat interface styles
├── onboarding-wizard.css   # Onboarding styles
├── tutor_mysql.js          # Chat client logic
├── landing.js              # Landing page behavior
└── quick-start.js          # Onboarding logic
```

## Database Schema

### Core Tables

- **users** - User accounts, authentication, profile information
- **conversations** - Chat session metadata
- **messages** - Individual messages (user + AI responses)
- **user_settings** - Theme preferences, accessibility settings
- **user_tokens** - Remember-me persistent tokens
- **knowledge_base** - RAG system cache for web search results
- **login_attempts** - Rate limiting tracking
- **feedback** - User feedback for model improvement

For detailed schema information, see [API_DOCUMENTATION.md](API_DOCUMENTATION.md).

## Usage

### Basic Usage

1. **Sign Up/Login** - Create an account or use Google OAuth
2. **Complete Onboarding** - Tell TutorMind about your learning goals
3. **Ask Questions** - Type your question in the chat interface
4. **Upload Files** - Attach PDFs, Word docs, PowerPoint files, or images
5. **Generate Images** - Ask TutorMind to create visual content
6. **Listen to Responses** - Use text-to-speech for audio learning
7. **Review History** - Access past conversations anytime

### Example Queries

- "Explain photosynthesis step by step"
- "Help me solve this calculus problem: ∫(2x + 3)dx"
- "What's the difference between mitosis and meiosis?"
- "Generate an image of a medieval castle"
- "Summarize this PDF" (with uploaded file)
- "Create a study guide for the American Revolution"

## Configuration

### Debug Mode

In `server_mysql.php`, set `DEBUG_MODE` to enable/disable logging:
```php
define('DEBUG_MODE', false); // Set to true for development
```

### Performance Tuning

- **Execution Time**: Modify `set_time_limit(300)` in [server_mysql.php](server_mysql.php#L11)
- **Database Connection**: Configure PDO settings in [db_mysql.php](db_mysql.php)
- **Caching**: Enable message caching in migrations

### Security Settings

- **CSRF Protection**: Automatically enabled for all forms
- **Rate Limiting**: Configure in [rate_limiter.php](rate_limiter.php)
- **Session Timeout**: Adjust in PHP configuration

## Development

### Running Locally

1. Start XAMPP/WAMP control panel
2. Start Apache and MySQL services
3. Navigate to `http://localhost/TutorMind`

### Debugging

- PHP errors: Check `error_log` in your PHP configuration directory
- Database queries: Enable query logging in MySQL
- Frontend: Use browser DevTools console

### Code Style

- Follow PSR-12 coding standards for PHP
- Use meaningful variable names
- Comment complex logic
- Keep functions focused and single-purpose

## Security Considerations

- **Never commit `config-sql.ini`** - Contains sensitive API keys
- Use prepared statements for all database queries
- Validate and sanitize all user inputs
- Implement CSRF tokens on all forms
- Use HTTPS in production
- Regularly update dependencies
- Rotate API keys periodically
- Implement rate limiting on all API endpoints

## Troubleshooting

### Common Issues

**Database Connection Failed**
- Verify MySQL is running
- Check credentials in `config-sql.ini`
- Ensure database exists and is accessible

**API Errors**
- Verify API keys are correct in `config-sql.ini`
- Check API rate limits and quotas
- Review error logs for specific error messages

**File Upload Issues**
- Check PHP `upload_max_filesize` and `post_max_size`
- Verify write permissions on `/data` directory
- Ensure file types are allowed

**Composer Dependencies Missing**
- Run `composer install` in project root
- Check PHP version compatibility (>= 7.4)

## Contributing

We welcome contributions! Please read [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on:
- Code of conduct
- Submitting bug reports
- Proposing new features
- Code review process

## License

This project is proprietary software. All rights reserved.

## Support

For issues and questions:
- Create an issue in the GitHub repository
- Contact: support@tutormind.example.com

## Roadmap

- [ ] Mobile native apps (iOS/Android)
- [ ] Real-time collaboration features
- [ ] Advanced analytics dashboard
- [ ] Multi-language support
- [ ] Offline mode
- [ ] Voice input for questions
- [ ] Gamification and achievements
- [ ] Teacher/parent dashboard

## Acknowledgments

- Google Gemini AI for powering the tutoring engine
- ElevenLabs for natural-sounding text-to-speech
- The open-source community for excellent PHP libraries

---

Built with dedication to making quality education accessible to everyone.
