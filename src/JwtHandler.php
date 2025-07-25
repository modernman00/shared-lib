<?php

declare(strict_types=1);

namespace Src;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;

class JwtHandler
{
    protected $jwtSecret;

    protected $token;

    protected $issuedAt;

    protected $expired;

    protected $jwt;

    public function __construct()
    {
        date_default_timezone_set('Europe/London');

        $this->issuedAt = time();

        //Token validity  2 hours (7300)
        $this->expired = $this->issuedAt + getenv('COOKIE_EXPIRE');

        // secret word or signature
        $this->jwtSecret = getenv('JWT_TOKEN');
    }

    // encoding the token

    public function jwtEncodeData($serverName, $data)
    {
        $this->token = [
            'iss' => $serverName,
            'aud' => $serverName,
            'iat' => $this->issuedAt,
            'nbf' => $this->issuedAt,
            'exp' => $this->expired,
            'data' => $data,
        ];
        $this->jwt = JWT::encode($this->token, $this->jwtSecret, 'HS512');

        return $this->jwt;
    }

    protected function errMsg($msg)
    {
        return [
            'auth' => 0,
            'message' => $msg,
        ];
    }

    //DECODING THE TOKEN
    public function jwtDecodeData($jwtToken)
    {
        try {
            $decode = JWT::decode($jwtToken, $this->jwtSecret, ['HS512']);

            return [
                'auth' => 1,
                'data' => $decode->data,
            ];
        } catch (ExpiredException | SignatureInvalidException | BeforeValidException | \DomainException | \InvalidArgumentException | \UnexpectedValueException $e) {
            return $this->errMsg($e->getMessage());
        }
    }
}
