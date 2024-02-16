<?php

use Firebase\JWT\JWT;
use Joyonto\UserManagement\Models\User;

define("BASE_DIR", dirname(__DIR__));
// Require composer autoloader
require  implode(DIRECTORY_SEPARATOR, [BASE_DIR, 'vendor', 'autoload.php']);

$dotenv = Dotenv\Dotenv::createImmutable(BASE_DIR);
$dotenv->load();

// Create Router instance
$router = new \Bramus\Router\Router();


try {
    User::init();
} catch (\Throwable $th) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');

    echo json_encode([
        'message' => $th->getMessage(),
        'trace' => $th->getTrace()
    ]);

    return;
}



$router->get('/', function () {
    echo "OK";
});

// $router->options('.*', function () {
//     header('Access-Control-Allow-Origin: http://localhost:5173');
//     header('Access-Control-Allow-Credentials: true');
//     header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, HEAD, OPTIONS');
//     header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Access-Control-Request-Method');
// });

header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Access-Control-Request-Method');

$router->post("/api/auth/register", function () {
    try {
        /**
         * @var array{email: string, password: string, username: string} $data
         */
        $data = json_decode(file_get_contents("php://input"), true) ?? [];
        $errors = [];

        if (!array_key_exists('email', $data)) {
            if (!array_key_exists('email', $errors)) {
                $errors['email'] = [];
            }

            $errors['email'][] = 'Email is required';
        }

        if (!array_key_exists('username', $data)) {
            if (!array_key_exists('username', $errors)) {
                $errors['username'] = [];
            }

            $errors['username'][] = 'Username is required';
        }

        if (!array_key_exists('password', $data)) {
            if (!array_key_exists('password', $errors)) {
                $errors['password'] = [];
            }

            $errors['password'][] = 'password is required';
        }

        if (array_key_exists('email', $data) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            if (!array_key_exists('email', $errors)) {
                $errors['email'] = [];
            }

            $errors['email'][] = 'Email is invalid';
        }

        if (array_key_exists('password', $data) && strlen($data['password']) < 8) {
            if (!array_key_exists('password', $errors)) {
                $errors['password'] = [];
            }

            $errors['password'][] = 'password length should be at least 8 characters long';
        }


        if (!empty($errors)) {
            header('HTTP/1.1 422 Unprocessable Entity');
            header('Content-Type: application/json');
            echo json_encode(compact('errors'));

            return;
        }

        $user = new User;

        $result = $user->findByEmail($data['email']);
        if (!is_null($result)) {
            header('HTTP/1.1 422 Unprocessable Entity');
            header('Content-Type: application/json');

            echo json_encode([
                'errors' => [
                    'email' => ["Email is already taken"]
                ]
            ]);

            return;
        }

        $success = $user->create($data);
        if (!$success) {
            header('HTTP/1.1 500 Internal Server Error');
        }

        header('Content-Type: application/json');
        echo json_encode([
            'message' => $success ? 'Successfully registered. You can now login' : 'Something went wrong.'
        ]);
    } catch (\Throwable $th) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');

        echo json_encode([
            'message' => $th->getMessage(),
            'trace' => $th->getTrace()
        ]);
    }
});


$router->post("/api/auth/login", guest(function () {
    try {
        /**
         * @var array{email: string, password: string} $data
         */
        $data = json_decode(file_get_contents("php://input"), true) ?? [];



        if (!array_key_exists('email', $data) || !array_key_exists('password', $data)) {
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json');

            echo json_encode([
                'message' => "Credentials does not match"
            ]);

            return;
        }
        $user = new User;
        $user = $user->findByEmail($data['email'] ?? "");
        if (is_null($user) || !$user->matchPassword($data['password'])) {
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json');

            echo json_encode([
                'message' => "Credentials does not match"
            ]);

            return;
        }

        // if (!empty($errors)) {
        //     header('HTTP/1.1 422 Unprocessable Entity');
        //     header('Content-Type: application/json');
        //     echo json_encode(compact('errors'));

        //     return;
        // }

        $issuedAt = new DateTimeImmutable();
        $exp = $issuedAt->add(DateInterval::createFromDateString('1 day'))->getTimestamp();
        $key = $_ENV['APP_SECRET'];
        $payload = [
            'iss' => 'http://localhost:8080',
            'aud' => 'access_token',
            'sub' => $user->getId(),
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $exp
        ];

        $accessToken = JWT::encode($payload, $key, 'HS256');
        $secureCookie = $_ENV['APP_ENV'] === 'production';

        header('Content-Type: application/json');
        setcookie('access_token', $accessToken, $exp,  '/',  'localhost',  $secureCookie,  true);
        echo json_encode([
            'user' => $user,
            'message' => 'Login success',
        ]);
    } catch (\Throwable $th) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');

        echo json_encode([
            'message' => $th->getMessage(),
            'trace' => $th->getTrace()
        ]);
    }
}));

$router->post("/api/auth/logout", auth(function () {
    try {
        if (isset($_COOKIE['access_token'])) {
            unset($_COOKIE['access_token']);
            $secureCookie = $_ENV['APP_ENV'] === 'production';
            setcookie('access_token', '', -1, '/', 'localhost', $secureCookie, true);
        }

        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'Logout success',
        ]);
    } catch (\Throwable $th) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');

        echo json_encode([
            'message' => $th->getMessage(),
            'trace' => $th->getTrace()
        ]);
    }
}));

$router->get('/api/users', auth(function () {
    try {
        $page = $_GET['page'] ?? 1;
        $page = abs($page);
        $perPage = $_GET['per_page'] ?? 15;
        $perPage = abs($perPage);
        $offset = ($page - 1) * $perPage;

        $user = new User;

        $result =  $user->paginate($offset, $perPage, [
            'query' => $_GET['query'] ?? ''
        ]);

        $totalUsers = $user->count();
        $lastPage = ceil($totalUsers / ($perPage));

        header('Content-Type: application/json');
        echo json_encode([
            'data' => $result,
            'total' => $totalUsers,
            'currect_page' => $page,
            'last_page' => $lastPage
        ]);
    } catch (\Throwable $th) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');

        echo json_encode([
            'message' => $th->getMessage(),
            'trace' => $th->getTrace()
        ]);
    }
}));

$router->delete('/api/users', auth(function (User $auth) {
    try {
        $success = $auth->delete();

        if ($success && isset($_COOKIE['access_token'])) {
            unset($_COOKIE['access_token']);
            $secureCookie = $_ENV['APP_ENV'] === 'production';
            setcookie('access_token', '', -1, '/', 'localhost', $secureCookie, true);
        }

        echo json_encode([
            'message' => $success ? "Account deleted" : "Something went wrong"
        ]);
    } catch (\Throwable $th) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');

        echo json_encode([
            'message' => $th->getMessage(),
            'trace' => $th->getTrace()
        ]);
    }
}));

$router->put('/api/users', auth(function (User $auth) {
    try {
        /**
         * @var array{email: string, password: string, username: string} $data
         */
        $data = json_decode(file_get_contents("php://input"), true) ?? [];
        $errors = [];

        if (array_key_exists('email', $data)) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                if (!array_key_exists('email', $errors)) {
                    $errors['email'] = [];
                }

                $errors['email'][] = 'Email is invalid';
            }

            $user = new User;
            $result = $user->findByEmail($data['email']);
            if (!is_null($result) && $result->getId() !== $auth->getId()) {
                $errors['email'][] = 'Email is already taken';
            }
        }

        if (array_key_exists('password', $data) && strlen($data['password']) < 8) {
            if (!array_key_exists('password', $errors)) {
                $errors['password'] = [];
            }

            $errors['password'][] = 'password length should be at least 8 characters long';
        }


        if (!empty($errors)) {
            header('HTTP/1.1 422 Unprocessable Entity');
            header('Content-Type: application/json');
            echo json_encode(compact('errors'));

            return;
        }

        if (array_key_exists('email', $data)) {
            $auth->setEmail($data["email"]);
        }

        if (array_key_exists('username', $data)) {
            $auth->setUsername($data["username"]);
        }

        if (array_key_exists('password', $data)) {
            $auth->setPassword($data["password"]);
        }

        $auth->update();

        header('Content-Type: application/json');

        echo json_encode([
            'message' => 'Update successful',
            'user' => $auth
        ]);
    } catch (\Throwable $th) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');

        echo json_encode([
            'message' => $th->getMessage(),
            'trace' => $th->getTrace()
        ]);
    }
}));

$router->set404(function () {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: application/json');

    echo json_encode([
        'message' => 'Not Found'
    ]);
});


$router->run();
