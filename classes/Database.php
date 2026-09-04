<?php

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $host = "localhost";
        $user = "root";
        $pass = "";
        $dbname = "incident_update";

        $this->conn = new mysqli($host, $user, $pass, $dbname);
        if ($this->conn->connect_error) {
            die("Database connection failed: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    public function prepare($sql) {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die("Prepare failed: " . $this->conn->error . " | SQL: " . $sql);
        }
        return $stmt;
    }

    public function query($sql) {
        $result = $this->conn->query($sql);
        if ($result === false) {
            die("Query failed: " . $this->conn->error . " | SQL: " . $sql);
        }
        return $result;
    }

    public function escape($str) {
        return $this->conn->real_escape_string((string)$str);
    }
}
