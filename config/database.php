<?php

class Database {

    private $host = "kodama.proxy.rlwy.net";
    private $db   = "railway";
    private $user = "root";
    private $pass = "GejrRXAHOyNbLyiCYtwmSpYssenQyJrV";
    private $port = "27960";

    public $conn;

    public function connect() {

        try {

            $this->conn = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->db};charset=utf8mb4",
                $this->user,
                $this->pass
            );

            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            return $this->conn;

        } catch(PDOException $e) {

            die("Connection failed: " . $e->getMessage());
        }
    }
}