<?php
namespace App\Http\Routes\V2;

use App\Http\Controllers\V1\Passport\AuthController;
use App\Http\Controllers\V1\Passport\CommController;
use App\Http\Controllers\V2\LocalCaptchaController;
use App\Http\Controllers\V2\Admin\SecurityCardController;
use Illuminate\Contracts\Routing\Registrar;

class PassportRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'passport'
        ], function ($router) {
            // Auth
            $router->post('/auth/register', [AuthController::class, 'register']);
            $router->post('/auth/login', [AuthController::class, 'login']);
            $router->get ('/auth/token2Login', [AuthController::class, 'token2Login']);
            $router->post('/auth/forget', [AuthController::class, 'forget']);
            $router->post('/auth/getQuickLoginUrl', [AuthController::class, 'getQuickLoginUrl']);
            $router->post('/auth/loginWithMailLink', [AuthController::class, 'loginWithMailLink']);
            // Comm
            $router->post('/comm/sendEmailVerify', [CommController::class, 'sendEmailVerify']);
            $router->post('/comm/pv', [CommController::class, 'pv']);
        });

        // 本地验证码（公开接口，无需认证，限速防刷）
        $router->group([
            'prefix' => 'captcha',
            'middleware' => 'throttle:30,1',
        ], function ($router) {
            $router->get('/generate', [LocalCaptchaController::class, 'generate']);
            $router->get('/config', [LocalCaptchaController::class, 'config']);
            $router->get('/bundle', [LocalCaptchaController::class, 'bundle']);
        });

        // 密保卡挑战验证（登录流程第二步，无需认证，限速防暴力破解）
        $router->post('/security-card/verify', [SecurityCardController::class, 'verifyLogin'])->middleware('throttle:10,1');
        // 密保卡挑战获取（登录页显示，无需认证，限速防刷）
        $router->get('/security-card/challenge', [SecurityCardController::class, 'publicChallenge'])->middleware('throttle:20,1');
    }
}
