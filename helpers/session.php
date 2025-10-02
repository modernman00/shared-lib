<?php

function sessionGet(string $key, $default = null): mixed
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return $_SESSION[$key] ?? $default;
}

/**
 * Flash a value to the session (store temporarily).
 */
function sessionFlash(string $key, $value): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['_flash'][$key] = $value;
}

/**
 * Check if a session key exists.
 */
function sessionHas(string $key): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return isset($_SESSION[$key]);
}

/**
 * Remove a session key.
 */
function sessionForget(string $key): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    unset($_SESSION[$key]);
}
