<?php
require_once __DIR__ . '/../models/AppointmentModel.php';

class AppointmentController extends BaseController {
    private $model;

    public function __construct() {
        parent::__construct();
        $this->model = new AppointmentModel($this->conn);
    }

    public function getByPatient() {
        $pid = $_GET['patient_id'] ?? null;
        if (!$pid) { $this->response("error", "Falta patient_id"); return; }
        $this->response("success", "Citas", $this->model->getByPatient($pid));
    }

    public function getByDoctor() {
        $docid = $_GET['doctor_id'] ?? null;
        if (!$docid) { $this->response("error", "Falta doctor_id"); return; }
        $this->response("success", "Citas", $this->model->getByDoctor($docid));
    }

    public function create() {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $result = $this->model->create($data);
        $status = $result ? "success" : "error";
        $this->response($status, $result ? "Cita creada" : "Error al crear cita");
    }

    public function cancel() {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = $data['appointment_id'] ?? null;
        if (!$id) { $this->response("error", "Falta appointment_id"); return; }
        $result = $this->model->cancel($id);
        $this->response($result ? "success" : "error", $result ? "Cita cancelada" : "Error");
    }
    
}