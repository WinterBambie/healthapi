<?php
require_once __DIR__ . '/../models/HistoriaClinicaModel.php';

class HistoriaClinicaController extends BaseController {
    private $model;

    public function __construct() {
        parent::__construct();
        $this->model = new HistoriaClinicaModel($this->conn);
    }

    // ✅ Siempre lee JSON del body
    private function json(): array {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
    // GET ?action=hcPacientes&doctor_id=X
    public function getPacientes() {
        $docid = (int)($_GET['doctor_id'] ?? 0);
        if (!$docid) { $this->response("error", "Falta doctor_id"); return; }
        $data = $this->model->getPacientesDelDoctor($docid);
        $this->response("success", "Pacientes", $data);
    }

    // GET ?action=hcGet&pid=X&doctor_id=X
    public function getHistoria() {
        $pid   = (int)($_GET['pid']       ?? 0);
        $docid = (int)($_GET['doctor_id'] ?? 0);
        if (!$pid || !$docid) { $this->response("error", "Faltan parámetros"); return; }

        $res = $this->model->getByPaciente($pid, $docid);

        if ($res['status'] === 'not_found') {
            $open = $this->model->abrirHistoria($pid, $docid);
            if (in_array($open['status'], ['created', 'exists'])) {
                $res = $this->model->getByPaciente($pid, $docid);
            }
        }

        if ($res['status'] === 'error') {
            $this->response("error", $res['message']); return;
        }

        $this->response("success", "Historia clínica", [
            "historia"  => $res['historia'],
            "registros" => $res['registros'],
        ]);
    }

    // POST ?action=hcCrearRegistro  — body: JSON
    public function crearRegistro() {
        $data  = $this->json();
        $docid = (int)($data['doctor_id'] ?? 0);

        if (!$docid)                    { $this->response("error", "Falta doctor_id");                        return; }
        if (empty($data['hc_id']))      { $this->response("error", "Falta hc_id");                           return; }
        if (empty($data['motivo_consulta'])) { $this->response("error", "Motivo de consulta es obligatorio"); return; }
        if (empty($data['diagnostico']))     { $this->response("error", "Diagnóstico es obligatorio");        return; }
        if (empty($data['plan_manejo']))     { $this->response("error", "Plan de manejo es obligatorio");     return; }

        $res = $this->model->crearRegistro($data, $docid);
        $this->response($res['status'], $res['message'],
            isset($res['registro_id']) ? ['registro_id' => $res['registro_id']] : null);
    }

    // POST ?action=hcAnularRegistro — body: JSON
    public function anularRegistro() {
        $data        = $this->json();
        $registro_id = (int)($data['registro_id'] ?? 0);
        $docid       = (int)($data['doctor_id']   ?? 0);
        $motivo      = trim($data['motivo']        ?? '');

        if (!$registro_id || !$docid) { $this->response("error", "Faltan parámetros"); return; }
        if (!$motivo)                  { $this->response("error", "Motivo de anulación requerido"); return; }

        $res = $this->model->anularRegistro($registro_id, $docid, $motivo);
        $this->response($res['status'], $res['message']);
    }
}