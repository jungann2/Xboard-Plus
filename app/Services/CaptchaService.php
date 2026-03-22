<?php

namespace App\Services;

use App\Http\Controllers\V2\LocalCaptchaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use ReCaptcha\ReCaptcha;

class CaptchaService
{
    /**
     * 验证所有已启用的验证码（支持叠加，区分前台/后台）
     *
     * 配置值说明：
     * - 0: 关闭
     * - 1: 前台启用
     * - 2: 后台启用
     * - 3: 前台+后台启用
     *
     * @param Request $request
     * @param string $scene 场景: 'frontend' 前台, 'admin' 后台
     */
    public function verify(Request $request, string $scene = 'frontend'): array
    {
        // 1. 本地字符验证码
        if ($this->isEnabledForScene('local_captcha_char_enable', $scene)) {
            $result = $this->verifyLocalCaptcha($request, 'char');
            if (!$result[0]) return $result;
        }

        // 2. 本地算术验证码
        if ($this->isEnabledForScene('local_captcha_math_enable', $scene)) {
            $result = $this->verifyLocalCaptcha($request, 'math');
            if (!$result[0]) return $result;
        }

        // 3. 第三方验证码
        if ($this->isEnabledForScene('captcha_enable', $scene)) {
            $captchaType = admin_setting('captcha_type', 'recaptcha');
            $result = match ($captchaType) {
                'turnstile' => $this->verifyTurnstile($request),
                'recaptcha-v3' => $this->verifyRecaptchaV3($request),
                'recaptcha' => $this->verifyRecaptcha($request),
                default => [false, [400, __('Invalid captcha type')]]
            };
            if (!$result[0]) return $result;
        }

        return [true, null];
    }

    /**
     * 判断某个验证码是否在指定场景下启用
     * 配置值: 0=关闭, 1=前台, 2=后台, 3=前台+后台
     */
    private function isEnabledForScene(string $settingKey, string $scene): bool
    {
        $val = (int) admin_setting($settingKey, 0);
        if ($val === 0) return false;
        if ($val === 3) return true;
        if ($val === 1 && $scene === 'frontend') return true;
        if ($val === 2 && $scene === 'admin') return true;
        return false;
    }

    /**
     * 获取指定场景下需要展示的验证码类型列表（供前端查询）
     */
    public static function getEnabledTypes(string $scene): array
    {
        $types = [];

        $charVal = (int) admin_setting('local_captcha_char_enable', 0);
        if ($charVal === 3 || ($charVal === 1 && $scene === 'frontend') || ($charVal === 2 && $scene === 'admin')) {
            $types[] = [
                'type' => 'char',
                'charset' => admin_setting('local_captcha_charset', 'mixed'),
                'length' => (int) admin_setting('local_captcha_length', 4),
            ];
        }

        $mathVal = (int) admin_setting('local_captcha_math_enable', 0);
        if ($mathVal === 3 || ($mathVal === 1 && $scene === 'frontend') || ($mathVal === 2 && $scene === 'admin')) {
            $types[] = ['type' => 'math'];
        }

        $thirdVal = (int) admin_setting('captcha_enable', 0);
        if ($thirdVal === 3 || ($thirdVal === 1 && $scene === 'frontend') || ($thirdVal === 2 && $scene === 'admin')) {
            $types[] = [
                'type' => 'third_party',
                'provider' => admin_setting('captcha_type', 'recaptcha'),
            ];
        }

        // 密保卡仅后台
        if ($scene === 'admin' && (int) admin_setting('security_card_enable', 0)) {
            $types[] = ['type' => 'security_card'];
        }

        return $types;
    }

    /**
     * 验证本地验证码
     */
    private function verifyLocalCaptcha(Request $request, string $type): array
    {
        $prefix = $type === 'math' ? 'math_' : 'char_';
        $captchaId = $request->input($prefix . 'captcha_id');
        $captchaInput = $request->input($prefix . 'captcha_input');

        if (empty($captchaId) || empty($captchaInput)) {
            $label = $type === 'math' ? '算术验证码' : '字符验证码';
            return [false, [400, $label . '不能为空']];
        }

        // 输入长度限制（验证码最长 10 位足够）
        if (strlen($captchaInput) > 10 || strlen($captchaId) > 64) {
            return [false, [400, '验证码参数无效']];
        }

        if (!LocalCaptchaController::verify($captchaId, $captchaInput)) {
            $label = $type === 'math' ? '算术验证码' : '字符验证码';
            return [false, [400, $label . '错误或已过期']];
        }

        return [true, null];
    }

    /**
     * 验证 Cloudflare Turnstile
     */
    private function verifyTurnstile(Request $request): array
    {
        $turnstileToken = $request->input('turnstile_token');
        if (!$turnstileToken) {
            return [false, [400, __('Invalid code is incorrect')]];
        }

        $response = Http::post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => admin_setting('turnstile_secret_key'),
            'response' => $turnstileToken,
            'remoteip' => $request->ip()
        ]);

        $result = $response->json();
        if (!$result['success']) {
            return [false, [400, __('Invalid code is incorrect')]];
        }

        return [true, null];
    }

    /**
     * 验证 Google reCAPTCHA v3
     */
    private function verifyRecaptchaV3(Request $request): array
    {
        $recaptchaV3Token = $request->input('recaptcha_v3_token');
        if (!$recaptchaV3Token) {
            return [false, [400, __('Invalid code is incorrect')]];
        }

        $recaptcha = new ReCaptcha(admin_setting('recaptcha_v3_secret_key'));
        $recaptchaResp = $recaptcha->verify($recaptchaV3Token, $request->ip());

        if (!$recaptchaResp->isSuccess()) {
            return [false, [400, __('Invalid code is incorrect')]];
        }

        $score = $recaptchaResp->getScore();
        $threshold = admin_setting('recaptcha_v3_score_threshold', 0.5);
        if ($score < $threshold) {
            return [false, [400, __('Invalid code is incorrect')]];
        }

        return [true, null];
    }

    /**
     * 验证 Google reCAPTCHA v2
     */
    private function verifyRecaptcha(Request $request): array
    {
        $recaptchaData = $request->input('recaptcha_data');
        if (!$recaptchaData) {
            return [false, [400, __('Invalid code is incorrect')]];
        }

        $recaptcha = new ReCaptcha(admin_setting('recaptcha_key'));
        $recaptchaResp = $recaptcha->verify($recaptchaData);

        if (!$recaptchaResp->isSuccess()) {
            return [false, [400, __('Invalid code is incorrect')]];
        }

        return [true, null];
    }
}
