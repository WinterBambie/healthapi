<?php
require_once __DIR__ . '/../config/JwtHelper.php';

class AuthController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }

    public function login() {
        $input    = json_decode(file_get_contents('php://input'), true) ?? [];
        $email    = $input['email']    ?? $_POST['email']    ?? '';
        $password = $input['password'] ?? $_POST['password'] ?? '';

        $stmt = $this->conn->prepare("SELECT usertype, password FROM webuser WHERE email=?");
        $stmt->execute([$email]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$res) {
            echo json_encode(["status" => "error", "message" => "Usuario no registrado"]);
            return;
        }

        if (!password_verify($password, $res['password'])) {
            echo json_encode(["status" => "error", "message" => "Contraseña incorrecta"]);
            return;
        }

        $type = $res['usertype'];

        if ($type === 'p') {
            $stmt2 = $this->conn->prepare("SELECT pid as id, pname as name FROM patient WHERE pemail=?");
        } elseif ($type === 'd') {
            $stmt2 = $this->conn->prepare("SELECT docid as id, dname as name FROM doctor WHERE demail=?");
        } else {
            $stmt2 = $this->conn->prepare("SELECT email as id, email as name FROM admin WHERE email=?");
        }

        $stmt2->execute([$email]);
        $userData = $stmt2->fetch(PDO::FETCH_ASSOC);

        $typeMap = ['p' => 'patient', 'd' => 'doctor', 'a' => 'admin'];
        $role    = $typeMap[$type];

        // ✅ Generar JWT con id, email y rol
        $token = JwtHelper::generate([
            'sub'   => $userData['id'] ?? $email,
            'email' => $email,
            'role'  => $role,
            'iat'   => time(),
            'exp'   => time() + (60 * 60 * 8), // 8 horas
        ]);

        echo json_encode([
            "status" => "success",
            "type"   => $role,
            "token"  => $token,
            "user"   => [
                "id"    => $userData['id']   ?? $email,
                "name"  => $userData['name'] ?? $email,
                "email" => $email,
            ]
        ]);
    }

    public function registerPatient() {
        $fname   = $_POST['fname']   ?? '';
        $lname   = $_POST['lname']   ?? '';
        $name    = trim($fname . ' ' . $lname);
        $email   = $_POST['email']   ?? '';
        $password= $_POST['password']?? '';
        $address = $_POST['address'] ?? '';
        $nic     = $_POST['nic']     ?? '';
        $dob     = $_POST['dob']     ?? '';
        $phone   = $_POST['phone']   ?? '';
        $typeDoc = $_POST['tipo_documento_id'] ?? null;

        if (!$email || !$password || !$nic) {
            echo json_encode(["status" => "error", "message" => "Faltan campos"]);
            return;
        }

        $check = $this->conn->prepare("SELECT email FROM webuser WHERE email=?");
        $check->execute([$email]);
        if ($check->fetch()) {
            echo json_encode(["status" => "error", "message" => "Correo ya registrado"]);
            return;
        }

        try {
            $this->conn->beginTransaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $this->conn->prepare(
                "INSERT INTO patient (pemail,pname,ppassword,paddress,pdocument,pbirthdate,pphone,tipo_documento_id)
                 VALUES (?,?,?,?,?,?,?,?)"
            )->execute([$email,$name,$hash,$address,$nic,$dob,$phone,$typeDoc]);

            $this->conn->prepare(
                "INSERT INTO webuser (email,password,usertype) VALUES (?,?,'p')"
            )->execute([$email,$hash]);

            $this->conn->commit();
            echo json_encode(["status" => "success", "message" => "Registro exitoso"]);
        } catch (Exception $e) {
            $this->conn->rollBack();
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    }

    public function getDocumentTypes() {
        $stmt = $this->conn->query("SELECT id, nombre FROM tipo_documento ORDER BY nombre");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}