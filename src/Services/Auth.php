<?php

declare(strict_types=1);

namespace Ilyamur\TasksApp\Services;

use Ilyamur\TasksApp\Gateways\UserGateway;
use Ilyamur\TasksApp\Exceptions\InvalidSignatureException;
use Ilyamur\TasksApp\Exceptions\TokenExpiredException;

class Auth
{
    private string $userId;

    public function __construct(
        private UserGateway $userGateway,
        private JWTCodec $codec
    ) {
    }

    public function authenticate(): bool
    {
        // selecting type of auth (JWT token or basic API key)
        // adjusting in the config file
        return JWT_AUTH ? $this->authenticateByJWT() : $this->authenticateByKey();
    }

    public function getUserID(): string
    {
        return $this->userId;
    }

    protected function authenticateByKey(): bool
    {
        $apiKey = $this->getAPIKeyFromHeader();

        if (!$apiKey) {
            $this->respondWarnMessage('missing API key');
            return false;
        };

        $user = $this->userGateway->getByAPIKey($apiKey);

        if ($user === false) {
            $this->respondWarnMessage('invalid API key', 401);
            return false;
        }
        $this->userId = (string) $user['id'];

        return true;
    }

    protected function authenticateByJWT(): bool
    {
        // check if Bearer key persist in the beginning of auth header
        if (!preg_match("/^Bearer\s+(.*)$/", $this->getJWTFromHeader(), $matches)) {
            $this->respondWarnMessage('incomplete authorization header');

            return false;
        }

        // decode JWT token and catching exception if its incorrect
        try {
            $data = $this->codec->decode($matches[1]);
        } catch (InvalidSignatureException) {
            $this->respondWarnMessage('invalid signature', 401);

            return false;
        } catch (TokenExpiredException) {
            $this->respondWarnMessage('token has expired', 401);

            return false;
        } catch (\Exception $e) {
            $this->respondWarnMessage($e->getMessage(), 400);

            return false;
        }
        $this->userId = (string) $data['sub'];

        return true;
    }

    protected function getAPIKeyFromHeader(): ?string
    {
        return empty($_SERVER['HTTP_X_API_KEY']) ? null : $_SERVER['HTTP_X_API_KEY'];
    }

    protected function getJWTFromHeader(): ?string
    {
        return empty($_SERVER['HTTP_AUTHORIZATION']) ? null : $_SERVER['HTTP_AUTHORIZATION'];
    }

    protected function respondWarnMessage(string $msg, int $statusCode = 400): void
    {
        http_response_code($statusCode);
        $this->renderJSON(['message' => $msg]);
    }

    protected function renderJSON(array | string $item): void
    {
        echo json_encode($item);
    }
}
