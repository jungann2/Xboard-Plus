<?php

namespace App\Http\Controllers\V2\Admin\Server;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * 解析 VasmaX 分享链接，返回可用于添加节点的字段。
 * POST /api/v2/{secure_path}/admin/server/parse-share-link
 */
class ParseShareLinkController extends Controller
{
    public function parse(Request $request): JsonResponse
    {
        $link = trim($request->input('link', ''));
        if (empty($link)) {
            return response()->json(['message' => '分享链接不能为空'], 422);
        }

        try {
            $result = $this->parseLink($link);
            return response()->json(['data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['message' => '解析失败: ' . $e->getMessage()], 422);
        }
    }

    private function parseLink(string $link): array
    {
        // 检测协议类型
        if (str_starts_with($link, 'vless://')) {
            return $this->parseVless($link);
        } elseif (str_starts_with($link, 'vmess://')) {
            return $this->parseVmess($link);
        } elseif (str_starts_with($link, 'trojan://')) {
            return $this->parseTrojan($link);
        } elseif (str_starts_with($link, 'hysteria2://') || str_starts_with($link, 'hy2://')) {
            return $this->parseHysteria2($link);
        } elseif (str_starts_with($link, 'tuic://')) {
            return $this->parseTuic($link);
        } elseif (str_starts_with($link, 'anytls://')) {
            return $this->parseAnytls($link);
        }

        throw new \Exception('不支持的协议类型');
    }

    /**
     * 解析 vless:// 链接
     * 格式: vless://uuid@host:port?params#name
     */
    private function parseVless(string $link): array
    {
        $link = substr($link, 8); // 去掉 vless://
        [$main, $fragment] = $this->splitFragment($link);
        [$userInfo, $hostPort, $query] = $this->splitURI($main);

        $params = [];
        parse_str($query, $params);

        $host = $hostPort['host'];
        $port = $hostPort['port'];
        $type = $params['type'] ?? 'tcp';
        $security = $params['security'] ?? 'tls';
        $flow = $params['flow'] ?? '';
        $sni = $params['sni'] ?? '';
        $fp = $params['fp'] ?? '';

        $result = [
            'type' => 'vless',
            'host' => $host,
            'port' => $port,
            'server_port' => $port,
            'protocol_settings' => [
                'network' => $type,
                'flow' => $flow,
            ],
        ];

        // TLS / Reality
        if ($security === 'reality') {
            $result['protocol_settings']['tls'] = 2; // reality
            $result['protocol_settings']['reality_settings'] = [
                'server_name' => $params['sni'] ?? '',
                'public_key' => $params['pbk'] ?? '',
                'short_id' => $params['sid'] ?? '',
            ];
            if ($fp) {
                $result['protocol_settings']['utls'] = ['enabled' => true, 'fingerprint' => $fp];
            }
        } elseif ($security === 'tls') {
            $result['protocol_settings']['tls'] = 1;
            $result['protocol_settings']['tls_settings'] = [
                'server_name' => $sni,
            ];
        } else {
            $result['protocol_settings']['tls'] = 0;
        }

        // 传输层设置
        $networkSettings = [];
        if ($type === 'ws') {
            $networkSettings['path'] = $params['path'] ?? '/';
            $networkSettings['headers'] = ['Host' => $params['host'] ?? $host];
        } elseif ($type === 'grpc') {
            $networkSettings['serviceName'] = $params['serviceName'] ?? '';
        } elseif ($type === 'xhttp') {
            $networkSettings['path'] = $params['path'] ?? '/';
        } elseif ($type === 'httpupgrade') {
            $networkSettings['path'] = $params['path'] ?? '/';
            $networkSettings['host'] = $params['host'] ?? $host;
        }
        if (!empty($networkSettings)) {
            $result['protocol_settings']['network_settings'] = $networkSettings;
        }

        return $result;
    }

    /**
     * 解析 vmess:// 链接
     * 格式: vmess://base64json
     */
    private function parseVmess(string $link): array
    {
        $encoded = substr($link, 8);
        $json = json_decode(base64_decode($encoded), true);
        if (!$json) {
            throw new \Exception('VMess 链接 Base64 解码失败');
        }

        $host = $json['add'] ?? '';
        $port = intval($json['port'] ?? 443);
        $net = $json['net'] ?? 'tcp';
        $tls = ($json['tls'] ?? '') === 'tls' ? 1 : 0;

        $result = [
            'type' => 'vmess',
            'host' => $host,
            'port' => $port,
            'server_port' => $port,
            'protocol_settings' => [
                'tls' => $tls,
                'network' => $net,
            ],
        ];

        if ($tls && !empty($json['sni'])) {
            $result['protocol_settings']['tls_settings'] = [
                'server_name' => $json['sni'],
            ];
        }

        $networkSettings = [];
        if ($net === 'ws') {
            $networkSettings['path'] = $json['path'] ?? '/';
            $networkSettings['headers'] = ['Host' => $json['host'] ?? $host];
        } elseif ($net === 'grpc') {
            $networkSettings['serviceName'] = $json['path'] ?? '';
        } elseif ($net === 'httpupgrade') {
            $networkSettings['path'] = $json['path'] ?? '/';
            $networkSettings['host'] = $json['host'] ?? $host;
        }
        if (!empty($networkSettings)) {
            $result['protocol_settings']['network_settings'] = $networkSettings;
        }

        return $result;
    }

    /**
     * 解析 trojan:// 链接
     * 格式: trojan://password@host:port?params#name
     */
    private function parseTrojan(string $link): array
    {
        $link = substr($link, 9);
        [$main, $fragment] = $this->splitFragment($link);
        [$userInfo, $hostPort, $query] = $this->splitURI($main);

        $params = [];
        parse_str($query, $params);

        $host = $hostPort['host'];
        $port = $hostPort['port'];
        $type = $params['type'] ?? 'tcp';
        $sni = $params['sni'] ?? '';

        $result = [
            'type' => 'trojan',
            'host' => $host,
            'port' => $port,
            'server_port' => $port,
            'protocol_settings' => [
                'network' => $type,
                'server_name' => $sni,
            ],
        ];

        $networkSettings = [];
        if ($type === 'grpc') {
            $networkSettings['serviceName'] = $params['serviceName'] ?? '';
        } elseif ($type === 'ws') {
            $networkSettings['path'] = $params['path'] ?? '/';
            $networkSettings['headers'] = ['Host' => $params['host'] ?? $host];
        }
        if (!empty($networkSettings)) {
            $result['protocol_settings']['network_settings'] = $networkSettings;
        }

        return $result;
    }

    /**
     * 解析 hysteria2:// 链接
     * 格式: hysteria2://password@host:port?params#name
     */
    private function parseHysteria2(string $link): array
    {
        // 统一处理 hy2:// 和 hysteria2://
        if (str_starts_with($link, 'hy2://')) {
            $link = substr($link, 6);
        } else {
            $link = substr($link, 12);
        }
        [$main, $fragment] = $this->splitFragment($link);
        [$userInfo, $hostPort, $query] = $this->splitURI($main);

        $params = [];
        parse_str($query, $params);

        $host = $hostPort['host'];
        $port = $hostPort['port'];
        $sni = $params['sni'] ?? '';
        $insecure = ($params['insecure'] ?? '0') === '1';
        $obfsType = $params['obfs'] ?? '';
        $obfsPassword = $params['obfs-password'] ?? '';

        $result = [
            'type' => 'hysteria',
            'host' => $host,
            'port' => $port,
            'server_port' => $port,
            'protocol_settings' => [
                'version' => 2,
                'tls' => [
                    'server_name' => $sni,
                    'allow_insecure' => $insecure,
                ],
            ],
        ];

        if ($obfsType) {
            $result['protocol_settings']['obfs'] = [
                'open' => true,
                'type' => $obfsType,
                'password' => $obfsPassword,
            ];
        }

        return $result;
    }

    /**
     * 解析 tuic:// 链接
     * 格式: tuic://uuid:password@host:port?params#name
     */
    private function parseTuic(string $link): array
    {
        $link = substr($link, 7);
        [$main, $fragment] = $this->splitFragment($link);
        [$userInfo, $hostPort, $query] = $this->splitURI($main);

        $params = [];
        parse_str($query, $params);

        $host = $hostPort['host'];
        $port = $hostPort['port'];
        $sni = $params['sni'] ?? '';
        $alpn = $params['alpn'] ?? '';
        $insecure = ($params['insecure'] ?? '0') === '1';

        return [
            'type' => 'tuic',
            'host' => $host,
            'port' => $port,
            'server_port' => $port,
            'protocol_settings' => [
                'version' => 5,
                'tls' => [
                    'server_name' => $sni,
                    'allow_insecure' => $insecure,
                ],
                'alpn' => $alpn,
            ],
        ];
    }

    /**
     * 解析 anytls:// 链接
     * 格式: anytls://password@host:port?params#name
     */
    private function parseAnytls(string $link): array
    {
        $link = substr($link, 9);
        [$main, $fragment] = $this->splitFragment($link);
        [$userInfo, $hostPort, $query] = $this->splitURI($main);

        $params = [];
        parse_str($query, $params);

        $host = $hostPort['host'];
        $port = $hostPort['port'];
        $sni = $params['sni'] ?? '';
        $insecure = ($params['insecure'] ?? '0') === '1';

        return [
            'type' => 'anytls',
            'host' => $host,
            'port' => $port,
            'server_port' => $port,
            'protocol_settings' => [
                'tls' => [
                    'server_name' => $sni,
                    'allow_insecure' => $insecure,
                ],
            ],
        ];
    }

    // --- 工具方法 ---

    /**
     * 分离 fragment (#name)
     */
    private function splitFragment(string $uri): array
    {
        $pos = strpos($uri, '#');
        if ($pos === false) {
            return [$uri, ''];
        }
        return [substr($uri, 0, $pos), urldecode(substr($uri, $pos + 1))];
    }

    /**
     * 解析 userinfo@host:port?query
     */
    private function splitURI(string $main): array
    {
        $query = '';
        $qPos = strpos($main, '?');
        if ($qPos !== false) {
            $query = substr($main, $qPos + 1);
            $main = substr($main, 0, $qPos);
        }

        $userInfo = '';
        $atPos = strpos($main, '@');
        if ($atPos !== false) {
            $userInfo = substr($main, 0, $atPos);
            $main = substr($main, $atPos + 1);
        }

        // host:port
        $lastColon = strrpos($main, ':');
        if ($lastColon !== false) {
            $host = substr($main, 0, $lastColon);
            $port = intval(substr($main, $lastColon + 1));
        } else {
            $host = $main;
            $port = 443;
        }

        // 去掉 IPv6 方括号
        $host = trim($host, '[]');

        return [$userInfo, ['host' => $host, 'port' => $port], $query];
    }
}
