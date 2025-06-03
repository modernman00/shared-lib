<?php

namespace Src;

use Src\Db;
use Src\Utility;
use Src\JwtHandler;
use Src\Exceptions\HttpException;
use Src\Exceptions\UnauthorisedException;

class Auth extends JwtHandler
{
    protected $headers;
    protected $token;

    public function __construct($headers)
    {
        parent::__construct();
        $this->headers = $headers;
    }

    public function isAuth()
    {
        try {
            if (array_key_exists('waleToken', $_COOKIE) && !empty(trim($this->headers))) {
                $data = $this->jwtDecodeData($this->headers);
                if (isset($data['auth']) && isset($data['data']->id)) {
                    $fetchData =  $this->fetchUser($data['data']->id);
                } else {
                    throw new UnauthorisedException("Could not use token to locate users");
                }
            } else {
                throw new HttpException("Header not found");
            }
            return $fetchData;
        } catch (\Throwable $th) {
            Utility::showError($th);
        }
    }

    /**
     * @return null|string
     *
     * @psalm-return 'SUCCESSFUL'|null
     */
    protected function fetchUser($user_id)
    {
        try {
            $query = "SELECT `email` FROM `account` WHERE `id`=?";
            $query_stmt = Db::connect2()->prepare($query);
            $query_stmt->execute([$user_id]);
            if ($query_stmt->rowCount()) {
                return "SUCCESSFUL";
            } else {
                return null;
            }
        } catch (\PDOException $e) {
            Utility::showError($e);
        }
    }
}
