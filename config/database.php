<?php
class Database {
    private $host, $db, $user, $pass, $port;

    public function __construct() {
        $this->host = getenv('MYSQLHOST') ?: 'localhost';
        $this->db   = getenv('MYSQLDATABASE') ?: 'edoc_actualizado';
        $this->user = getenv('MYSQLUSER') ?: 'root';
        $this->pass = getenv('MYSQLPASSWORD') ?: '';
        $this->port = getenv('MYSQLPORT') ?: '3306';
    }

    public function connect() {
        try {
            $conn = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->db};charset=utf8",
                $this->user, $this->pass
            );
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $conn;
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            exit;
        }
    }
}