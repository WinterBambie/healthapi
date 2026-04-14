<?php
require_once __DIR__ . '/../models/AdminModel.php';

class AdminController extends BaseController {
    private $model;

    public function __construct() {
        parent::__construct();
        $this->model = new AdminModel($this->conn);
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    public function getStats() {
        $this->response("success", "Estadísticas", [
            "total_patients"        => $this->model->getTotalPatients(),
            "total_doctors"         => $this->model->getTotalDoctors(),
            "appointments_today"    => $this->model->getAppointmentsToday(),
            "appointments_month"    => $this->model->getAppointmentsThisMonth(),
            "appointments_reserved" => $this->model->getAppointmentsReserved(),
        ]);
    }

    // ── Pacientes ─────────────────────────────────────────────────────────────

    public function getPatients() {
        $this->response("success", "Pacientes", $this->model->getAllPatients());
    }

    public function updatePatient() {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $pid  = $data['pid'] ?? null;
        if (!$pid) { $this->response("error", "Falta pid"); return; }
        $res = $this->model->updatePatient($pid, $data);
        $this->response($res['status'], $res['message']);
    }

    public function deletePatient() {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $pid  = $data['pid'] ?? null;
        if (!$pid) { $this->response("error", "Falta pid"); return; }
        $res = $this->model->deletePatient($pid);
        $this->response($res['status'], $res['message']);
    }

    // ── Doctores ──────────────────────────────────────────────────────────────

    public function getDoctors() {
        $this->response("success", "Doctores", $this->model->getAllDoctors());
    }

    public function createDoctor() {
        $res = $this->model->createDoctor(
            $_POST['dname']             ?? '',
            $_POST['demail']            ?? '',
            $_POST['dpassword']         ?? '',
            $_POST['dphone']            ?? '',
            $_POST['ddocument']         ?? '',
            $_POST['tipo_documento_id'] ?? null,
            $_POST['specialty_name']    ?? ''
        );
        $this->response($res['status'], $res['message']);
    }

    public function updateDoctor() {
        $data   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $docid  = $data['docid'] ?? null;
        if (!$docid) { $this->response("error", "Falta docid"); return; }
        $res = $this->model->updateDoctor($docid, $data);
        $this->response($res['status'], $res['message']);
    }

    public function deleteDoctor() {
        $data  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $docid = $data['docid'] ?? null;
        if (!$docid) { $this->response("error", "Falta docid"); return; }
        $res = $this->model->deleteDoctor($docid);
        $this->response($res['status'], $res['message']);
    }

    // ── Citas ─────────────────────────────────────────────────────────────────

    public function getAppointments() {
        $this->response("success", "Citas", $this->model->getAllAppointments());
    }

    public function updateAppointmentStatus() {
        $data   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $appoid = $data['appoid'] ?? null;
        $status = $data['status'] ?? null;
        if (!$appoid || !$status) { $this->response("error", "Faltan parámetros"); return; }
        $res = $this->model->updateAppointmentStatus($appoid, $status);
        $this->response($res['status'], $res['message']);
    }

    public function deleteAppointment() {
        $data   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $appoid = $data['appoid'] ?? null;
        if (!$appoid) { $this->response("error", "Falta appoid"); return; }
        $res = $this->model->deleteAppointment($appoid);
        $this->response($res['status'], $res['message']);
    }

    // ── Horarios ──────────────────────────────────────────────────────────────

    public function getSchedules() {
        $this->response("success", "Horarios", $this->model->getAllSchedules());
    }

    public function getAvailableSlots() {
        $scheduleid = $_GET['scheduleid'] ?? null;
        $date       = $_GET['date']       ?? null;
        if (!$scheduleid || !$date) { $this->response("error", "Faltan parámetros"); return; }
        $slots = $this->model->getAvailableSlots($scheduleid, $date);
        $this->response("success", "Slots disponibles", $slots);
    }

    public function createSchedule() {
        $res = $this->model->createSchedule(
            $_POST['docid']              ?? null,
            $_POST['sessionid']          ?? null,
            $_POST['day_of_week']        ?? 0,
            $_POST['start_time']         ?? '08:00',
            $_POST['end_time']           ?? '12:00',
            $_POST['slot_duration_min']  ?? 30,
            $_POST['max_patients_per_day'] ?? 10
        );
        $this->response($res['status'], $res['message']);
    }

    public function updateSchedule() {
        $data       = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $scheduleid = $data['scheduleid'] ?? null;
        if (!$scheduleid) { $this->response("error", "Falta scheduleid"); return; }
        $res = $this->model->updateSchedule($scheduleid, $data);
        $this->response($res['status'], $res['message']);
    }

    public function deleteSchedule() {
        $data       = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $scheduleid = $data['scheduleid'] ?? null;
        if (!$scheduleid) { $this->response("error", "Falta scheduleid"); return; }
        $res = $this->model->deleteSchedule($scheduleid);
        $this->response($res['status'], $res['message']);
    }

    // ── Auxiliares ────────────────────────────────────────────────────────────

    public function getSessions() {
        $this->response("success", "Sesiones", $this->model->getAllSessions());
    }

    public function getSpecialties() {
        $this->response("success", "Especialidades", $this->model->getSpecialties());
    }
}