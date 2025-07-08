<?php

namespace Sakura\API;

class TurnstileCaptcha
{
    private $siteKey;
    private $secretKey;

    public function __construct()
    {
        $this->siteKey = iro_opt('turnstile_site_key');
        $this->secretKey = iro_opt('turnstile_secret_key');
    }

    /**
     * Generate HTML for Turnstile widget
     *
     * @return string
     */
    public function html(): string
    {
        if (empty($this->siteKey)) {
            return '<div class="turnstile-error">Turnstile site key not configured</div>';
        }

        $theme = iro_opt('turnstile_theme') ?: 'auto';
        $size = iro_opt('turnstile_size') ?: 'normal';

        return <<<HTML
        <div class="cf-turnstile" data-sitekey="{$this->siteKey}" data-theme="{$theme}" data-size="{$size}"></div>
        HTML;
    }

    /**
     * Generate JavaScript for Turnstile
     *
     * @return string
     */
    public function script(): string
    {
        return <<<JS
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        JS;
    }

    /**
     * Verify Turnstile response
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
                'error' => __('Turnstile secret key not configured', 'sakurairo')
            ];
        }

        if (empty($response)) {
            return [
                'success' => false,
                'error' => __('Please complete the Turnstile verification', 'sakurairo')
            ];
        }

        $data = [
            'secret' => $this->secretKey,
            'response' => $response
        ];

        if (!empty($remoteIp)) {
            $data['remoteip'] = $remoteIp;
        }

        $response = wp_safe_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'timeout' => 15,
            'body' => $data
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => __('Turnstile verification failed: network error', 'sakurairo')
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (!$result || !isset($result['success'])) {
            return [
                'success' => false,
                'error' => __('Turnstile verification failed: invalid response', 'sakurairo')
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
            'timeout-or-duplicate' => __('The response parameter has already been validated before', 'sakurairo'),
            'internal-error' => __('An internal error happened while validating the response', 'sakurairo'),
            'invalid-widget-id' => __('The widget ID extracted from the parsed site secret key was invalid or did not exist', 'sakurairo'),
            'invalid-parsed-secret' => __('The secret key parameter was invalid or did not exist', 'sakurairo')
        ];

        if (empty($errorCodes)) {
            return __('Turnstile verification failed', 'sakurairo');
        }

        $errorCode = $errorCodes[0];
        return isset($messages[$errorCode]) ? $messages[$errorCode] : __('Turnstile verification failed', 'sakurairo');
    }
}