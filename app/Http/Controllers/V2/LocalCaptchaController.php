<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * 本地验证码生成与验证
 * 类型A：字符验证码（数字/大写/小写/混合）+ 混淆背景
 * 类型B：算术验证码（1000以内 + - × ÷）
 */
class LocalCaptchaController extends Controller
{
    /**
     * 生成验证码图片
     * GET /api/v2/captcha/generate?type=char|math
     */
    public function generate(Request $request): JsonResponse
    {
        $type = $request->input('type', 'char');

        if ($type === 'math') {
            return $this->generateMath();
        }

        return $this->generateChar();
    }

    /**
     * 获取指定场景下启用的验证码类型（供前端查询）
     * GET /api/v2/captcha/config?scene=frontend|admin
     */
    public function config(Request $request): JsonResponse
    {
        $scene = $request->input('scene', 'frontend');
        if (!in_array($scene, ['frontend', 'admin'])) $scene = 'frontend';

        $types = \App\Services\CaptchaService::getEnabledTypes($scene);

        return response()->json(['data' => $types]);
    }

    /**
     * 一次性返回所有验证码数据（config + images + security card challenge）
     * GET /api/v2/captcha/bundle?scene=frontend|admin
     */
    public function bundle(Request $request): JsonResponse
    {
        $scene = $request->input('scene', 'frontend');
        if (!in_array($scene, ['frontend', 'admin'])) $scene = 'frontend';

        $types = \App\Services\CaptchaService::getEnabledTypes($scene);
        $result = ['types' => $types];

        foreach ($types as $t) {
            if ($t['type'] === 'char') {
                $result['char'] = $this->generateCharData();
            } elseif ($t['type'] === 'math') {
                $result['math'] = $this->generateMathData();
            } elseif ($t['type'] === 'security_card') {
                $ctrl = new \App\Http\Controllers\V2\Admin\SecurityCardController();
                $challengeResp = $ctrl->publicChallenge();
                $challengeData = json_decode($challengeResp->getContent(), true);
                $result['security_card'] = $challengeData['data'] ?? null;
            }
        }

        return response()->json(['data' => $result]);
    }

    /**
     * 生成字符验证码数据（内部用）
     */
    private function generateCharData(): array
    {
        $length = (int) admin_setting('local_captcha_length', 4);
        if ($length === 0) $length = random_int(4, 6);
        $charset = admin_setting('local_captcha_charset', 'mixed');
        $chars = $this->getCharset($charset);
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $captchaId = Str::random(32);
        Cache::put('captcha:' . $captchaId, $code, 300);
        return [
            'captcha_id' => $captchaId,
            'image' => 'data:image/svg+xml;base64,' . base64_encode($this->renderCharImage($code, $length)),
        ];
    }

    /**
     * 生成算术验证码数据（内部用）
     */
    private function generateMathData(): array
    {
        $ops = ['+', '-', '×', '÷'];
        $op = $ops[random_int(0, 3)];
        switch ($op) {
            case '+': $a = random_int(1, 999); $b = random_int(1, 999 - $a); $answer = $a + $b; break;
            case '-': $a = random_int(2, 999); $b = random_int(1, $a - 1); $answer = $a - $b; break;
            case '×': $a = random_int(1, 99); $b = random_int(1, min(99, intdiv(999, max($a, 1)))); $answer = $a * $b; break;
            case '÷': $b = random_int(1, 99); $answer = random_int(1, min(99, intdiv(999, $b))); $a = $b * $answer; break;
            default: $a = 1; $b = 1; $answer = 2;
        }
        $expression = "{$a} {$op} {$b} = ?";
        $captchaId = Str::random(32);
        Cache::put('captcha:' . $captchaId, (string) $answer, 300);
        return [
            'captcha_id' => $captchaId,
            'image' => 'data:image/svg+xml;base64,' . base64_encode($this->renderMathImage($expression)),
        ];
    }

    /**
     * 字符验证码
     */
    private function generateChar(): JsonResponse
    {
        $length = (int) admin_setting('local_captcha_length', 4);
        if ($length === 0) $length = random_int(4, 6);
        $charset = admin_setting('local_captcha_charset', 'mixed');

        $chars = $this->getCharset($charset);
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $captchaId = Str::random(32);
        Cache::put('captcha:' . $captchaId, $code, 300); // 5分钟过期

        $image = $this->renderCharImage($code, $length);

        return response()->json([
            'data' => [
                'captcha_id' => $captchaId,
                'image' => 'data:image/svg+xml;base64,' . base64_encode($image),
            ]
        ]);
    }

    /**
     * 算术验证码
     */
    private function generateMath(): JsonResponse
    {
        $ops = ['+', '-', '×', '÷'];
        $op = $ops[random_int(0, 3)];

        switch ($op) {
            case '+':
                $a = random_int(1, 999);
                $b = random_int(1, 999 - $a);
                $answer = $a + $b;
                break;
            case '-':
                $a = random_int(2, 999);
                $b = random_int(1, $a - 1);
                $answer = $a - $b;
                break;
            case '×':
                $a = random_int(1, 99);
                $b = random_int(1, min(99, intdiv(999, max($a, 1))));
                $answer = $a * $b;
                break;
            case '÷':
                $b = random_int(1, 99);
                $answer = random_int(1, min(99, intdiv(999, $b)));
                $a = $b * $answer;
                break;
            default:
                $a = 1; $b = 1; $answer = 2;
        }

        $expression = "{$a} {$op} {$b} = ?";
        $captchaId = Str::random(32);
        Cache::put('captcha:' . $captchaId, (string) $answer, 300);

        $image = $this->renderMathImage($expression);

        return response()->json([
            'data' => [
                'captcha_id' => $captchaId,
                'image' => 'data:image/svg+xml;base64,' . base64_encode($image),
            ]
        ]);
    }

    /**
     * 获取字符集
     */
    private function getCharset(string $type): string
    {
        return match ($type) {
            'number' => '0123456789',
            'upper' => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'lower' => 'abcdefghjkmnpqrstuvwxyz',
            default => '0123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz',
        };
    }

    /**
     * 渲染字符验证码 SVG 图片
     */
    private function renderCharImage(string $code, int $length): string
    {
        $width = $length >= 6 ? 180 : ($length === 5 ? 155 : 130);
        $height = 44;

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '">';
        $svg .= '<rect width="100%" height="100%" fill="#f0f0f0"/>';

        // 混淆背景：随机线条
        for ($i = 0; $i < 6; $i++) {
            $x1 = random_int(0, $width);
            $y1 = random_int(0, $height);
            $x2 = random_int(0, $width);
            $y2 = random_int(0, $height);
            $color = sprintf('#%02x%02x%02x', random_int(150, 230), random_int(150, 230), random_int(150, 230));
            $svg .= '<line x1="' . $x1 . '" y1="' . $y1 . '" x2="' . $x2 . '" y2="' . $y2 . '" stroke="' . $color . '" stroke-width="1"/>';
        }

        // 混淆背景：随机圆点
        for ($i = 0; $i < 30; $i++) {
            $cx = random_int(0, $width);
            $cy = random_int(0, $height);
            $r = random_int(1, 3);
            $color = sprintf('#%02x%02x%02x', random_int(150, 230), random_int(150, 230), random_int(150, 230));
            $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="' . $color . '"/>';
        }

        // 绘制字符
        $charWidth = ($width - 20) / strlen($code);
        for ($i = 0; $i < strlen($code); $i++) {
            $x = 10 + $i * $charWidth + random_int(-3, 3);
            $y = random_int(26, 34);
            $rotate = random_int(-15, 15);
            $color = sprintf('#%02x%02x%02x', random_int(20, 100), random_int(20, 100), random_int(20, 100));
            $fontSize = random_int(20, 26);
            $svg .= '<text x="' . $x . '" y="' . $y . '" fill="' . $color . '" font-size="' . $fontSize . '" font-family="monospace" font-weight="bold" transform="rotate(' . $rotate . ' ' . $x . ' ' . $y . ')">' . htmlspecialchars($code[$i]) . '</text>';
        }

        // 混淆前景线
        for ($i = 0; $i < 2; $i++) {
            $x1 = random_int(0, 20);
            $y1 = random_int(10, $height - 10);
            $x2 = random_int($width - 20, $width);
            $y2 = random_int(10, $height - 10);
            $color = sprintf('#%02x%02x%02x', random_int(80, 160), random_int(80, 160), random_int(80, 160));
            $svg .= '<line x1="' . $x1 . '" y1="' . $y1 . '" x2="' . $x2 . '" y2="' . $y2 . '" stroke="' . $color . '" stroke-width="1.5"/>';
        }

        $svg .= '</svg>';
        return $svg;
    }

    /**
     * 渲染算术验证码 SVG 图片
     */
    private function renderMathImage(string $expression): string
    {
        $width = 180;
        $height = 44;

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '">';
        $svg .= '<rect width="100%" height="100%" fill="#f0f0f0"/>';

        // 混淆背景
        for ($i = 0; $i < 5; $i++) {
            $x1 = random_int(0, $width);
            $y1 = random_int(0, $height);
            $x2 = random_int(0, $width);
            $y2 = random_int(0, $height);
            $color = sprintf('#%02x%02x%02x', random_int(150, 230), random_int(150, 230), random_int(150, 230));
            $svg .= '<line x1="' . $x1 . '" y1="' . $y1 . '" x2="' . $x2 . '" y2="' . $y2 . '" stroke="' . $color . '" stroke-width="1"/>';
        }

        for ($i = 0; $i < 20; $i++) {
            $cx = random_int(0, $width);
            $cy = random_int(0, $height);
            $color = sprintf('#%02x%02x%02x', random_int(150, 230), random_int(150, 230), random_int(150, 230));
            $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . random_int(1, 2) . '" fill="' . $color . '"/>';
        }

        // 绘制算式
        $color = sprintf('#%02x%02x%02x', random_int(20, 80), random_int(20, 80), random_int(20, 80));
        $svg .= '<text x="' . ($width / 2) . '" y="30" fill="' . $color . '" font-size="22" font-family="monospace" font-weight="bold" text-anchor="middle">' . htmlspecialchars($expression) . '</text>';

        // 前景干扰线
        $color2 = sprintf('#%02x%02x%02x', random_int(80, 160), random_int(80, 160), random_int(80, 160));
        $svg .= '<line x1="' . random_int(0, 20) . '" y1="' . random_int(10, 34) . '" x2="' . random_int($width - 20, $width) . '" y2="' . random_int(10, 34) . '" stroke="' . $color2 . '" stroke-width="1.5"/>';

        $svg .= '</svg>';
        return $svg;
    }

    /**
     * 验证本地验证码
     */
    public static function verify(string $captchaId, string $input): bool
    {
        if (empty($captchaId) || empty($input)) {
            return false;
        }

        // 验证 captchaId 格式（只允许字母数字，最长 64 位）
        if (!preg_match('/^[a-zA-Z0-9]{1,64}$/', $captchaId)) {
            return false;
        }

        // 防暴力破解：同一 captchaId 最多尝试 5 次
        $attemptKey = 'captcha_attempts:' . $captchaId;
        $attempts = (int) Cache::get($attemptKey, 0);
        if ($attempts >= 5) {
            Cache::forget('captcha:' . $captchaId);
            Cache::forget($attemptKey);
            return false;
        }
        Cache::put($attemptKey, $attempts + 1, 300);

        $key = 'captcha:' . $captchaId;
        $expected = Cache::get($key);

        if ($expected === null) {
            return false;
        }

        // 验证后立即删除（一次性）
        Cache::forget($key);
        Cache::forget($attemptKey);

        return trim($input) === $expected;
    }
}
