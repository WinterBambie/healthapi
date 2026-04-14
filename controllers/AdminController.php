<?php
require_once __DIR__ . '/../models/AdminModel.php';
require_once __DIR__ . '/../config/BaseController.php';

class AdminController extends BaseController {
    private $model;

    public function __construct() {
        parent::__construct();
        $this->model = new AdminModel($this->conn);
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    public function getStats() {
        $this->response("success", "Estadísticas.", [
            "total_patients"        => $this->model->getTotalPatients(),
            "total_doctors"         => $this->model->getTotalDoctors(),
            "appointments_today"    => $this->model->getAppointmentsToday(),
            "appointments_month"    => $this->model->getAppointmentsThisMonth(),
            "appointments_reserved" => $this->model->getAppointmentsReserved(),
        ]);
    }

    // ── Pacientes ─────────────────────────────────────────────────────────────

    public function getPatients() {
        $this->response("success", "Pacientes.", $this->model->getAllPatients());
    }

    public function deletePatient() {
        $d   = $this->input();
        $pid = $d['pid'] ?? null;
        if (!$pid) { $this->response("error", "Falta pid."); }
        $res = $this->model->deletePatient($pid);
        $this->response($res['status'], $res['message']);
    }

    // ── Doctores ──────────────────────────────────────────────────────────────

    public function getDoctors() {
        $this->response("success", "Doctores.", $this->model->getAllDoctors());
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
    ob_start(); // 👈 captura cualquier output basura antes del JSON
    $d     = $this->input();
    $docid = $d['docid'] ?? null;
    if (!$docid) { $this->response("error", "Falta docid."); }
    $res = $this->model->updateDoctor($docid, $d);
    ob_end_clean(); // 👈 limpia el basura
    $this->response($res['status'], $res['message']);
}

    public function deleteDoctor() {
        $d     = $this->input();
        $docid = $d['docid'] ?? null;
        if (!$docid) { $this->response("error", "Falta docid."); }
        $res = $this->model->deleteDoctor($docid);
        $this->response($res['status'], $res['message']);
    }

    // ── Citas ─────────────────────────────────────────────────────────────────

    public function getAppointments() {
        $this->response("success", "Citas.", $this->model->getAllAppointments());
    }

    public function updateAppointmentStatus() {
        $d      = $this->input();
        $appoid = $d['appoid'] ?? null;
        $status = $d['status'] ?? null;
        if (!$appoid || !$status) { $this->response("error", "Faltan parámetros."); }
        $res = $this->model->updateAppointmentStatus($appoid, $status);
        $this->response($res['status'], $res['message']);
    }

    public function deleteAppointment() {
        $d      = $this->input();
        $appoid = $d['appoid'] ?? null;
        if (!$appoid) { $this->response("error", "Falta appoid."); }
        $res = $this->model->deleteAppointment($appoid);
        $this->response($res['status'], $res['message']);
    }

    // ── Horarios ──────────────────────────────────────────────────────────────

    public function getSchedules() {
        $this->response("success", "Horarios.", $this->model->getAllSchedules());
    }

    public function getAvailableSlots() {
        $scheduleid = $_GET['scheduleid'] ?? null;
        $date       = $_GET['date']       ?? null;
        if (!$scheduleid || !$date) { $this->response("error", "Faltan parámetros."); }
        $slots = $this->model->getAvailableSlots($scheduleid, $date);
        $this->response("success", "Slots disponibles.", $slots);
    }

    public function createSchedule() {
        $res = $this->model->createSchedule(
            $_POST['docid']                ?? null,
            $_POST['sessionid']            ?? null,
            $_POST['day_of_week']          ?? 0,
            $_POST['start_time']           ?? '08:00',
            $_POST['end_time']             ?? '17:00',
            $_POST['slot_duration_min']    ?? 30,
            $_POST['max_patients_per_day'] ?? 10
        );
        $this->response($res['status'], $res['message']);
    }

    public function updateSchedule() {
        $d          = $this->input();
        $scheduleid = $d['scheduleid'] ?? null;
        if (!$scheduleid) { $this->response("error", "Falta scheduleid."); }
        $res = $this->model->updateSchedule($scheduleid, $d);
        $this->response($res['status'], $res['message']);
    }

    public function deleteSchedule() {
        $d          = $this->input();
        $scheduleid = $d['scheduleid'] ?? null;
        if (!$scheduleid) { $this->response("error", "Falta scheduleid."); }
        $res = $this->model->deleteSchedule($scheduleid);
        $this->response($res['status'], $res['message']);
    }

    // ── Auxiliares ────────────────────────────────────────────────────────────

    public function getSessions() {
        $this->response("success", "Sesiones.", $this->model->getAllSessions());
    }

    public function getSpecialties() {
        $this->response("success", "Especialidades.", $this->model->getSpecialties());
    }
}
