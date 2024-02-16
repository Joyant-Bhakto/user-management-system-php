<?php

namespace Joyonto\UserManagement\Models;

use DateTime;
use PDO;

abstract class Model
{
    protected PDO $db;

    protected DateTime $createdAt;
    protected ?DateTime $updatedAt;

    public function __construct()
    {
        $dbHost = $_ENV['DB_HOST'];
        $dbUser = $_ENV['DB_USER'];
        $dbPass = $_ENV['DB_PASS'];
        $dbName = $_ENV['DB_NAME'];
        $dbPort = $_ENV['DB_PORT'];
        $this->db = new PDO(
            "mysql:host={$dbHost};port={$dbPort};dbname={$dbName}",
            $dbUser,
            $dbPass
        );

        $stmt = $this->db->prepare("SELECT * 
        FROM information_schema.tables
        WHERE table_schema = :dbName 
            AND table_name = :tableName
        LIMIT 1");

        $stmt->execute([
            'dbName' => $dbName,
            'tableName' => $this->getTable(),
        ]);

        $data = $stmt->fetch();

        if (false === $data) {
            $stmt = $this->db->prepare($this->createTable());
            $stmt->execute();
        }
    }

    public static function init(): self
    {
        return new static();
    }

    abstract static public function getTable(): string;
    abstract static public function createTable(): string;

    public function  getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function  getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function __set($name, $value)
    {
        if ($name === 'created_at') {
            $this->createdAt = new DateTime($value);
        }

        if ($name === 'updated_at') {
            $this->updatedAt = is_null($value) ? null : new DateTime($value);
        }
    }
}
