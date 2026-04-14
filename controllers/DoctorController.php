<?php
require_once __DIR__ . '/../models/AppointmentModel.php';
require_once __DIR__ . '/../models/DoctorModel.php';
require_once __DIR__ . '/../config/BaseController.php';

class DoctorController extends BaseController {
    private $appointmentModel;
    private $doctorModel;

    public function __construct() {
        parent::__construct();
        $this->appointmentModel = new AppointmentModel($this->conn);
        $this->doctorModel      = new DoctorModel($this->conn);
    }

    // ── Citas ─────────────────────────────────────────────────────────────────

    public function getAppointments() {
        $docid = $_GET['doctor_id'] ?? null;
        if (!$docid) { $this->response("error", "Falta doctor_id."); }
        $this->response("success", "Citas.", $this->appointmentModel->getByDoctor($docid));
    }

    public function getTodayAppointments() {
        $docid = $_GET['doctor_id'] ?? null;
        if (!$docid) { $this->response("error", "Falta doctor_id."); }
        $this->response("success", "Citas hoy.", $this->doctorModel->getTodayAppointments($docid));
    }

    public function getWeeklyAgenda() {
        $docid = $_GET['doctor_id'] ?? null;
        if (!$docid) { $this->response("error", "Falta doctor_id."); }
        $this->response("success", "Agenda semanal.", $this->doctorModel->getWeeklyAgenda($docid));
    }

    public function getStats() {
        $docid = $_GET['doctor_id'] ?? null;
        if (!$docid) { $this->response("error", "Falta doctor_id."); }
        $this->response("success", "Estadísticas.", $this->doctorModel->getDoctorStats($docid));
    }

    public function getSchedules() {
        $docid = $_GET['doctor_id'] ?? null;
        if (!$docid) { $this->response("error", "Falta doctor_id."); }
        $this->response("success", "Horarios.", $this->doctorModel->getDoctorSchedules($docid));
    }

    public function getProfile() {
        $docid = $_GET['doctor_id'] ?? $_GET['docid'] ?? null;
        if (!$docid) { $this->response("error", "Falta doctor_id."); }

        $stmt = $this->conn->prepare(
            "SELECT d.docid AS id, d.dname AS name, d.demail AS email, d.dphone AS phone,
                    s.name AS specialty
             FROM doctor d
             LEFT JOIN specialties s ON s.id = d.specialty_id
             WHERE d.docid = ?"
        );
        $stmt->execute([$docid]);
        $doc = $stmt->fetch();
        if (!$doc) { $this->response("error", "Doctor no encontrado."); }
        $this->response("success", "Perfil.", $doc);
    }

    // ── Actualizar estado de cita ─────────────────────────────────────────────

    public function updateAppointmentStatus() {
        $d      = $this->input();
        $appoid = $d['appoid'] ?? $d['appointment_id'] ?? null;
        $status = $d['status'] ?? null;
        $docid  = $d['doctor_id'] ?? null;

        if (!$appoid || !$status) { $this->response("error", "Faltan parámetros."); }

        $allowed = ['completada', 'cancelada', 'reservada'];
        if (!in_array($status, $allowed)) { $this->response("error", "Estado no válido."); }

        try {
            // Verificar que la cita pertenezca al doctor
            if ($docid) {
                $ck = $this->conn->prepare(
                    "SELECT COUNT(*) FROM appointment a
                     JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
                     WHERE a.appoid = ? AND ds.docid = ?"
                );
                $ck->execute([$appoid, $docid]);
                if ($ck->fetchColumn() == 0) {
                    $this->response("error", "No tienes permiso para modificar esta cita.");
                }
            }

            $this->conn->prepare(
                "UPDATE appointment SET status = ? WHERE appoid = ?"
            )->execute([$status, $appoid]);
            $this->response("success", "Estado actualizado.");
        } catch (Exception $e) {
            $this->response("error", $e->getMessage());
        }
    }

    // ── Perfil ────────────────────────────────────────────────────────────────

    public function updateProfile() {
        $d     = $this->input();
        $docid = $d['docid'] ?? $d['id'] ?? null;
        if (!$docid) { $this->response("error", "Falta docid."); }
        $res = $this->doctorModel->updateProfile($this->conn, $docid, $d);
        $this->response($res['status'], $res['message']);
    }

    // ── Solicitud de cambio de horario ────────────────────────────────────────

    public function requestScheduleChange() {
        $d       = $this->input();
        $docid   = $d['doctor_id'] ?? null;
        $message = $d['message']   ?? '';
        if (!$docid || !$message) { $this->response("error", "Faltan parámetros."); }

        try {
            // Crear tabla si no existe
            $this->conn->exec(
                "CREATE TABLE IF NOT EXISTS schedule_requests (
                    id         INT AUTO_INCREMENT PRIMARY KEY,
                    docid      INT         NOT NULL,
                    message    TEXT        NOT NULL,
                    status     VARCHAR(20) DEFAULT 'pendiente',
                    created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
                )"
            );
            $this->conn->prepare(
                "INSERT INTO schedule_requests (docid, message) VALUES (?, ?)"
            )->execute([$docid, $message]);
            $this->response("success", "Solicitud enviada al administrador.");
        } catch (Exception $e) {
            $this->response("error", $e->getMessage());
        }
    }
}
