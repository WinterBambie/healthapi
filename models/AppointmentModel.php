<?php

class AppointmentModel {

    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ── Crear cita ────────────────────────────────────────────────────────────

    public function create($data) {
        $pid        = $data['pid']              ?? null;
        $scheduleid = $data['scheduleid']       ?? null;
        $date       = $data['appointment_date'] ?? null;
        $time       = $data['appointment_time'] ?? null;

        if (!$pid || !$scheduleid || !$date || !$time) {
            return ["status" => "error", "message" => "Faltan campos obligatorios."];
        }

        // No permitir fechas pasadas
        if ($date < date('Y-m-d')) {
            return ["status" => "error", "message" => "No se pueden agendar citas en fechas pasadas."];
        }

        // Verificar que no exista otra cita en el mismo horario
        $check = $this->conn->prepare(
            "SELECT COUNT(*) 
             FROM appointment 
             WHERE scheduleid = ? 
             AND appointment_date = ? 
             AND appointment_time = ?
             AND status != 'cancelada'"
        );
        $check->execute([$scheduleid, $date, $time]);

        if ($check->fetchColumn() > 0) {
            return ["status" => "error", "message" => "Ese horario ya está ocupado."];
        }

        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO appointment 
                (pid, scheduleid, appointment_date, appointment_time, status)
                VALUES (?, ?, ?, ?, 'reservada')"
            );

            $stmt->execute([$pid, $scheduleid, $date, $time]);

            return ["status" => "success", "message" => "Cita creada correctamente."];

        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // ── Consultas ─────────────────────────────────────────────────────────────

    public function getByPatient($pid) {
        $stmt = $this->conn->prepare(
            "SELECT a.appoid, d.dname AS doctor,
                    a.appointment_date,
                    a.appointment_time,
                    a.status
             FROM appointment a
             JOIN doctor_schedule s ON a.scheduleid = s.scheduleid
             JOIN doctor d ON s.docid = d.docid
             WHERE a.pid = ?
             ORDER BY a.appointment_date DESC, a.appointment_time DESC"
        );
        $stmt->execute([$pid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByDoctor($docid) {
        $stmt = $this->conn->prepare(
            "SELECT a.appoid, p.pname, p.pphone,
                    a.appointment_date,
                    a.appointment_time,
                    a.status
             FROM appointment a
             JOIN patient p ON a.pid = p.pid
             JOIN doctor_schedule s ON a.scheduleid = s.scheduleid
             WHERE s.docid = ?
             ORDER BY a.appointment_date DESC, a.appointment_time DESC"
        );
        $stmt->execute([$docid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Actualizar estado ─────────────────────────────────────────────────────

    public function updateStatus($appoid, $status) {
        $allowed = ['reservada', 'completada', 'cancelada'];
        if (!in_array($status, $allowed)) {
            return ["status" => "error", "message" => "Estado no válido."];
        }

        try {
            $stmt = $this->conn->prepare(
                "UPDATE appointment SET status = ? WHERE appoid = ?"
            );
            $stmt->execute([$status, $appoid]);
            return ["status" => "success", "message" => "Estado actualizado."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // ── Cancelar ──────────────────────────────────────────────────────────────

    public function cancel($id) {
        $stmt = $this->conn->prepare(
            "UPDATE appointment SET status = 'cancelada' WHERE appoid = ?"
        );
        return $stmt->execute([$id]);
    }

    // ── Eliminar ──────────────────────────────────────────────────────────────

    public function delete($id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM appointment WHERE appoid = ?");
            $stmt->execute([$id]);
            return ["status" => "success", "message" => "Cita eliminada."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }
}