# Multi-AI Assistant

A modern web-based AI assistant that supports multiple AI providers: ChatGPT (OpenAI), Claude (Anthropic), Gemini (Google), and Copilot (Azure OpenAI).

## Features

✨ **Multiple AI Providers**
- OpenAI ChatGPT (GPT-4o-mini)
- Anthropic Claude (Sonnet 4)
- Google Gemini (1.5 Flash)
- Microsoft Copilot (via Azure OpenAI)

🎨 **Modern UI/UX**
- Beautiful gradient design
- Responsive layout (mobile & desktop)
- Smooth animations
- Real-time typing indicators
- Message history with provider badges

🔒 **Security Features**
- Rate limiting (20 requests/minute)
- Input sanitization
- Session management
- CSRF protection ready

💬 **Smart Conversation**
- Maintains conversation context
- Provider-specific formatting
- Error handling with user-friendly messages
- Clear chat functionality

## Requirements

- PHP 7.4 or higher
- cURL extension enabled
- Web server (Apache/Nginx)
- API keys for your chosen AI providers

## Installation

### 1. Clone or Download Files

Place all files in your web server directory (e.g., `/var/www/html/` or `htdocs/`).

### 2. Configure API Keys

Open `chat.php` and add your API keys:

```php
// Line 11-13
define('OPENAI_API_KEY', 'sk-your-actual-openai-key');
define('ANTHROPIC_API_KEY', 'sk-ant-your-actual-anthropic-key');
define('GOOGLE_API_KEY', 'AIza-your-actual-google-key');
```

### 3. Get API Keys

#### OpenAI (ChatGPT)
1. Go to https://platform.openai.com/api-keys
2. Sign up or log in
3. Click "Create new secret key"
4. Copy the key (starts with `sk-`)

#### Anthropic (Claude)
1. Go to https://console.anthropic.com/
2. Sign up or log in
3. Navigate to API Keys
4. Create a new key (starts with `sk-ant-`)

#### Google (Gemini)
1. Go to https://aistudio.google.com/app/apikey
2. Sign in with Google account
3. Click "Create API Key"
4. Copy the key (starts with `AIza`)

#### Microsoft Copilot (Optional)
Requires Azure OpenAI Service subscription:
1. Go to https://portal.azure.com/
2. Create an Azure OpenAI resource
3. Deploy a model
4. Get endpoint and API key

### 4. Set Permissions

Ensure PHP can write to the session directory:

```bash
chmod 755 /path/to/your/project
```

### 5. Configure PHP (if needed)

Enable required extensions in `php.ini`:

```ini
extension=curl
extension=json
session.auto_start = 0
```

## Usage

### Basic Usage

1. Open `index.html` in your web browser
2. Select an AI provider (ChatGPT, Claude, or Gemini)
3. Type your message
4. Press Enter or click Send

### Provider Selection

Click any provider button to switch:
- **🟢 ChatGPT**: Best for general tasks, coding, and creative writing
- **🟣 Claude**: Excellent for detailed analysis and long-form content
- **🔵 Gemini**: Great for multimodal tasks and fast responses
- **⚪ Copilot**: Requires Azure setup (enterprise option)

### Keyboard Shortcuts

- `Enter`: Send message
- `Shift + Enter`: New line in message
- Messages auto-resize as you type

### Clear Chat

Click the "Clear Chat" button to reset conversation history.

## File Structure

```
project/
├── index.html              # Main UI interface
├── chat.php               # Backend API handler
├── config.template.php    # API key template
└── README.md             # This file
```

## Customization

### Change Models

Edit the model names in `chat.php`:

```php
// OpenAI (line 70)
'model' => 'gpt-4o',  // or gpt-4o-mini, gpt-3.5-turbo

// Claude (line 107)
'model' => 'claude-3-5-sonnet-20241022',  // or other versions

// Gemini (line 144)
$url = '...models/gemini-1.5-pro:generateContent...';  // or gemini-1.5-flash
```

### Adjust Rate Limits

Modify in `chat.php` (line 14):

```php
define('MAX_REQUESTS_PER_MINUTE', 30);  // Increase limit
define('MAX_MESSAGE_LENGTH', 8000);     // Allow longer messages
```

### Customize Styling

Edit the `<style>` section in `index.html`:

```css
/* Change gradient colors */
background: linear-gradient(135deg, #your-color1 0%, #your-color2 100%);

/* Adjust container size */
max-width: 1200px;
max-height: 900px;
```

## API Cost Considerations

Each provider has different pricing:

### OpenAI
- GPT-4o-mini: ~$0.15/$0.60 per 1M tokens (input/output)
- GPT-4o: ~$2.50/$10 per 1M tokens

### Anthropic
- Claude Sonnet 4: ~$3/$15 per 1M tokens
- Claude Haiku: ~$0.25/$1.25 per 1M tokens

### Google
- Gemini 1.5 Flash: Free tier available, then ~$0.075/$0.30 per 1M tokens
- Gemini 1.5 Pro: ~$1.25/$5 per 1M tokens

**Recommendation**: Use rate limiting and message length restrictions to control costs.

## Troubleshooting

### "API key not configured" Error
- Ensure you've replaced placeholder API keys in `chat.php`
- Check that keys are copied correctly (no extra spaces)

### "Network error" Message
- Check PHP error logs: `tail -f /var/log/apache2/error.log`
- Verify cURL is enabled: `php -m | grep curl`
- Test API connectivity with curl from command line

### Rate Limit Issues
- Increase `MAX_REQUESTS_PER_MINUTE` in `chat.php`
- Clear browser cookies/session storage
- Wait 60 seconds and try again

### Provider-Specific Errors

**OpenAI**: 
- Verify billing is set up at https://platform.openai.com/account/billing
- Check API key permissions

**Claude**:
- Ensure you're using the correct API version header
- Verify account has API access enabled

**Gemini**:
- Check API key is enabled for Generative Language API
- Verify quota limits in Google Cloud Console
- Run `check_gemini_models.php` to see available models
- Try alternative models: `gemini-pro`, `gemini-1.5-pro-latest`
- Some models may not be available in all regions

### "Model not found" Error (Gemini)
- The code automatically falls back to `gemini-pro` if `gemini-1.5-flash-latest` is unavailable
- To check which models are available with your API key:
  1. Upload `check_gemini_models.php` to your server
  2. Add your API key to the file
  3. Access it via browser: `http://yoursite.com/check_gemini_models.php`
  4. Use one of the listed models in `chat.php`

## Security Best Practices

1. **Never commit API keys** to version control
2. Add `config.php` to `.gitignore`
3. Use environment variables for production:
   ```php
   define('OPENAI_API_KEY', getenv('OPENAI_API_KEY'));
   ```
4. Implement HTTPS in production
5. Add CSRF tokens for form submissions
6. Set up proper CORS headers
7. Monitor API usage and set billing alerts

## Advanced Features

### Add Conversation Export

```javascript
function exportChat() {
    const blob = new Blob([JSON.stringify(conversationHistory, null, 2)], 
        { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'chat-export.json';
    a.click();
}
```

### Implement User Authentication

```php
// Add to chat.php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
```

### Add Database Storage

```php
// Store conversations in MySQL
$pdo = new PDO('mysql:host=localhost;dbname=ai_chat', 'user', 'pass');
$stmt = $pdo->prepare("INSERT INTO messages (user_id, provider, message, response) VALUES (?, ?, ?, ?)");
```

## Browser Compatibility

- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

## License

This project is open source and available for personal and commercial use.

## Support

For issues or questions:
1. Check the troubleshooting section
2. Review API provider documentation
3. Check server error logs
4. Ensure all requirements are met

## Future Enhancements

- [ ] Voice input/output
- [ ] File upload support
- [ ] Image generation
- [ ] Conversation templates
- [ ] Multi-language support
- [ ] Dark/light theme toggle
- [ ] Conversation search
- [ ] User accounts and saved chats

## Credits

Built with:
- OpenAI API
- Anthropic Claude API
- Google Gemini API
- Pure PHP, HTML, CSS, JavaScript (no frameworks)

---

**Note**: This is a demonstration project. For production use, implement additional security measures, proper error logging, and database storage for conversation history.
