<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/Mailer.php';

class PasswordResetController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
        $this->ensureTable();
    }

    private function ensureTable() {
        $this->conn->exec(
            "CREATE TABLE IF NOT EXISTS password_resets (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                email      VARCHAR(255) NOT NULL,
                token      VARCHAR(100) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                used       TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_token (token),
                INDEX idx_email (email)
            )"
        );
    }

    // ── POST ?action=forgotPassword ───────────────────────────────────────────
    public function forgotPassword() {
        $data  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $email = trim($data['email'] ?? '');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(["status" => "error", "message" => "Correo inválido."]); return;
        }

        // Buscar usuario en webuser
        $stmt = $this->conn->prepare("SELECT email FROM webuser WHERE email = ?");
        $stmt->execute([$email]);
        if (!$stmt->fetch()) {
            // Por seguridad devolvemos success aunque no exista
            echo json_encode(["status" => "success", "message" => "Si el correo existe, recibirás un enlace."]); return;
        }

        // Buscar nombre del usuario
        $name = $this->getNameByEmail($email);

        // Limpiar tokens anteriores del mismo email
        $this->conn->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

        // Generar token seguro
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hora

        $this->conn->prepare(
            "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)"
        )->execute([$email, $token, $expiresAt]);

        // Enviar email
            $sent = Mailer::passwordReset($email, $name, $token);
            if (!$sent) {
                echo json_encode(["status" => "error", "message" => "No se pudo enviar el correo."]);
                return;
            }
            echo json_encode(["status" => "success", "message" => "Si el correo existe, recibirás un enlace."]);
    }

    // ── POST ?action=resetPassword ────────────────────────────────────────────
    public function resetPassword() {
        $data     = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $token    = trim($data['token']    ?? '');
        $password = trim($data['password'] ?? '');

        if (!$token || !$password) {
            echo json_encode(["status" => "error", "message" => "Faltan parámetros."]); return;
        }
        if (strlen($password) < 6) {
            echo json_encode(["status" => "error", "message" => "La contraseña debe tener al menos 6 caracteres."]); return;
        }

        // Buscar token válido y no usado
        $stmt = $this->conn->prepare(
            "SELECT email FROM password_resets
             WHERE token = ? AND expires_at > NOW() AND used = 0"
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(["status" => "error", "message" => "Enlace inválido o expirado."]); return;
        }

        $email = $row['email'];
        $hash  = password_hash($password, PASSWORD_DEFAULT);

        try {
            $this->conn->beginTransaction();

            // Actualizar webuser
            $this->conn->prepare(
                "UPDATE webuser SET password = ? WHERE email = ?"
            )->execute([$hash, $email]);

            // Actualizar patient si existe
            $this->conn->prepare(
                "UPDATE patient SET ppassword = ? WHERE pemail = ?"
            )->execute([$hash, $email]);

            // Actualizar doctor si existe
            $this->conn->prepare(
                "UPDATE doctor SET dpassword = ? WHERE demail = ?"
            )->execute([$hash, $email]);

            // Marcar token como usado
            $this->conn->prepare(
                "UPDATE password_resets SET used = 1 WHERE token = ?"
            )->execute([$token]);

            $this->conn->commit();
            echo json_encode(["status" => "success", "message" => "Contraseña actualizada correctamente."]);
        } catch (Exception $e) {
            $this->conn->rollBack();
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    }

    // ── GET ?action=validateResetToken&token=xxx ──────────────────────────────
    public function validateToken() {
        $token = $_GET['token'] ?? '';
        $stmt  = $this->conn->prepare(
            "SELECT email FROM password_resets
             WHERE token = ? AND expires_at > NOW() AND used = 0"
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        echo json_encode($row
            ? ["status" => "success", "message" => "Token válido."]
            : ["status" => "error",   "message" => "Token inválido o expirado."]);
    }

    private function getNameByEmail(string $email): string {
        $p = $this->conn->prepare("SELECT pname FROM patient WHERE pemail = ?");
        $p->execute([$email]);
        $r = $p->fetchColumn();
        if ($r) return $r;

        $d = $this->conn->prepare("SELECT dname FROM doctor WHERE demail = ?");
        $d->execute([$email]);
        $r = $d->fetchColumn();
        return $r ?: $email;
    }
}