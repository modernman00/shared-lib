<?php

namespace Src;

class LaravelHelper
{
    /**
     * Defined named routes for the application.
     */
    protected static array $routes = [
        'home'    => '/',
        'about'   => '/about',
        'contact' => '/contact',
        'result'  => '/result?score={score}',
    ];

    /**
     * Get the base URL of the application.
     */
    public static function baseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $_SERVER['HTTP_HOST'];
    }

    /**
     * Generate a full URL from a relative path.
     * Example: LaravelHelper::url('about') will return
     * http://yourdomain.com/about
     * This is useful for generating links to other pages in your application.
     * If you pass a path like 'public/css/style.css', it will return
     * http://yourdomain.com/public/css/style.css
     * This assumes your application is hosted in the root directory.
     * If you want to link to a specific route, you can use:
     * LaravelHelper::url('result?score=85') which will return
     * http://yourdomain.com/result?score=85
     */
    public static function url(string $path = ''): string
    {
        return rtrim(self::baseUrl(), '/') . '/' . ltrim($path, '/');
    }

    /**
     * Generate the full URL to an asset in the /public directory.
     * This is useful for linking to CSS, JS, images, etc.
     * Example: LaravelHelper::asset('css/style.css') will return
     * http://yourdomain.com/public/css/style.css 
     * This assumes your assets are stored in the public directory.
     */
    public static function asset(string $path = ''): string
    {
        return self::url('public/' . ltrim($path, '/'));
    }

    /**
     * 
     * This method allows you to create URLs based on predefined routes.
     * For example, if you have a route named 'result', you can call:
     * LaravelHelper::route('result', ['score' => 85, 'user' => 1]);
     * This will generate a URL like: http://yourdomain.com/result?score=85&user=1
     */
    public static function route(string $path = '', array $params = []): string
{
    // e.g., path = 'result'
    $query = http_build_query($params); // score=85&user=1
    return self::url($path . ($query ? '?' . $query : ''));
}


    /**
     * Retrieve a value from the old POST data or return default.
     * This is useful for repopulating form fields after validation errors.
     * Example: LaravelHelper::old('username', 'defaultUser') will return
     * the value of 'username' from the previous POST request, or 'defaultUser'
     * if it doesn't exist. This is commonly used in form handling to retain
     * user input after a form submission fails validation.
     */
    public static function old(string $key, $default = ''): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Generate or retrieve a CSRF token from the session.
     * This is used to protect against Cross-Site Request Forgery attacks.
     * The CSRF token is a unique token that is generated for each session
     * and is included in forms to ensure that the form submission is coming
     * from the same user who requested the form.
     * Example: LaravelHelper::csrfToken() will return a unique token string.
     * If the session does not have a CSRF token, it will generate one.
     * This token should be included in forms as a hidden input field to
     * validate the form submission.
     * Example usage in a form:
     * <form method="POST" action="/submit">
     *     {{ LaravelHelper::csrfField() }}
     *     <input type="text" name="username" value="{{ LaravelHelper::old('username') }}">
     *     <button type="submit">Submit</button>
     * </form>
     * This will generate a hidden input field with the CSRF token, ensuring
     * that the form submission is secure.  
     */
    public static function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Return a hidden input field for CSRF protection.
     * This method generates a hidden input field
     * that includes the CSRF token, which should be included in forms
     * to protect against CSRF attacks.
     * Example usage in a form:
     * <form method="POST" action="/submit">
     *     {{ LaravelHelper::csrfField() }}
     *     <input type="text" name="username" value="{{ LaravelHelper::old('username') }}">
     *     <button type="submit">Submit</button>
     * </form>
     * This will generate a hidden input field with the CSRF token,
     * ensuring that the form submission is secure.
     * The generated HTML will look like:
     * <input type="hidden" name="_token" value="your_csrf_token_here">
     * This token should be validated on the server side when processing the form submission.
     */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="_token" value="' . self::csrfToken() . '">';
    }

    /**
     * Retrieve a value from the session.
     * This method allows you to access session data
     * stored in the PHP session. If the session is not active,
     * it will start the session automatically.
     * Example: LaravelHelper::sessionGet('username', 'defaultUser') will return
     * the value of 'username' from the session, or 'defaultUser'
     * if it doesn't exist. This is commonly used to retrieve user-specific
     * data that has been stored in the session, such as user preferences,
     * authentication status, or other temporary data that needs to persist
     * across requests.
     */
    public static function sessionGet(string $key, $default = null): mixed
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Flash a value to the session (store temporarily).
     * This method allows you to store a value in the session
     * that will be available for the next request only.
     * This is useful for displaying messages or notifications
     * that should only be shown once, such as success messages after
     * form submissions or error messages after validation failures.
     * Example: LaravelHelper::sessionFlash('success', 'Your profile has been updated successfully.')
     * will store the message in the session under the key 'success'.
     * The message will be available for the next request and then removed
     * from the session automatically.
     * This is commonly used in web applications to provide feedback to users
     * after they perform an action, such as submitting a form or updating their profile.
     */
    public static function sessionFlash(string $key, $value): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Check if a session key exists.
     * This method checks if a specific key exists in the session.
     * If the session is not active, it will start the session automatically.
     * Example: LaravelHelper::sessionHas('username') will return true if
     * the 'username' key exists in the session, or false if it does not.
     * This is commonly used to check if a user is logged in or if certain
     * data is available in the session before performing actions that depend
     * on that data, such as displaying user-specific content or redirecting
     * to a different page based on the session data.
     * This can help prevent errors or unexpected behavior in your application
     * by ensuring that the required session data is present before proceeding
     * with operations that rely on that data.
     */
    public static function sessionHas(string $key): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session key.
     * This method allows you to remove a specific key from the session.
     * If the session is not active, it will start the session automatically.
     * Example: LaravelHelper::sessionForget('username') will remove the 'username'
     * key from the session, effectively logging out the user or clearing
     * user-specific data from the session.
     * This is commonly used when you want to clear session data after a user
     * logs out, or when you want to remove temporary data that is no longer needed.
     * It helps keep the session clean and prevents unnecessary data from
     * persisting across requests, which can improve performance and security.
     * Example usage:
     * LaravelHelper::sessionForget('cart_items'); // This will remove the 'cart_items
     * key from the session, clearing the user's shopping cart.
     * This is useful in e-commerce applications where you want to clear the cart
     * after the user completes a purchase or decides to empty their cart.
     */
    public static function sessionForget(string $key): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        unset($_SESSION[$key]);
    }

    /**
     * Redirect the user to a given path and stop execution.
     * This method sends a HTTP header to the browser
     * to redirect the user to a specified path.
     * It is commonly used after form submissions or when you want to
     * redirect the user to a different page in your application.
     * Example: LaravelHelper::redirect('home') will redirect the user to
     * the home page of your application. If you want to redirect to a specific
     * URL, you can use LaravelHelper::redirect('https://example.com/some-page').
     * This will send a HTTP 302 redirect response to the browser, instructing it
     * to navigate to the specified URL.
     * After sending the redirect header, it calls exit() to stop further
     * execution of the script. This is important to prevent any additional
     * output or processing after the redirect, which could lead to unexpected
     * behavior or errors in your application.
     * Example usage:
     * LaravelHelper::redirect('contact'); // Redirects to the contact page
     * LaravelHelper::redirect('https://example.com/thank-you'); // Redirects to
     * a specific external URL
     * This is useful in scenarios where you want to guide the user to a
     * different page after they perform an action, such as submitting a form,
     * logging in, or completing a purchase.
     */
    public static function redirect(string $path): void
    {
        header('Location: ' . self::url($path));
        exit;
    }

    /**
     * Sanitize user input to make it safe for storage or HTML output.
     * - Optionally strips HTML tags
     * - Always encodes special characters
     * This method is used to clean user input  
     * to prevent XSS (Cross-Site Scripting) attacks
     * and ensure that the data is safe to display in HTML.
     * Example: LaravelHelper::sanitize('<script>alert("XSS")</script>', true
     * will return '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;'.
     * This means that any HTML tags will be removed, and special characters
     * will be converted to their HTML entities, making it safe to display
     * in a web page without executing any scripts.
     * If you want to keep HTML tags but still encode special characters,
     * you can call LaravelHelper::sanitize('<b>Bold Text</b>', false).
     * This will return '&lt;b&gt;Bold Text&lt;/b&gt;', preserving the
     * <b> tag but encoding the special characters.
     * This is useful when you want to allow certain HTML tags in user input,
     * such as formatting tags like <b>, <i>, or <a>, while still
     * preventing any potentially harmful scripts from being executed.
     *  
     *
     * @param string $value
     * @param bool $stripTags Whether to remove HTML tags (true by default)
     * @return string
     */
    public static function sanitize(string $value, bool $stripTags = true): string
    {
        if ($stripTags) {
            $value = strip_tags($value);
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}


