<?php

class Database {

    private $host = "localhost";
    private $db = "edoc_actualizado";
    private $user = "root";
    private $pass = "";
    private $conn;

    public function connect() {

        try {
            $this->conn = new PDO(
                "mysql:host=$this->host;dbname=$this->db",
                $this->user,
                $this->pass
            );

            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch (PDOException $e) {
            echo json_encode(["error"=>$e->getMessage()]);
        }

        return $this->conn;
    }
}