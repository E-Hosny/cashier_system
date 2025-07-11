<?php
// Production Configuration for Offline System
return [
    "offline_enabled" => env("OFFLINE_ENABLED", true),
    "cache_version" => env("CACHE_VERSION", "v2"),
    "pwa_name" => env("PWA_NAME", "نظام الكاشير"),
    "pwa_short_name" => env("PWA_SHORT_NAME", "كاشير"),
    "pwa_description" => env("PWA_DESCRIPTION", "نظام إدارة المبيعات والمخزون"),
    "pwa_theme_color" => env("PWA_THEME_COLOR", "#667eea"),
    "pwa_background_color" => env("PWA_BACKGROUND_COLOR", "#ffffff"),
];
