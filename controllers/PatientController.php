<?php
require_once __DIR__ . '/../models/AppointmentModel.php';
require_once __DIR__ . '/../config/BaseController.php';

class PatientController extends BaseController {
    private $model;

    public function __construct() {
        parent::__construct();
        $this->model = new AppointmentModel($this->conn);
    }

    // ── Citas ─────────────────────────────────────────────────────────────────

    public function getByPatient() {
        // Acepta ?patient_id= o ?pid=
        $pid = $_GET['patient_id'] ?? $_GET['pid'] ?? null;
        if (!$pid) { $this->response("error", "Falta patient_id."); }
        $this->response("success", "Citas.", $this->model->getByPatient($pid));
    }

    public function getStatsByPatient() {
        $pid = $_GET['patient_id'] ?? $_GET['pid'] ?? null;
        if (!$pid) { $this->response("error", "Falta patient_id."); }
        $this->response("success", "Estadísticas.", $this->model->getStatsByPatient($pid));
    }

    public function cancelByPatient() {
        $d      = $this->input();
        // Acepta 'appointment_id' o 'appoid'
        $appoid = $d['appointment_id'] ?? $d['appoid'] ?? null;
        if (!$appoid) { $this->response("error", "Falta appointment_id."); }
        $res = $this->model->cancelByPatient($appoid);
        $this->response($res['status'], $res['message']);
    }

    public function create() {
        $d   = $this->input();
        $res = $this->model->create($d);
        $this->response($res['status'], $res['message']);
    }

    // ── Perfil ────────────────────────────────────────────────────────────────

    public function updateProfile() {
        $d   = $this->input();
        $pid = $d['pid'] ?? $d['id'] ?? $d['patient_id'] ?? null;
        if (!$pid) { $this->response("error", "Falta pid."); }

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("SELECT pemail FROM patient WHERE pid = ?");
            $stmt->execute([$pid]);
            $patient = $stmt->fetch();
            if (!$patient) {
                $this->conn->rollBack();
                $this->response("error", "Paciente no encontrado.");
            }

            $oldEmail = $patient['pemail'];
            $newEmail = !empty($d['pemail']) ? trim($d['pemail']) : $oldEmail;

            $this->conn->prepare(
                "UPDATE patient SET pname=?, pemail=?, pphone=?, paddress=? WHERE pid=?"
            )->execute([
                trim($d['pname']    ?? ''),
                $newEmail,
                trim($d['pphone']   ?? ''),
                trim($d['paddress'] ?? ''),
                $pid,
            ]);

            if ($oldEmail !== $newEmail) {
                $this->conn->prepare(
                    "UPDATE webuser SET email = ? WHERE email = ?"
                )->execute([$newEmail, $oldEmail]);
            }

            // Cambio de contraseña opcional
            if (!empty($d['new_password'])) {
                $wu = $this->conn->prepare("SELECT password FROM webuser WHERE email = ?");
                $wu->execute([$newEmail]);
                $webuser = $wu->fetch();
                if (!$webuser) {
                    $wu2 = $this->conn->prepare("SELECT password FROM webuser WHERE email = ?");
                    $wu2->execute([$oldEmail]);
                    $webuser = $wu2->fetch();
                }
                if (!$webuser || !password_verify($d['current_password'] ?? '', $webuser['password'])) {
                    $this->conn->rollBack();
                    $this->response("error", "La contraseña actual es incorrecta.");
                }
                $newHash = password_hash($d['new_password'], PASSWORD_DEFAULT);
                $this->conn->prepare("UPDATE patient SET ppassword = ? WHERE pid = ?")->execute([$newHash, $pid]);
                $this->conn->prepare("UPDATE webuser SET password = ? WHERE email = ?")->execute([$newHash, $newEmail]);
            }

            $this->conn->commit();
            $this->response("success", "Datos actualizados correctamente.");
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            $this->response("error", $e->getMessage());
        }
    }

    public function deleteAccount() {
        $d   = $this->input();
        $pid = $d['pid'] ?? $d['id'] ?? null;
        if (!$pid) { $this->response("error", "Falta pid."); }

        try {
            $this->conn->beginTransaction();
            $em = $this->conn->prepare("SELECT pemail FROM patient WHERE pid = ?");
            $em->execute([$pid]);
            $email = $em->fetchColumn();

            $this->conn->prepare("DELETE FROM appointment WHERE pid = ?")->execute([$pid]);
            if ($email) {
                $this->conn->prepare("DELETE FROM webuser WHERE email = ?")->execute([$email]);
            }
            $this->conn->prepare("DELETE FROM patient WHERE pid = ?")->execute([$pid]);
            $this->conn->commit();
            $this->response("success", "Cuenta eliminada correctamente.");
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            $this->response("error", $e->getMessage());
        }
    }
}
