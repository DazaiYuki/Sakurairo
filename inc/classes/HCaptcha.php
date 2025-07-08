<?php

namespace Sakura\API;

class HCaptcha
{
    private $siteKey;
    private $secretKey;

    public function __construct()
    {
        $this->siteKey = iro_opt('hcaptcha_site_key');
        $this->secretKey = iro_opt('hcaptcha_secret_key');
    }

    /**
     * Generate HTML for hCaptcha widget
     *
     * @return string
     */
    public function html(): string
    {
        if (empty($this->siteKey)) {
            return '<div class="hcaptcha-error">hCaptcha site key not configured</div>';
        }

        $theme = iro_opt('hcaptcha_theme') ?: 'light';
        $size = iro_opt('hcaptcha_size') ?: 'normal';

        return <<<HTML
        <div class="h-captcha" data-sitekey="{$this->siteKey}" data-theme="{$theme}" data-size="{$size}"></div>
        HTML;
    }

    /**
     * Generate JavaScript for hCaptcha
     *
     * @return string
     */
    public function script(): string
    {
        $lang = get_locale();
        // Convert WordPress locale to hCaptcha language code
        $lang = str_replace('_', '-', $lang);
        
        return <<<JS
        <script src="https://js.hcaptcha.com/1/api.js?hl={$lang}" async defer></script>
        JS;
    }

    /**
     * Verify hCaptcha response
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
                'error' => __('hCaptcha secret key not configured', 'sakurairo')
            ];
        }

        if (empty($response)) {
            return [
                'success' => false,
                'error' => __('Please complete the hCaptcha verification', 'sakurairo')
            ];
        }

        $data = [
            'secret' => $this->secretKey,
            'response' => $response
        ];

        if (!empty($remoteIp)) {
            $data['remoteip'] = $remoteIp;
        }

        $response = wp_safe_remote_post('https://hcaptcha.com/siteverify', [
            'timeout' => 15,
            'body' => $data
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => __('hCaptcha verification failed: network error', 'sakurairo')
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (!$result || !isset($result['success'])) {
            return [
                'success' => false,
                'error' => __('hCaptcha verification failed: invalid response', 'sakurairo')
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
            'invalid-or-already-seen-response' => __('The response parameter has already been checked, or has another issue', 'sakurairo'),
            'not-using-dummy-passcode' => __('You have used a testing sitekey but have not used its matching secret', 'sakurairo'),
            'sitekey-secret-mismatch' => __('The sitekey is not registered with the provided secret', 'sakurairo')
        ];

        if (empty($errorCodes)) {
            return __('hCaptcha verification failed', 'sakurairo');
        }

        $errorCode = $errorCodes[0];
        return isset($messages[$errorCode]) ? $messages[$errorCode] : __('hCaptcha verification failed', 'sakurairo');
    }
}