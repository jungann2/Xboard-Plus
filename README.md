# Xboard-Plus

<div align="center">

🛡️ 基于 Laravel 11 + Octane 的高性能面板系统，搭配 VasmaX 实现全自动化管理

[![GitHub](https://img.shields.io/badge/GitHub-Xboard--Plus-181717?logo=github)](https://github.com/jungann2/Xboard-Plus)
![PHP](https://img.shields.io/badge/PHP-8.2+-green.svg)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-blue.svg)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

</div>

---

## 🔗 配套项目

| 项目 | 说明 |
|------|------|
| [🛡️ VasmaX](https://github.com/jungann2/vasmax) | 十五合一协议管理脚本（Go 重构版），支持 Xray-core + sing-box 双核心，一键安装 15 种协议组合 |

> 💡 **Xboard-Plus + VasmaX 搭配使用，效果极佳。** 面板负责用户管理、订阅分发、流量统计，VasmaX 负责节点部署、协议配置、证书管理。两者对接后可实现：用户自动同步、流量实时统计、到期自动停用、多节点集中管理。从单机自用到多用户运营，一套方案全搞定。

---

## 📖 简介

Xboard-Plus 是一个现代化的面板管理系统，基于 Laravel 11 + Octane 构建，提供简洁高效的用户体验。

## ✨ 功能特性

- 🚀 Laravel 11 + Octane 驱动，性能大幅提升
- 🎨 全新管理后台（React + Shadcn UI）
- 📱 现代化用户前端（Vue3 + TypeScript + NaiveUI）
- 🐳 Docker 一键部署，开箱即用
- 🔐 本地验证码系统（字符 + 算术 + 密保卡多层防护）
- 🔌 插件架构，功能扩展不改主代码
- 📊 流量统计、用户管理、订阅分发一站式解决

## 🚀 快速安装

### 第一步：更新系统并安装必要依赖

root 用户执行：
```bash
apt update -y && apt install -y curl socat wget git
```

非 root 用户执行：
```bash
sudo apt update -y && sudo apt install -y curl socat wget git
```

### 第二步：安装 Docker

```bash
curl -sSL https://get.docker.com | bash
```

> 安装完成后确认 Docker 运行：`docker --version`

### 第三步：克隆项目

```bash
git clone --recurse-submodules https://github.com/jungann2/Xboard-Plus.git
cd Xboard-Plus
```

> 如果忘了加 `--recurse-submodules`，补执行：`git submodule update --init`

### 第四步：初始化安装

```bash
docker compose run -it --rm \
    -e ENABLE_SQLITE=false \
    -e ENABLE_REDIS=true \
    -e ADMIN_ACCOUNT=admin@admin.com \
    web php artisan xboard:install
```

> ⚠️ 安装过程中会显示管理员密码和后台路径，请务必保存

### 第五步：启动服务

```bash
docker compose up -d
```

安装完成后访问：`http://服务器IP:7001`

## 🔄 升级

```bash
cd Xboard-Plus
git pull
git submodule update --init
docker compose restart
```

---

## 🔐 Xboard-Plus 特色功能

Xboard-Plus 在原版 Xboard 基础上增加了多项安全增强与运维便捷功能：

### 🛡️ 多层验证码系统

支持本地字符验证码（可配置字符集、长度）和算术验证码，可独立控制前台/后台启用，无需依赖第三方服务，有效防御暴力破解和自动化攻击。

| 前台登录 | 后台登录 |
|:---:|:---:|
| ![前台登录](./docs/images/front-desk-login.png) | ![后台登录](./docs/images/back-end-login.png) |

### 📊 密保卡系统

后台管理员登录支持密保卡二次验证（12×12 矩阵），每次登录随机抽取坐标验证，支持打印和导出图片，为管理后台提供额外的物理安全层。

![验证码与密保卡管理](./docs/images/verification-code-security-card.png)

### 📋 一键导入节点

在节点管理对话框中粘贴 VasmaX 分享链接（支持 vless / vmess / trojan / hysteria2 / tuic / anytls），自动解析并填入所有配置字段，大幅提升节点录入效率。

![一键导入节点](./docs/images/one-click-node-addition.png)

### 🔒 安全加固

- 验证码暴力破解防护：每个 captchaId 最多 5 次尝试，验证后立即销毁
- 路由级限速：验证码生成 30次/分钟，密保卡挑战 20次/分钟，密保卡验证 10次/分钟
- 场景自动检测：后端根据请求路径判断前台/后台，不信任前端传入的 scene 参数
- 统一错误信息：登录失败不区分"密码错误"和"密保卡错误"，防止信息泄露
- 输入格式验证：captchaId、challengeId 严格正则校验，answers 数量限制

### 🌐 多语言界面

前台和后台均支持中英文界面切换。

| 中文前台 | 中文后台 | English Frontend | English Admin |
|:---:|:---:|:---:|:---:|
| ![cn_user](./docs/images/cn_user.png) | ![cn_admin](./docs/images/cn_admin.png) | ![user](./docs/images/user.png) | ![admin](./docs/images/admin.png) |

## 📖 文档

### 部署指南
- [Docker Compose 部署](./docs/en/installation/docker-compose.md)
- [1Panel 部署](./docs/en/installation/1panel.md)
- [宝塔面板部署](./docs/en/installation/aapanel.md)
- [宝塔 + Docker 部署](./docs/en/installation/aapanel-docker.md)（推荐）

### 迁移指南
- [从 v2board dev 迁移](./docs/en/migration/v2board-dev.md)
- [从 v2board 1.7.4 迁移](./docs/en/migration/v2board-1.7.4.md)
- [从 v2board 1.7.3 迁移](./docs/en/migration/v2board-1.7.3.md)

## 🛠️ 技术栈

- 后端：Laravel 11 + Octane
- 管理后台：React + Shadcn UI + TailwindCSS
- 用户前端：Vue3 + TypeScript + NaiveUI
- 部署：Docker + Docker Compose
- 缓存：Redis + Octane Cache

## ⚠️ 免责声明

本项目仅供学习交流使用，使用者需自行承担使用后果。

## 🔔 注意事项

修改管理路径后需重启服务：
```bash
docker compose restart
```

## 🤝 贡献

欢迎提交 Issue 和 Pull Request。

---

# English

<div align="center">

🛡️ High-performance panel system built on Laravel 11 + Octane, paired with VasmaX for fully automated management

</div>

---

## 🔗 Companion Project

| Project | Description |
|---------|-------------|
| [🛡️ VasmaX](https://github.com/jungann2/vasmax) | 15-in-1 protocol management script (Go rewrite), supports Xray-core + sing-box dual-core, one-click install for 15 protocol combinations |

> 💡 **Xboard-Plus + VasmaX work best together.** The panel handles user management, subscription distribution, and traffic statistics. VasmaX handles node deployment, protocol configuration, and certificate management. Together they enable: automatic user sync, real-time traffic stats, auto-disable on expiry, and centralized multi-node management. One solution from personal use to multi-user operations.

---

## 📖 Introduction

Xboard-Plus is a modern panel management system built on Laravel 11 + Octane, delivering a clean and efficient user experience.

## ✨ Features

- 🚀 Powered by Laravel 11 + Octane for significant performance gains
- 🎨 Redesigned admin interface (React + Shadcn UI)
- 📱 Modern user frontend (Vue3 + TypeScript + NaiveUI)
- 🐳 One-click Docker deployment, ready out of the box
- 🔐 Local captcha system (character + arithmetic + security card multi-layer protection)
- 🔌 Plugin architecture, extend features without modifying core code
- 📊 Traffic stats, user management, subscription distribution all-in-one

## 🚀 Quick Start

### Step 1: Update system and install dependencies

As root:
```bash
apt update -y && apt install -y curl socat wget git
```

Non-root:
```bash
sudo apt update -y && sudo apt install -y curl socat wget git
```

### Step 2: Install Docker

```bash
curl -sSL https://get.docker.com | bash
```

### Step 3: Clone the project

```bash
git clone --recurse-submodules https://github.com/jungann2/Xboard-Plus.git
cd Xboard-Plus
```

> If you forgot `--recurse-submodules`, run: `git submodule update --init`

### Step 4: Initialize

```bash
docker compose run -it --rm \
    -e ENABLE_SQLITE=false \
    -e ENABLE_REDIS=true \
    -e ADMIN_ACCOUNT=admin@admin.com \
    web php artisan xboard:install
```

> ⚠️ Save the admin credentials shown during installation

### Step 5: Start services

```bash
docker compose up -d
```

Visit: `http://YOUR_SERVER_IP:7001`

## 🔄 Upgrade

```bash
cd Xboard-Plus
git pull
git submodule update --init
docker compose restart
```

---

## 🔐 Exclusive Features

Xboard-Plus builds on the original Xboard with enhanced security features and operational tools designed for production environments.

### 🛡️ Multi-layer CAPTCHA

Built-in character CAPTCHA (configurable charset: digits, uppercase, lowercase, mixed; adjustable length 4-6 or random) and arithmetic CAPTCHA (addition, subtraction, multiplication, division within 1000). Each type can be independently enabled for frontend, admin, or both — no third-party service required. Includes brute-force protection with per-captcha attempt limits and route-level rate limiting.

| Frontend Login | Admin Login |
|:---:|:---:|
| ![Frontend Login](./docs/images/front-desk-login.png) | ![Admin Login](./docs/images/back-end-login.png) |

### 📊 Security Card

Physical two-factor authentication for admin login via a 12×12 security card matrix (columns A-L, rows 1-12, each cell containing a 2-3 character alphanumeric code). On every admin login, 3-4 random coordinates are challenged. Cards can be generated, viewed, printed, and exported as PNG from the admin panel. Provides an offline security layer that cannot be phished or intercepted remotely.

![CAPTCHA & Security Card Management](./docs/images/verification-code-security-card.png)

### 📋 One-click Node Import

Paste a VasmaX share link directly into the node management dialog to auto-fill all configuration fields. Supports vless, vmess, trojan, hysteria2, tuic, and anytls protocols. Automatically parses transport type, TLS/Reality settings, SNI, path, service name, public key, short ID, fingerprint, obfuscation, and ALPN — eliminating manual entry errors and saving significant time when adding nodes.

![One-click Node Import](./docs/images/one-click-node-addition.png)

### 🔒 Security Hardening

- Per-captcha brute-force protection (max 5 attempts per captchaId, one-time use after verification)
- Route-level rate limiting (captcha generation: 30/min, card challenge: 20/min, card verify: 10/min)
- Server-side scene detection based on request path — never trusts frontend-supplied scene parameter
- Unified error messages on login failure to prevent password/card oracle attacks
- Strict input validation with regex on all IDs and length limits on all user inputs

### 🌐 Multilingual UI

Full Chinese and English interface support for both the user frontend and admin panel.

| CN Frontend | CN Admin | EN Frontend | EN Admin |
|:---:|:---:|:---:|:---:|
| ![cn_user](./docs/images/cn_user.png) | ![cn_admin](./docs/images/cn_admin.png) | ![user](./docs/images/user.png) | ![admin](./docs/images/admin.png) |

## 🛠️ Tech Stack

- Backend: Laravel 11 + Octane
- Admin Panel: React + Shadcn UI + TailwindCSS
- User Frontend: Vue3 + TypeScript + NaiveUI
- Deployment: Docker + Docker Compose
- Cache: Redis + Octane Cache

## ⚠️ Disclaimer

This project is for learning and communication purposes only. Users are responsible for any consequences of using this project.

## 🔔 Notes

Restart required after modifying admin path:
```bash
docker compose restart
```

## 🤝 Contributing

Issues and Pull Requests are welcome to help improve the project.
