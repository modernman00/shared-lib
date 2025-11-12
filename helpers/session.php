<?php

function sessGet(string $key, $default = null): mixed
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return cleanSession($_SESSION[$key]) ?? $default;
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

/**
 * Remove a session key.
 */
function sessForget(string $key): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    unset($_SESSION[$key]);
}

// remove some session keys
function unsetSess(array $keys): void
{

    foreach ($keys as $key) {
        unset($_SESSION[$key]);
    }
}

// print all session keys and values
function sess(): void
{

    echo '<pre>';
    print_r($_SESSION);
    echo '</pre>';
}

// create sessions 
function sessSet(string $key, $value): void
{
    $_SESSION[$key] = $value;
}

// set sessions array 
function sessSetMany(array $data): void
{
    $_SESSION = array_merge($_SESSION, $data);
}
