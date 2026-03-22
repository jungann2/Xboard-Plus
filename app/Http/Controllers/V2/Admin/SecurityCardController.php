<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 密保卡管理
 * 12×12 网格，列 A-L，行 1-12
 * 每格 2-3 位 "数字+字母" 组合
 */
class SecurityCardController extends Controller
{
    private const COLS = ['A','B','C','D','E','F','G','H','I','J','K','L'];
    private const ROWS = 12;

    /**
     * 获取密保卡挑战（登录页调用，公开接口）
     * GET /api/v2/security-card/challenge
     */
    public function publicChallenge(): JsonResponse
    {
        if (!(int) admin_setting('security_card_enable', 0)) {
            return response()->json(['data' => null]);
        }

        $record = DB::table('v2_security_cards')
            ->where('is_enabled', true)
            ->first();

        if (!$record) {
            return response()->json(['data' => null]);
        }

        $count = random_int(3, 4);
        $positions = [];
        $usedKeys = [];

        while (count($positions) < $count) {
            $col = self::COLS[random_int(0, 11)];
            $row = random_int(1, self::ROWS);
            $key = $col . $row;
            if (!in_array($key, $usedKeys)) {
                $usedKeys[] = $key;
                $positions[] = $key;
            }
        }

        $challengeId = Str::random(32);
        Cache::put('security_card_challenge:' . $challengeId, [
            'positions' => $positions,
        ], 300);

        return response()->json([
            'data' => [
                'challenge_id' => $challengeId,
                'positions' => $positions,
            ]
        ]);
    }

    /**
     * 生成新密保卡
     * POST /api/v2/{secure_path}/admin/security-card/generate
     */
    public function generate(Request $request): JsonResponse
    {
        $user = $request->user();
        $card = $this->generateCard();

        // 加密存储
        $encrypted = encrypt(json_encode($card));

        DB::table('v2_security_cards')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'card_data' => $encrypted,
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'data' => [
                'card' => $card,
                'cols' => self::COLS,
                'rows' => range(1, self::ROWS),
                'message' => '请立即保存密保卡，此卡仅显示一次。',
            ]
        ]);
    }

    /**
     * 获取密保卡状态（是否已启用）
     * GET /api/v2/{secure_path}/admin/security-card/status
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $record = DB::table('v2_security_cards')
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'data' => [
                'enabled' => $record && $record->is_enabled,
                'created_at' => $record?->created_at,
            ]
        ]);
    }

    /**
     * 查看密保卡（需要已登录）
     * GET /api/v2/{secure_path}/security-card/view
     */
    public function view(Request $request): JsonResponse
    {
        $user = $request->user();
        $record = DB::table('v2_security_cards')
            ->where('user_id', $user->id)
            ->where('is_enabled', true)
            ->first();

        if (!$record) {
            return response()->json(['message' => '未找到已启用的密保卡'], 404);
        }

        $card = json_decode(decrypt($record->card_data), true);

        return response()->json([
            'data' => [
                'card' => $card,
                'cols' => self::COLS,
                'rows' => range(1, self::ROWS),
                'created_at' => $record->created_at,
            ]
        ]);
    }

    /**
     * 禁用密保卡
     * POST /api/v2/{secure_path}/admin/security-card/disable
     */
    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();

        DB::table('v2_security_cards')
            ->where('user_id', $user->id)
            ->update(['is_enabled' => false, 'updated_at' => now()]);

        return response()->json(['data' => true]);
    }

    /**
     * 获取密保卡挑战（登录时调用，无需认证）
     * POST /api/v2/security-card/challenge
     */
    public static function getChallenge(int $userId): ?array
    {
        $record = DB::table('v2_security_cards')
            ->where('user_id', $userId)
            ->where('is_enabled', true)
            ->first();

        if (!$record) {
            return null;
        }

        // 随机抽 2-3 个坐标
        $count = random_int(2, 3);
        $positions = [];
        $usedKeys = [];

        while (count($positions) < $count) {
            $col = self::COLS[random_int(0, 11)];
            $row = random_int(1, self::ROWS);
            $key = $col . $row;
            if (!in_array($key, $usedKeys)) {
                $usedKeys[] = $key;
                $positions[] = ['col' => $col, 'row' => $row, 'label' => $key];
            }
        }

        // 存储挑战到缓存
        $challengeId = Str::random(32);
        Cache::put('security_card_challenge:' . $challengeId, [
            'user_id' => $userId,
            'positions' => $positions,
        ], 300); // 5分钟过期

        return [
            'challenge_id' => $challengeId,
            'positions' => array_map(fn($p) => $p['label'], $positions),
        ];
    }

    /**
     * 验证密保卡答案（登录流程第二步，公开接口）
     * POST /api/v2/security-card/verify
     */
    public function verifyLogin(Request $request): JsonResponse
    {
        $challengeId = $request->input('challenge_id');
        $answers = $request->input('answers', []);

        if (empty($challengeId) || empty($answers) || !is_array($answers)) {
            return response()->json(['message' => '密保卡验证参数不完整'], 400);
        }

        // 验证 challengeId 格式
        if (!preg_match('/^[a-zA-Z0-9]{1,64}$/', $challengeId)) {
            return response()->json(['message' => '密保卡验证失败'], 400);
        }

        // 限制 answers 数量
        if (count($answers) > 6) {
            return response()->json(['message' => '密保卡验证失败'], 400);
        }

        // 从缓存获取挑战数据
        $key = 'security_card_challenge:' . $challengeId;
        $challenge = Cache::get($key);
        if (!$challenge) {
            return response()->json(['message' => '验证已过期，请重新登录'], 400);
        }
        Cache::forget($key);

        $positions = $challenge['positions'] ?? [];
        $userId = $challenge['user_id'] ?? null;

        // 必须有 user_id（由 getChallenge 生成的挑战才有）
        if (!$userId) {
            return response()->json(['message' => '密保卡验证失败'], 400);
        }

        // 验证答案
        $record = DB::table('v2_security_cards')
            ->where('user_id', $userId)
            ->where('is_enabled', true)
            ->first();

        if (!$record) {
            return response()->json(['message' => '密保卡验证失败'], 400);
        }

        $card = json_decode(decrypt($record->card_data), true);

        if (count($answers) !== count($positions)) {
            return response()->json(['message' => '密保卡验证失败'], 400);
        }

        foreach ($positions as $i => $pos) {
            if (is_string($pos)) {
                $col = substr($pos, 0, 1);
                $row = (int) substr($pos, 1);
            } else {
                $col = $pos['col'];
                $row = $pos['row'];
            }
            $expected = $card[$col][$row] ?? null;
            $input = trim($answers[$i] ?? '');

            if ($expected === null || strcasecmp($input, $expected) !== 0) {
                return response()->json(['message' => '密保卡验证失败'], 400);
            }
        }

        // 验证通过，从缓存获取待发放的 auth token
        $pendingKey = 'security_card_pending_auth:' . $challengeId;
        $authData = Cache::get($pendingKey);
        Cache::forget($pendingKey);

        if (!$authData) {
            return response()->json(['message' => '验证已过期，请重新登录'], 400);
        }

        return response()->json(['data' => $authData]);
    }

    /**
     * 验证密保卡答案（支持两种格式）
     * @param int $userId 用户ID
     */
    public static function verifyChallengeForUser(string $challengeId, array $answers, int $userId): bool
    {
        // 验证 challengeId 格式
        if (!preg_match('/^[a-zA-Z0-9]{1,64}$/', $challengeId)) {
            return false;
        }

        // 限制 answers 数量（密保卡最多 4 个坐标）
        if (count($answers) > 6) {
            return false;
        }

        $key = 'security_card_challenge:' . $challengeId;
        $challenge = Cache::get($key);

        if (!$challenge) {
            return false;
        }

        Cache::forget($key);

        $positions = $challenge['positions'] ?? [];

        $record = DB::table('v2_security_cards')
            ->where('user_id', $userId)
            ->where('is_enabled', true)
            ->first();

        if (!$record) {
            return false;
        }

        $card = json_decode(decrypt($record->card_data), true);

        if (count($answers) !== count($positions)) {
            return false;
        }

        foreach ($positions as $i => $pos) {
            // 支持字符串格式 "A3" 和对象格式 {col:"A", row:3}
            if (is_string($pos)) {
                $col = substr($pos, 0, 1);
                $row = (int) substr($pos, 1);
            } else {
                $col = $pos['col'];
                $row = $pos['row'];
            }
            $expected = $card[$col][$row] ?? null;
            $input = trim($answers[$i] ?? '');

            if ($expected === null || strcasecmp($input, $expected) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * 生成 12×12 密保卡数据
     */
    private function generateCard(): array
    {
        $card = [];
        $chars = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';

        foreach (self::COLS as $col) {
            $card[$col] = [];
            for ($row = 1; $row <= self::ROWS; $row++) {
                $len = random_int(2, 3);
                $val = '';
                for ($k = 0; $k < $len; $k++) {
                    $val .= $chars[random_int(0, strlen($chars) - 1)];
                }
                $card[$col][$row] = $val;
            }
        }

        return $card;
    }
}
