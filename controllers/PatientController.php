<?php
require_once __DIR__ . '/../models/AppointmentModel.php';
require_once __DIR__ . '/../config/BaseController.php';
require_once __DIR__ . '/../config/Mailer.php';


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
    public function create() {
    $data = !empty($_POST) ? $_POST : (json_decode(file_get_contents('php://input'), true) ?? []);
    $res  = $this->model->create($data);
 
    if ($res['status'] === 'success') {
        // ✅ Enviar email de confirmación al paciente
        try {
            $pid = $data['pid'] ?? null;
            if ($pid) {
                $pStmt = $this->conn->prepare(
                    "SELECT p.pname, p.pemail, d.dname AS doctor,
                            s.title AS session,
                            a.appointment_date, a.appointment_time
                     FROM appointment a
                     JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
                     JOIN doctor d ON d.docid = ds.docid
                     LEFT JOIN session s ON s.sessionid = ds.sessionid
                     JOIN patient p ON p.pid = a.pid
                     WHERE a.pid = ? ORDER BY a.appoid DESC LIMIT 1"
                );
                $pStmt->execute([$pid]);
                $appt = $pStmt->fetch(PDO::FETCH_ASSOC);
 
                if ($appt) {
                    Mailer::appointmentCreated($appt['pemail'], $appt['pname'], [
                        'doctor'  => $appt['doctor'],
                        'session' => $appt['session'] ?? '—',
                        'date'    => $appt['appointment_date'],
                        'time'    => $appt['appointment_time'],
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            // No fallar la cita si el email falla
        }
    }
 
    $this->response($res['status'], $res['message']);
}
 
// 3. Reemplaza cancelByPatient():
public function cancelByPatient() {
    $data   = !empty($_POST) ? $_POST : (json_decode(file_get_contents('php://input'), true) ?? []);
    $appoid = $data['appointment_id'] ?? $data['appoid'] ?? null;
    if (!$appoid) { $this->response("error", "Falta appointment_id"); return; }
 
    // Obtener datos antes de cancelar para el email
    $infoStmt = $this->conn->prepare(
        "SELECT p.pname, p.pemail, d.dname AS doctor,
                a.appointment_date, a.appointment_time
         FROM appointment a
         JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
         JOIN doctor d ON d.docid = ds.docid
         JOIN patient p ON p.pid = a.pid
         WHERE a.appoid = ?"
    );
    $infoStmt->execute([$appoid]);
    $apptInfo = $infoStmt->fetch(PDO::FETCH_ASSOC);
 
    $res = $this->model->cancelByPatient($appoid);
 
    if ($res['status'] === 'success' && $apptInfo) {
        // ✅ Enviar email de cancelación
        try {
            Mailer::appointmentCancelled($apptInfo['pemail'], $apptInfo['pname'], [
                'doctor' => $apptInfo['doctor'],
                'date'   => $apptInfo['appointment_date'],
                'time'   => $apptInfo['appointment_time'],
            ]);
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
        }
    }
 
    $this->response($res['status'], $res['message']);
}
}
