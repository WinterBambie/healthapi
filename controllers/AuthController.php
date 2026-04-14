<?php
require_once __DIR__ . '/../config/JwtHelper.php';
require_once __DIR__ . '/../config/BaseController.php';

class AuthController extends BaseController {

    public function login() {
        $d     = $this->input();
        $email = trim($d['email']    ?? '');
        $pass  = trim($d['password'] ?? '');

        if (!$email || !$pass) {
            $this->response("error", "Correo y contraseña son obligatorios.");
        }

        $stmt = $this->conn->prepare("SELECT usertype, password FROM webuser WHERE email = ?");
        $stmt->execute([$email]);
        $wu = $stmt->fetch();

        if (!$wu) {
            $this->response("error", "Usuario no registrado.");
        }
        if (!password_verify($pass, $wu['password'])) {
            $this->response("error", "Contraseña incorrecta.");
        }

        $type = $wu['usertype']; // 'p' | 'd' | 'a'

        if ($type === 'p') {
            $s = $this->conn->prepare(
                "SELECT pid AS id, pname AS name, pemail AS email, pphone AS phone, paddress AS address
                 FROM patient WHERE pemail = ?"
            );
        } elseif ($type === 'd') {
            $s = $this->conn->prepare(
                "SELECT docid AS id, dname AS name, demail AS email, dphone AS phone
                 FROM doctor WHERE demail = ?"
            );
        } else {
            $s = $this->conn->prepare(
                "SELECT email AS id, email AS name FROM webuser WHERE email = ?"
            );
        }
        $s->execute([$email]);
        $user = $s->fetch();

        $roleMap = ['p' => 'patient', 'd' => 'doctor', 'a' => 'admin'];
        $role    = $roleMap[$type] ?? 'patient';

        $token = JwtHelper::generate([
            'sub'   => $user['id'] ?? $email,
            'email' => $email,
            'role'  => $role,
            'iat'   => time(),
            'exp'   => time() + (60 * 60 * 8),
        ]);

        $this->response("success", "Login exitoso.", [
            "type"  => $role,
            "token" => $token,
            "user"  => [
                "id"      => $user['id']      ?? $email,
                "name"    => $user['name']    ?? $email,
                "email"   => $user['email']   ?? $email,
                "phone"   => $user['phone']   ?? "",
                "address" => $user['address'] ?? "",
            ],
        ]);
    }

    public function registerPatient() {
        $fname   = $_POST['fname']              ?? '';
        $lname   = $_POST['lname']              ?? '';
        $name    = trim("$fname $lname");
        $email   = trim($_POST['email']         ?? '');
        $pass    = $_POST['password']           ?? '';
        $address = $_POST['address']            ?? '';
        $nic     = $_POST['nic']                ?? '';
        $dob     = $_POST['dob']                ?? '';
        $phone   = $_POST['phone']              ?? '';
        $typeDoc = $_POST['tipo_documento_id']  ?? null;

        if (!$email || !$pass || !$nic) {
            $this->response("error", "Faltan campos obligatorios.");
        }

        $check = $this->conn->prepare("SELECT email FROM webuser WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $this->response("error", "Correo ya registrado.");
        }

        try {
            $this->conn->beginTransaction();
            $hash = password_hash($pass, PASSWORD_DEFAULT);

            $this->conn->prepare(
                "INSERT INTO patient (pemail,pname,ppassword,paddress,pdocument,pbirthdate,pphone,tipo_documento_id)
                 VALUES (?,?,?,?,?,?,?,?)"
            )->execute([$email, $name, $hash, $address, $nic, $dob, $phone, $typeDoc]);

            $this->conn->prepare(
                "INSERT INTO webuser (email, password, usertype) VALUES (?, ?, 'p')"
            )->execute([$email, $hash]);

            $this->conn->commit();
            $this->response("success", "Registro exitoso.");
        } catch (Exception $e) {
            $this->conn->rollBack();
            $this->response("error", $e->getMessage());
        }
    }

    public function getDocumentTypes() {
        $rows = $this->conn->query("SELECT id, nombre FROM tipo_documento ORDER BY nombre")->fetchAll();
        $this->response("success", "Tipos de documento.", $rows);
    }
}
