<?php
class AppointmentModel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
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
        if ($date < date('Y-m-d')) {
            return ["status" => "error", "message" => "No se pueden agendar citas en fechas pasadas."];
        }

        // Slot ocupado por otro paciente
        $s1 = $this->conn->prepare(
            "SELECT COUNT(*) FROM appointment
             WHERE scheduleid = ? AND appointment_date = ?
               AND appointment_time = ? AND status != 'cancelada'"
        );
        $s1->execute([$scheduleid, $date, $time]);
        if ($s1->fetchColumn() > 0) {
            return ["status" => "error", "message" => "Ese horario ya está ocupado."];
        }

        // Mismo paciente ya tiene cita a esa hora
        $s2 = $this->conn->prepare(
            "SELECT COUNT(*) FROM appointment
             WHERE pid = ? AND appointment_date = ?
               AND appointment_time = ? AND status != 'cancelada'"
        );
        $s2->execute([$pid, $date, $time]);
        if ($s2->fetchColumn() > 0) {
            return ["status" => "error", "message" => "Ya tienes una cita agendada a esa hora."];
        }

        // Mismo paciente ya tiene cita ese día con ese doctor
        $s3 = $this->conn->prepare(
            "SELECT COUNT(*) FROM appointment a
             JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
             WHERE a.pid = ? AND a.appointment_date = ?
               AND ds.docid = (SELECT docid FROM doctor_schedule WHERE scheduleid = ?)
               AND a.status != 'cancelada'"
        );
        $s3->execute([$pid, $date, $scheduleid]);
        if ($s3->fetchColumn() > 0) {
            return ["status" => "error", "message" => "Ya tienes una cita con este doctor ese día."];
        }

        try {
            $this->conn->prepare(
                "INSERT INTO appointment (pid, scheduleid, appointment_date, appointment_time, status)
                 VALUES (?, ?, ?, ?, 'reservada')"
            )->execute([$pid, $scheduleid, $date, $time]);
            return ["status" => "success", "message" => "Cita creada correctamente."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // ── Por paciente ──────────────────────────────────────────────────────────

    public function getByPatient($pid) {
        $stmt = $this->conn->prepare(
            "SELECT a.appoid, d.dname AS doctor,
                    s.title AS session,
                    a.appointment_date, a.appointment_time,
                    a.duration_min, a.status
             FROM appointment a
             JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
             JOIN doctor d ON d.docid = ds.docid
             LEFT JOIN session s ON s.sessionid = ds.sessionid
             WHERE a.pid = ?
             ORDER BY a.appointment_date DESC, a.appointment_time DESC"
        );
        $stmt->execute([$pid]);
        return $stmt->fetchAll();
    }

    public function getStatsByPatient($pid) {
        $run = function($sql, $params) {
            $s = $this->conn->prepare($sql);
            $s->execute($params);
            return (int)$s->fetchColumn();
        };
        return [
            "total"     => $run("SELECT COUNT(*) FROM appointment WHERE pid = ?", [$pid]),
            "completed" => $run("SELECT COUNT(*) FROM appointment WHERE pid = ? AND status = 'completada'", [$pid]),
            "cancelled" => $run("SELECT COUNT(*) FROM appointment WHERE pid = ? AND status = 'cancelada'", [$pid]),
            "pending"   => $run("SELECT COUNT(*) FROM appointment WHERE pid = ? AND status = 'reservada'", [$pid]),
        ];
    }

    public function cancelByPatient($appoid) {
        $stmt = $this->conn->prepare(
            "SELECT appointment_date, appointment_time FROM appointment WHERE appoid = ?"
        );
        $stmt->execute([$appoid]);
        $appt = $stmt->fetch();
        if (!$appt) return ["status" => "error", "message" => "Cita no encontrada."];

        $apptTime  = new DateTime($appt['appointment_date'] . ' ' . ($appt['appointment_time'] ?? '00:00:00'));
        $diffHours = ($apptTime->getTimestamp() - time()) / 3600;
        if ($diffHours <= 12) {
            return ["status" => "error", "message" => "No puedes cancelar con menos de 12 horas de antelación."];
        }

        try {
            $this->conn->prepare(
                "UPDATE appointment SET status = 'cancelada' WHERE appoid = ?"
            )->execute([$appoid]);
            return ["status" => "success", "message" => "Cita cancelada correctamente."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // ── Por doctor ────────────────────────────────────────────────────────────

    public function getByDoctor($docid) {
        $stmt = $this->conn->prepare(
            "SELECT a.appoid, p.pname AS patient, p.pphone AS patient_phone,
                    s.title AS session,
                    a.appointment_date, a.appointment_time,
                    a.duration_min, a.status
             FROM appointment a
             JOIN patient p ON p.pid = a.pid
             JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
             LEFT JOIN session s ON s.sessionid = ds.sessionid
             WHERE ds.docid = ?
             ORDER BY a.appointment_date DESC, a.appointment_time DESC"
        );
        $stmt->execute([$docid]);
        return $stmt->fetchAll();
    }

    public function updateStatus($appoid, $status) {
        $allowed = ['reservada', 'completada', 'cancelada'];
        if (!in_array($status, $allowed)) {
            return ["status" => "error", "message" => "Estado no válido."];
        }
        try {
            $this->conn->prepare(
                "UPDATE appointment SET status = ? WHERE appoid = ?"
            )->execute([$status, $appoid]);
            return ["status" => "success", "message" => "Estado actualizado."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }
}
