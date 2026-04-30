<?php

/**
 * Generate or retrieve a CSRF token from the session.
 */
function csrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['token'];
}

function csrfValidate(?string $token): bool
    {
        return isset($_SESSION['token']) &&
               hash_equals($_SESSION['token'], $token ?? '');
    }

/**
 * Return a hidden input field for CSRF protection.
 */
function csrfField(): string
{
    return '<input type="hidden" name="token" value="' . csrfToken() . '">';
}