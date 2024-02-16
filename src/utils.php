<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Joyonto\UserManagement\Models\User;

function auth(Closure $callback)
{
    return function (...$args) use ($callback) {
        try {
            $accessToken = $_COOKIE['access_token'] ?? "";

            $key = $_ENV['APP_SECRET'] ?? "";

            $decoded = JWT::decode($accessToken, new Key($key, 'HS256'));

            $user = new User;

            $user = $user->findById($decoded->sub);

            if (is_null($user)) {
                header('HTTP/1.1 401 Unauthorized');
                header('Content-Type: application/json');

                echo json_encode([
                    'message' => 'Unauthenticated',
                ]);
                return;
            }

            return $callback($user, ...$args);
        } catch (UnexpectedValueException $e) {
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json');

            echo json_encode([
                'message' => 'Unauthenticated',
            ]);
        } catch (\Throwable $th) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: application/json');
            echo json_encode([
                'message' => $th->getMessage(),
                'trace' => $th->getTrace()
            ]);
        }
    };
}

function guest(Closure $callback)
{
    return function (...$args) use ($callback) {
        try {
            $accessToken = $_COOKIE['access_token'] ?? "";

            $key = $_ENV['APP_SECRET'] ?? "";

            if (!empty($accessToken)) {
                $decoded = JWT::decode($accessToken, new Key($key, 'HS256'));

                $user = new User;

                $user = $user->findById($decoded->sub);

                if (!is_null($user)) {
                    header('Content-Type: application/json');

                    echo json_encode([
                        'message' => 'Logged in',
                    ]);
                    return;
                }
            }

            return $callback(...$args);
        } catch (\Throwable $th) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: application/json');
            echo json_encode([
                'message' => $th->getMessage(),
                'trace' => $th->getTrace()
            ]);
        }
    };
}
