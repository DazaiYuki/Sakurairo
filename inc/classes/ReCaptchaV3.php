<?php

namespace Sakura\API;

class ReCaptchaV3
{
    private $siteKey;
    private $secretKey;
    private $minScore;

    public function __construct()
    {
        $this->siteKey = iro_opt('recaptcha_v3_site_key');
        $this->secretKey = iro_opt('recaptcha_v3_secret_key');
        $this->minScore = (float) (iro_opt('recaptcha_v3_min_score') ?: 0.5);
    }

    /**
     * Generate HTML for reCAPTCHA v3 (hidden field)
     *
     * @return string
     */
    public function html(): string
    {
        if (empty($this->siteKey)) {
            return '<div class="recaptcha-error">reCAPTCHA v3 site key not configured</div>';
        }

        return '<input type="hidden" id="recaptcha-v3-token" name="g-recaptcha-response">';
    }

    /**
     * Generate JavaScript for reCAPTCHA v3
     *
     * @param string $action
     * @return string
     */
    public function script(string $action = 'login'): string
    {
        if (empty($this->siteKey)) {
            return '';
        }

        $lang = get_locale();
        $lang = str_replace('_', '-', $lang);
        
        return <<<JS
        <script src="https://www.google.com/recaptcha/api.js?render={$this->siteKey}&hl={$lang}"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            function executeRecaptcha() {
                grecaptcha.ready(function() {
                    grecaptcha.execute('{$this->siteKey}', {action: '{$action}'}).then(function(token) {
                        var tokenField = document.getElementById('recaptcha-v3-token');
                        if (tokenField) {
                            tokenField.value = token;
                        }
                        // Also set any other g-recaptcha-response fields
                        var otherFields = document.querySelectorAll('input[name="g-recaptcha-response"]');
                        otherFields.forEach(function(field) {
                            field.value = token;
                        });
                    });
                });
            }
            
            executeRecaptcha();
            
            // Re-execute before form submission
            var forms = document.querySelectorAll('form');
            forms.forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    executeRecaptcha();
                    setTimeout(function() {
                        form.submit();
                    }, 500);
                });
            });
        });
        </script>
        JS;
    }

    /**
     * Verify reCAPTCHA v3 response
     *
     * @param string $response
     * @param string $remoteIp
     * @param string $action
     * @return array
     */
    public function verify(string $response, string $remoteIp = '', string $action = 'login'): array
    {
        if (empty($this->secretKey)) {
            return [
                'success' => false,
                'score' => 0,
                'action' => $action,
                'error' => __('reCAPTCHA v3 secret key not configured', 'sakurairo')
            ];
        }

        if (empty($response)) {
            return [
                'success' => false,
                'score' => 0,
                'action' => $action,
                'error' => __('reCAPTCHA v3 token is missing', 'sakurairo')
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
                'score' => 0,
                'action' => $action,
                'error' => __('reCAPTCHA v3 verification failed: network error', 'sakurairo')
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (!$result || !isset($result['success'])) {
            return [
                'success' => false,
                'score' => 0,
                'action' => $action,
                'error' => __('reCAPTCHA v3 verification failed: invalid response', 'sakurairo')
            ];
        }

        $score = isset($result['score']) ? (float) $result['score'] : 0;
        $resultAction = isset($result['action']) ? $result['action'] : '';

        if (!$result['success']) {
            $errorCodes = isset($result['error-codes']) ? $result['error-codes'] : [];
            $errorMsg = $this->getErrorMessage($errorCodes);
            
            return [
                'success' => false,
                'score' => $score,
                'action' => $resultAction,
                'error' => $errorMsg
            ];
        }

        // Check action matches
        if ($resultAction !== $action) {
            return [
                'success' => false,
                'score' => $score,
                'action' => $resultAction,
                'error' => __('reCAPTCHA v3 action mismatch', 'sakurairo')
            ];
        }

        // Check score meets minimum threshold
        if ($score < $this->minScore) {
            return [
                'success' => false,
                'score' => $score,
                'action' => $resultAction,
                'error' => sprintf(__('reCAPTCHA v3 score too low: %s (minimum: %s)', 'sakurairo'), $score, $this->minScore)
            ];
        }

        return [
            'success' => true,
            'score' => $score,
            'action' => $resultAction,
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
            return __('reCAPTCHA v3 verification failed', 'sakurairo');
        }

        $errorCode = $errorCodes[0];
        return isset($messages[$errorCode]) ? $messages[$errorCode] : __('reCAPTCHA v3 verification failed', 'sakurairo');
    }
}