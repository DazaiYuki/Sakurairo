# Enhanced Captcha Support

This theme now supports multiple captcha platforms to provide better security and user experience options.

## Supported Captcha Platforms

### 1. Built-in Mathematical Captcha (Enhanced)
- Simple addition and subtraction (existing)
- **NEW**: Multiplication problems (2×3=?)
- **NEW**: Number sequences (5,7,9,?)
- **NEW**: Logic questions (How many days in a week?)
- **NEW**: Mixed arithmetic (2+2×2=?)

### 2. Google reCAPTCHA v2
- Traditional "I'm not a robot" checkbox
- Requires site key and secret key from Google
- Fully responsive with language support

### 3. Google reCAPTCHA v3
- Invisible background verification
- Score-based validation (configurable threshold)
- Requires site key and secret key from Google
- Action-based verification for different forms

### 4. hCaptcha
- Privacy-focused alternative to reCAPTCHA
- Supports light/dark themes and normal/compact sizes
- Requires site key and secret key from hCaptcha

### 5. Cloudflare Turnstile
- Free alternative to reCAPTCHA
- Modern, user-friendly interface
- Supports auto/light/dark themes and normal/compact sizes
- Requires site key and secret key from Cloudflare

### 6. Vaptcha (Existing)
- Existing support maintained
- Scene-based configuration

## Configuration

All captcha platforms can be configured through the WordPress admin panel:
1. Go to **Appearance > Theme Options**
2. Navigate to the **Login/Register** section
3. Select your preferred **Captcha Selection**
4. Configure the required keys and settings for your chosen platform

## Implementation Details

- All captcha platforms work on login, registration, lost password, and comment forms
- Automatic fallback handling for misconfigured platforms
- Responsive design with proper styling
- Multi-language support for error messages
- Secure server-side validation for all platforms

## Security Features

- Input validation and sanitization
- Rate limiting through platform providers
- IP-based verification support
- Timeout handling for all requests
- Proper error reporting without exposing sensitive data

## Developer Notes

Each captcha platform is implemented as a separate class in `/inc/classes/`:
- `Captcha.php` - Enhanced built-in captcha
- `ReCaptchaV2.php` - Google reCAPTCHA v2
- `ReCaptchaV3.php` - Google reCAPTCHA v3
- `HCaptcha.php` - hCaptcha implementation
- `TurnstileCaptcha.php` - Cloudflare Turnstile
- `Vaptcha.php` - Vaptcha (existing)

All classes follow the same interface pattern for consistency and maintainability.