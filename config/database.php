<?php
class Database {
    private $host;
    private $port;
    private $db;
    private $user;
    private $pass;

    public function __construct() {
        $this->host = getenv('MYSQLHOST')     ?: 'mysql.railway.internal';
        $this->port = getenv('MYSQLPORT')     ?: '3306';
        $this->db   = getenv('MYSQLDATABASE') ?: 'railway';
        $this->user = getenv('MYSQLUSER')     ?: 'root';
        $this->pass = getenv('MYSQLPASSWORD') ?: '';
    }

    public function connect() {
        try {
            $conn = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->db};charset=utf8",
                $this->user,
                $this->pass
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