<?php
require_once __DIR__ . '/database.php';

class BaseController {
    protected $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    // Respuesta JSON estándar
    protected function response($status, $message, $data = null) {
        echo json_encode([
            "status"  => $status,
            "message" => $message,
            "data"    => $data,
        ]);
        exit;
    }

    // Lee FormData ($_POST) o JSON del body
    protected function input(): array {
        if (!empty($_POST)) return $_POST;
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
}
