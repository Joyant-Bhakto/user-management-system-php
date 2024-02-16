<?php

namespace Joyonto\UserManagement\Models;

use DateTime;
use JsonSerializable;
use PDO;

class User extends Model implements JsonSerializable
{
    private int $id;
    private string $username;
    private string $email;
    private string $password;

    public function __construct()
    {
        parent::__construct();
    }

    public static function getTable(): string
    {
        return "users";
    }

    public static function createTable(): string
    {
        return "CREATE TABLE IF NOT EXISTS" . static::getTable() . " (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT NOW(),
            updated_at DATETIME ON UPDATE NOW()
        )";
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'username' => $this->username,
            'created_at' => $this->getCreatedAt()->format(DateTime::ATOM),
            'updated_at' => $this->getUpdatedAt()?->format(DateTime::ATOM)
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function setPassword($value)
    {
        $this->password = password_hash($value, null);
    }

    public function setUsername($value)
    {
        $this->username = $value;
    }

    public function setEmail($value)
    {
        $this->email = $value;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function insert(): bool
    {
        $stmt =  $this->db->prepare('INSERT INTO ' . $this->getTable() . ' (username, email, password) VALUES (:username, :email, :password)');
        return $stmt->execute([
            'email' => $this->email,
            'username' => $this->username,
            'password' => $this->password
        ]);
    }

    public function findById(int $id): User|null
    {
        $stmt =  $this->db->prepare('SELECT * FROM ' . $this->getTable() . ' WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetchObject($this::class);

        if (false === $user) {
            return null;
        }

        return $user;
    }

    public function findByEmail(string $email): User|null
    {
        $stmt =  $this->db->prepare('SELECT * FROM ' . $this->getTable() . ' WHERE email = :email');
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetchObject($this::class);

        if (false === $user) {
            return null;
        }

        return $user;
    }

    public function matchPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    public function delete(): bool
    {
        $stmt = $this->db->prepare('DELETE FROM ' . $this->getTable() . ' WHERE id = :id');

        $stmt->bindValue(
            ':id',
            $this->getId(),
        );

        return $stmt->execute();
    }

    public function update(): bool
    {
        $stmt = $this->db->prepare("UPDATE " . $this->getTable() . " SET username = :username, email = :email, password = :password WHERE id = :id");
        $stmt->bindValue("id", $this->getId(), PDO::PARAM_INT);
        $stmt->bindValue("username", $this->getUsername());
        $stmt->bindValue("email", $this->getEmail());
        $stmt->bindValue("password", $this->getPassword());

        return $stmt->execute();
    }

    public function count(): int
    {
        $stmt = $this->db->query('SELECT COUNT(id) as total_users FROM ' . $this->getTable());
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    /**
     *
     * @param integer $offset
     * @param integer $limit
     * @return User[]
     */
    public function paginate(int $offset, int $limit, array $filters = [])
    {
        $stmt = $this->db->prepare('SELECT * FROM ' . $this->getTable() .
            ' WHERE username LIKE :query OR email LIKE :query ORDER BY created_at DESC LIMIT :limit OFFSET :offset');


        $query = $filters['query'] ? "%{$filters['query']}%" : "%%";
        $stmt->bindParam(
            ':query',
            $query,
        );


        $stmt->bindParam(
            ':limit',
            $limit,
            PDO::PARAM_INT
        );

        $stmt->bindParam(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );

        $stmt->execute();

        $result =  $stmt->fetchAll(PDO::FETCH_CLASS, $this::class);

        if (false === $result) {
            return [];
        }

        return $result;
    }

    /**
     * create a new user
     *
     * @param array{email: string, username: string, password: string} $data
     * @return self
     */
    public static function create(array $data): bool
    {
        $user = new static();

        $user->setEmail($data['email']);
        $user->setPassword($data['password']);
        $user->setUsername($data['username']);
        return $user->insert();
    }
}
