<?php

declare(strict_types=1);

namespace Src;

use Src\Exceptions\NotFoundException;
use Src\{Exceptions\UnauthorisedException };

class Token extends CheckToken
{
    /**
     * Helps to generate token, aπnd it updates the login table as well.
     * two sessions are set $_SESSION['auth']['2FA_token_ts'] and $_SESSION['auth']['identifyCust'].
    /**
     * @param array<string, mixed> $data
     * @param string                $viewPath
     * @param string|null           $subject
     *
     * @throws \Throwable
     * @throws \InvalidArgumentException
     */
    public static function generateSendTokenEmail($data, $viewPath, ?string $subject = null): void
    {
        $id = $data['id'] ?? $data['no'] ?? $data['user_id'] ?? $data['userId'] ?? null;
        // 1. check if email exists
        $email = Utility::checkInputEmail($data['email']);

        if (empty($email) || empty($id)) {
            throw new NotFoundException('Error : could not find email and id');
        }

        //2. generate token and update table
        $deriveToken = self::generateUpdateTableWithToken($email);

        $_SESSION['auth']['2FA_token_ts'] = time(); // Use 'auth' namespace
        $_SESSION['auth']['identifyCust'] = $id; // Use 'auth' namespace
        $_SESSION['auth']['email'] = $email;
        $_SESSION['auth']['2FA_challenge_mac'] = self::computeChallengeMac((string) $email);
        //TODO send text to the user with the code - xxx

        //3. ACCOMPANY EMAIL CONTENT (Heuristic-compliant subject for iOS 17+ / Android OTP autofill)
        $appName = (string) ($data['appName'] ?? $_ENV['APP_NAME'] ?? 'Security');
        $finalSubject = $subject ?? "{$deriveToken} is your {$appName} verification code";

        $baseUrl = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
        $verifyRoute = (string) ($data['verifyRoute'] ?? '/manager/verify');
        $sessId = session_id();
        $tokenParam = (string) ($_SESSION['token'] ?? ($sessId !== false ? $sessId : ''));

        $emailData = [
            'code' => $deriveToken,
            'email' => $email,
            'appName' => $appName,
            'isFunctional' => true,
        ];

        if (!empty($baseUrl)) {
            $emailData['verifyUrl'] = "{$baseUrl}{$verifyRoute}?code={$deriveToken}&token={$tokenParam}";
        }

        $generateEmailArray = ToSendEmail::genEmailArray(
            viewPath: $viewPath,
            data: $emailData,
            subject: $finalSubject
        );

        ToSendEmail::sendEmailGeneral(
            $generateEmailArray,
            'member'
        );
    }

    /**
     * to generate random byte - token.
     *
     * @throws \Exception
     */
    public static function generateAuthToken(): string
    {
        return mb_strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * Helps to generate token, aπnd it updates the login table as well.
     *
     * @param string $email
     *                          session
     *                          MUST HAVE DB_TABLE_CODE_MGT in the .env file
     *
     * @return string
     *
     * @throws \Exception
     */
    public static function generateUpdateTableWithToken(string $email): string
    {
        //5. generate code
        $code = self::generateAuthToken();

        // check if code is empty
        if (empty($code)) {
            throw new UnauthorisedException('Error : Could not generate token');
        }

        $table = $_ENV['DB_TABLE_CODE_MGT'];

        // check if table is empty

        if (empty($table)) {
            throw new UnauthorisedException('Error : Could not generate token');
        }

        // then check if the account is active on code_mgt table
        $query = Select::formAndMatchQuery(
            selection: 'SELECT_ONE',
            table: $table,
            identifier1: 'email'
        );
        $codeData = Select::selectFn2(query: (string) $query, bind: [$email]);

        if (empty($codeData)) {
            // then insert the email into the code_mgt table
            $data = ['email' => $email, 'code' => $code];
            SubmitForm::submitForm($table, $data);
        } else {
            // If an active, unused code was already generated for this exact email
            // within the last 180 seconds in the current session (e.g. registration
            // immediately followed by login), preserve that code so the first email's
            // code remains valid unless an explicit resend requested a rotation.
            $existingRow = is_array($codeData[0] ?? null) ? $codeData[0] : $codeData;
            $existingCode = strtoupper(trim((string) ($existingRow['code'] ?? '')));
            $sessionEmail = strtolower(trim((string) ($_SESSION['auth']['email'] ?? '')));
            $tokenTs = (int) ($_SESSION['auth']['2FA_token_ts'] ?? 0);
            $forceNew = !empty($_SESSION['auth']['force_new_otp']);
            unset($_SESSION['auth']['force_new_otp']);

            if (
                !$forceNew
                && $sessionEmail === strtolower(trim($email))
                && $tokenTs > 0
                && (time() - $tokenTs) < 180
                && preg_match('/^[A-Z0-9]{6}$/', $existingCode) === 1
                && !in_array($existingCode, ['LOCKED', 'USED'], true)
            ) {
                return $existingCode;
            }

            //6.  update login account table with the code
            $updateCodeToCustomer = new Update($table);
            $updateCodeToCustomer->updateTable('code', $code, 'email', $email);
        }

        return $code;
    }

    /**
     * Maximum validity window for a 2FA OTP challenge in seconds (15 minutes).
     */
    public const OTP_TTL_SECONDS = 900;

    /**
     * Compute a session-bound HMAC tying the 2FA challenge to the client's /24 subnet and User-Agent.
     */
    public static function computeChallengeMac(string $email): string
    {
        $ip = Utility::getUserIpAddr();
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $subnet = \Src\Auth\AdminGuardMiddleware::extractSubnet($ip);
        $secret = (string) ($_ENV['APP_KEY'] ?? $_ENV['JWT_KEY'] ?? 'sovereign-2fa-challenge-salt');
        return hash_hmac('sha256', strtolower(trim($email)) . '|' . $subnet . '|' . $ua, $secret);
    }

    /**
     * Checks if the token is valid.
     *
     * @param mixed $code
     *
     * @return bool
     */
    public static function verifyToken($code)
    {
        if (is_string($code)) {
            $eastern = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩','۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
            $western = ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'];
            $code = strtoupper(str_replace($eastern, $western, trim($code)));
        }
        $code = Utility::checkInput($code);
        if (!is_string($code) || trim($code) === '' || in_array(strtoupper(trim($code)), ['LOCKED', 'USED'], true)) {
            throw new UnauthorisedException('Cannot verify token. Invalid code provided.');
        }
        $code = strtoupper(trim($code));
        $email = cleanSession($_SESSION['auth']['email'] ?? null);
        if (!is_string($email) || trim($email) === '') {
            throw new UnauthorisedException('Cannot verify token. No active verification session.');
        }

        // 1. Enforce strict 15-minute OTP TTL check if timestamp was recorded in session
        $tokenTs = (int) ($_SESSION['auth']['2FA_token_ts'] ?? 0);
        if ($tokenTs > 0 && (time() - $tokenTs) > self::OTP_TTL_SECONDS) {
            $updateCode = new Update($_ENV['DB_TABLE_CODE_MGT']);
            $updateCode->updateTable('code', 'LOCKED', 'email', $email);
            unset($_SESSION['auth']['otp_attempts'], $_SESSION['auth']['pendingUser']);
            throw new UnauthorisedException('Your verification code has expired. Please request a new one.');
        }

        // 2. Verify pre-auth challenge MAC if set (prevents cross-subnet session fixation / OTP replay)
        if (!empty($_SESSION['auth']['2FA_challenge_mac'])) {
            $expectedMac = self::computeChallengeMac($email);
            if (!hash_equals((string) $_SESSION['auth']['2FA_challenge_mac'], $expectedMac)) {
                unset($_SESSION['auth']['pendingUser']);
                throw new UnauthorisedException('Verification session network mismatch. Please log in again.');
            }
        }

        // 3. Enforce DB-backed rate limiter so stateless cookie-dropping agents cannot bypass 3-attempt session lockouts
        try {
            Limiter::limit('otp_verify:' . strtolower($email), 'login');
        } catch (\Src\Exceptions\TooManyRequestsException $e) {
            $updateCode = new Update($_ENV['DB_TABLE_CODE_MGT']);
            $updateCode->updateTable('code', 'LOCKED', 'email', $email);
            throw new UnauthorisedException('Too many failed attempts. Your verification code has been locked. Please request a new one.');
        }
        $query = Select::formAndMatchQuery(
            selection: 'SELECT_COL_DYNAMICALLY_ID_AND',
            table: $_ENV['DB_TABLE_CODE_MGT'],
            identifier1: 'email',
            identifier2: 'code',
            colArray: ['code', 'email']
        );
        
        $data = Select::selectCountFn2(
            query: (string) $query,
            bind: [$email, $code]
        );
        
        if (!$data) {
            $_SESSION['auth']['otp_attempts'] = ($_SESSION['auth']['otp_attempts'] ?? 0) + 1;
            if ($_SESSION['auth']['otp_attempts'] >= 3) {
                // Invalidate the token in DB to force requesting a new one
                $updateCode = new Update($_ENV['DB_TABLE_CODE_MGT']);
                $updateCode->updateTable('code', 'LOCKED', 'email', (string) $email);
                unset($_SESSION['auth']['otp_attempts']);
                throw new UnauthorisedException('Too many failed attempts. Your verification code has been locked. Please request a new one.');
            }
            throw new UnauthorisedException('Cannot verify token. Invalid code provided.');
        }

        // Reset attempts on success
        unset($_SESSION['auth']['otp_attempts']);

        // Invalidate token in DB to prevent replay attacks
        $updateCode = new Update($_ENV['DB_TABLE_CODE_MGT']);
        $updateCode->updateTable('code', 'USED', 'email', (string) $email);

        // JwtHandler::authenticate() stashes the password-verified user here instead
        // of setting the auth cookie right away, so that a password alone can never
        // grant access. Now that the emailed code has actually been confirmed, this
        // is the single choke point (used by every 2FA verification flow in the
        // library) where it's safe to issue the persistent login cookie - callers
        // don't need to know or do anything extra for this to happen.
        $pendingUser = $_SESSION['auth']['pendingUser'] ?? null;
        if ($pendingUser && ($pendingUser['email'] ?? null) === $email) {
            JwtHandler::issueLoginCookie($pendingUser);
        }
        unset($_SESSION['auth']['pendingUser']);

        return true;
    }
}
