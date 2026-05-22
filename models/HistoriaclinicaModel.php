<?php
class HistoriaClinicaModel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // ── Abrir historia clínica ────────────────────────────────────────────────
    public function abrirHistoria($pid, $docid) {
        $check = $this->conn->prepare("SELECT hc_id FROM historia_clinica WHERE pid = ?");
        $check->execute([$pid]);
        if ($row = $check->fetch(PDO::FETCH_ASSOC)) {
            return ["status" => "exists", "hc_id" => $row['hc_id']];
        }
        $stmt = $this->conn->prepare(
            "INSERT INTO historia_clinica (pid, fecha_apertura, created_by) VALUES (?, CURDATE(), ?)"
        );
        $stmt->execute([$pid, $docid]);
        return ["status" => "created", "hc_id" => $this->conn->lastInsertId()];
    }

    // ── Obtener historia clínica completa ─────────────────────────────────────
    public function getByPaciente($pid, $docid) {
        // Verificar acceso: el doctor debe haber atendido al paciente
        $auth = $this->conn->prepare(
            "SELECT COUNT(*) FROM appointment a
             JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
             WHERE a.pid = ? AND ds.docid = ?"
        );
        $auth->execute([$pid, $docid]);
        if ($auth->fetchColumn() == 0) {
            return ["status" => "error", "message" => "No tienes acceso a esta historia clínica."];
        }

        $hcStmt = $this->conn->prepare(
            "SELECT hc.hc_id, hc.fecha_apertura, hc.activa,
                    p.pname AS paciente, p.pemail, p.pphone,
                    p.pbirthdate, p.paddress, p.pdocument,
                    td.nombre AS tipo_documento,
                    d.dname AS abierta_por
             FROM historia_clinica hc
             JOIN patient p ON p.pid = hc.pid
             LEFT JOIN tipo_documento td ON td.id = p.tipo_documento_id
             JOIN doctor d ON d.docid = hc.created_by
             WHERE hc.pid = ?"
        );
        $hcStmt->execute([$pid]);
        $hc = $hcStmt->fetch(PDO::FETCH_ASSOC);
        if (!$hc) return ["status" => "not_found", "message" => "Sin historia clínica."];

        $regStmt = $this->conn->prepare(
            "SELECT r.*,
                    d.dname AS doctor_nombre,
                    COALESCE(d2.dname, '') AS anulado_por_nombre
             FROM registro_clinico r
             JOIN doctor d ON d.docid = r.docid
             LEFT JOIN doctor d2 ON d2.docid = r.anulado_por
             WHERE r.hc_id = ?
             ORDER BY r.fecha_registro DESC, r.hora_registro DESC"
        );
        $regStmt->execute([$hc['hc_id']]);
        $registros = $regStmt->fetchAll(PDO::FETCH_ASSOC);

        // ✅ signos_vitales ya es JSON string → el frontend lo parsea
        // No modificar aquí para que llegue limpio

        $this->logAcceso($hc['hc_id'], $docid, 'ver');

        return ["status" => "success", "historia" => $hc, "registros" => $registros];
    }

    // ── Crear registro clínico ────────────────────────────────────────────────
    public function crearRegistro(array $data, int $docid) {
        $hc_id = (int)($data['hc_id'] ?? 0);

        // Verificar permiso
        $check = $this->conn->prepare(
            "SELECT hc.hc_id FROM historia_clinica hc
             JOIN appointment a ON a.pid = hc.pid
             JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
             WHERE hc.hc_id = ? AND ds.docid = ? LIMIT 1"
        );
        $check->execute([$hc_id, $docid]);
        if (!$check->fetch()) {
            return ["status" => "error", "message" => "Sin permiso para registrar en esta HC."];
        }

        // ✅ signos_vitales llega como objeto JSON → codificar para MySQL
        $sv = json_encode([
            "presion_arterial"        => $data['presion_arterial']        ?? null,
            "frecuencia_cardiaca"     => $data['frecuencia_cardiaca']     ?? null,
            "frecuencia_respiratoria" => $data['frecuencia_respiratoria'] ?? null,
            "temperatura"             => $data['temperatura']             ?? null,
            "peso_kg"                 => $data['peso_kg']                 ?? null,
            "talla_cm"                => $data['talla_cm']                ?? null,
            "saturacion_o2"           => $data['saturacion_o2']           ?? null,
        ]);

        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO registro_clinico
                 (hc_id, appoid, docid, fecha_registro, hora_registro,
                  motivo_consulta, signos_vitales, anamnesis, examen_fisico,
                  diagnostico, cie10_codigo, plan_manejo, evolucion, observaciones)
                 VALUES (?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $hc_id,
                $data['appoid']           ?? null,
                $docid,
                $data['motivo_consulta']  ?? '',
                $sv,
                $data['anamnesis']        ?? '',
                $data['examen_fisico']    ?? '',
                $data['diagnostico']      ?? '',
                $data['cie10_codigo']     ?? null,
                $data['plan_manejo']      ?? '',
                $data['evolucion']        ?? '',
                $data['observaciones']    ?? '',
            ]);

            $this->logAcceso($hc_id, $docid, 'crear');
            return ["status" => "success", "message" => "Registro creado.",
                    "registro_id" => (int)$this->conn->lastInsertId()];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // ── Anular registro ───────────────────────────────────────────────────────
    public function anularRegistro(int $registro_id, int $docid, string $motivo) {
        $check = $this->conn->prepare(
            "SELECT r.registro_id FROM registro_clinico r
             JOIN historia_clinica hc ON hc.hc_id = r.hc_id
             JOIN appointment a ON a.pid = hc.pid
             JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
             WHERE r.registro_id = ? AND ds.docid = ? AND r.anulado = 0"
        );
        $check->execute([$registro_id, $docid]);
        if (!$check->fetch()) {
            return ["status" => "error", "message" => "No puedes anular este registro."];
        }

        try {
            $this->conn->prepare(
                "UPDATE registro_clinico
                 SET anulado = 1, anulado_por = ?, anulado_at = NOW(), motivo_anulacion = ?
                 WHERE registro_id = ?"
            )->execute([$docid, $motivo, $registro_id]);
            return ["status" => "success", "message" => "Registro anulado."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // ── Pacientes del doctor ──────────────────────────────────────────────────
    public function getPacientesDelDoctor(int $docid) {
        $stmt = $this->conn->prepare(
            "SELECT DISTINCT p.pid, p.pname, p.pemail, p.pbirthdate,
                    hc.hc_id, hc.fecha_apertura,
                    (SELECT COUNT(*) FROM registro_clinico r
                     WHERE r.hc_id = hc.hc_id AND r.anulado = 0) AS total_registros,
                    (SELECT MAX(r2.fecha_registro) FROM registro_clinico r2
                     WHERE r2.hc_id = hc.hc_id) AS ultima_consulta
             FROM appointment a
             JOIN patient p ON p.pid = a.pid
             JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
             LEFT JOIN historia_clinica hc ON hc.pid = p.pid
             WHERE ds.docid = ?
             ORDER BY p.pname ASC"
        );
        $stmt->execute([$docid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function logAcceso(int $hc_id, int $docid, string $accion) {
        try {
            $this->conn->prepare(
                "INSERT INTO hc_acceso_log (hc_id, docid, accion, ip) VALUES (?, ?, ?, ?)"
            )->execute([$hc_id, $docid, $accion, $_SERVER['REMOTE_ADDR'] ?? null]);
        } catch (Exception $e) {
            error_log("HC log: " . $e->getMessage());
        }
    }
}