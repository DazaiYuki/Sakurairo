<?php

namespace Sakura\API;

class ReCaptchaV2
{
    private $siteKey;
    private $secretKey;

    public function __construct()
    {
        $this->siteKey = iro_opt('recaptcha_v2_site_key');
        $this->secretKey = iro_opt('recaptcha_v2_secret_key');
    }

    /**
     * Generate HTML for reCAPTCHA v2 widget
     *
     * @return string
     */
    public function html(): string
    {
        if (empty($this->siteKey)) {
            return '<div class="recaptcha-error">reCAPTCHA site key not configured</div>';
        }

        return <<<HTML
        <div class="g-recaptcha" data-sitekey="{$this->siteKey}"></div>
        HTML;
    }

    /**
     * Generate JavaScript for reCAPTCHA v2
     *
     * @return string
     */
    public function script(): string
    {
        $lang = get_locale();
        // Convert WordPress locale to reCAPTCHA language code
        $lang = str_replace('_', '-', $lang);
        
        return <<<JS
        <script src="https://www.google.com/recaptcha/api.js?hl={$lang}" async defer></script>
        JS;
    }

    /**
     * Verify reCAPTCHA v2 response
     *
     * @param string $response
     * @param string $remoteIp
     * @return array
     */
    public function verify(string $response, string $remoteIp = ''): array
    {
        if (empty($this->secretKey)) {
            return [
                'success' => false,
                'error' => __('reCAPTCHA secret key not configured', 'sakurairo')
            ];
        }

        if (empty($response)) {
            return [
                'success' => false,
                'error' => __('Please complete the reCAPTCHA verification', 'sakurairo')
            ];
        }

        $data = [
            'secret' => $this->secretKey,
            'response' => $response
        ];

        if (!empty($remoteIp)) {
            $data['remoteip'] = $remoteIp;
        }

        $response = wp_safe_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'timeout' => 15,
            'body' => $data
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => __('reCAPTCHA verification failed: network error', 'sakurairo')
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (!$result || !isset($result['success'])) {
            return [
                'success' => false,
                'error' => __('reCAPTCHA verification failed: invalid response', 'sakurairo')
            ];
        }

        if (!$result['success']) {
            $errorCodes = isset($result['error-codes']) ? $result['error-codes'] : [];
            $errorMsg = $this->getErrorMessage($errorCodes);
            
            return [
                'success' => false,
                'error' => $errorMsg
            ];
        }

        return [
            'success' => true,
            'error' => ''
        ];
    }

    /**
     * Get human-readable error message from error codes
     *
     * @param array $errorCodes
     * @return string
     */
    private function getErrorMessage(array $errorCodes): string
    {
        $messages = [
            'missing-input-secret' => __('The secret parameter is missing', 'sakurairo'),
            'invalid-input-secret' => __('The secret parameter is invalid or malformed', 'sakurairo'),
            'missing-input-response' => __('The response parameter is missing', 'sakurairo'),
            'invalid-input-response' => __('The response parameter is invalid or malformed', 'sakurairo'),
            'bad-request' => __('The request is invalid or malformed', 'sakurairo'),
            'timeout-or-duplicate' => __('The response is no longer valid: either is too old or has been used previously', 'sakurairo')
        ];

        if (empty($errorCodes)) {
            return __('reCAPTCHA verification failed', 'sakurairo');
        }

        $errorCode = $errorCodes[0];
        return isset($messages[$errorCode]) ? $messages[$errorCode] : __('reCAPTCHA verification failed', 'sakurairo');
    }
}