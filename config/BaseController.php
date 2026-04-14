<?php
// Centraliza la conexión y las respuestas para todos los controladores
require_once __DIR__ . '/database.php';

class BaseController {
    protected $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    // Método estándar para responder a React
    protected function response($status, $message, $data = null) {
        echo json_encode([
            "status" => $status,
            "message" => $message,
            "data" => $data
        ]);
        exit;
    }
}