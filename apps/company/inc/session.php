<?php

function getCompanySessionPathCandidate(?string $configuredPath): ?string
{
    if ($configuredPath === null) {
        return null;
    }

    $configuredPath = trim($configuredPath);
    if ($configuredPath === '') {
        return null;
    }

    $parts = explode(';', $configuredPath);
    $candidate = trim((string) end($parts));

    return $candidate === '' ? null : $candidate;
}

function ensureCompanySessionStorage(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $configuredPath = getCompanySessionPathCandidate(ini_get('session.save_path') ?: null);
    $canUseConfiguredPath = $configuredPath !== null && is_dir($configuredPath) && is_writable($configuredPath);

    if (!$canUseConfiguredPath) {
        $fallbackPath = dirname(__DIR__) . '/storage/sessions';

        if (!is_dir($fallbackPath)) {
            mkdir($fallbackPath, 0777, true);
        }

        if (is_dir($fallbackPath) && is_writable($fallbackPath)) {
            session_save_path($fallbackPath);
        }
    }

    session_start();
}
