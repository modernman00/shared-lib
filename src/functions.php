<?php

declare(strict_types=1);

// Get base URL
function base_url()
{
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';

    return $scheme . '://' . $_SERVER['HTTP_HOST'];
}

// url('path/to/page')
function url($path = '')
{
    return rtrim(base_url(), '/') . '/' . ltrim($path, '/');
}

// asset('images/logo.png')
function asset($path = '')
{
    return url('public/' . ltrim($path, '/'));
}

// route('name', ['param' => value])
$routes = [
    'home'    => '/',
    'about'   => '/about',
    'contact' => '/contact',
    'result'  => '/result?score={score}',
];

function route($name, $params = [])
{
    global $routes;
    $uri = $routes[$name] ?? '';
    foreach ($params as $key => $val) {
        $uri = str_replace("{{$key}}", urlencode($val), $uri);
    }

    return url($uri);
}

// old('field_name')
function old($key, $default = '')
{
    return $_POST[$key] ?? $default;
}

// csrf_token() placeholder (custom implementation needed for real CSRF)
function csrf_token()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

// csrf_field() to insert hidden field
function csrf_field()
{
    return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
}

// session()->get('key')
function session_get($key, $default = null)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return $_SESSION[$key] ?? $default;
}

// session()->flash('key', 'value')
function session_flash($key, $value = null)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['_flash'][$key] = $value;
}

// session()->has('key')
function session_has($key)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return isset($_SESSION[$key]);
}

// session()->forget('key')
function session_forget($key)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    unset($_SESSION[$key]);
}

// redirect('path')
function redirect($path)
{
    header('Location: ' . url($path));
    exit;
}
