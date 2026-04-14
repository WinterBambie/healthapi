<?php
class AdminModel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    public function getTotalPatients() {
        return $this->conn->query("SELECT COUNT(*) FROM patient")->fetchColumn();
    }

    public function getTotalDoctors() {
        return $this->conn->query("SELECT COUNT(*) FROM doctor")->fetchColumn();
    }

    public function getAppointmentsToday() {
        return $this->conn->query(
            "SELECT COUNT(*) FROM appointment
             WHERE appointment_date = CURDATE() AND status != 'cancelada'"
        )->fetchColumn();
    }

    public function getAppointmentsThisMonth() {
        return $this->conn->query(
            "SELECT COUNT(*) FROM appointment
             WHERE MONTH(appointment_date) = MONTH(CURDATE())
             AND YEAR(appointment_date) = YEAR(CURDATE())
             AND status != 'cancelada'"
        )->fetchColumn();
    }

    public function getAppointmentsReserved() {
        return $this->conn->query(
            "SELECT COUNT(*) FROM appointment WHERE status = 'reservada'"
        )->fetchColumn();
    }

    // ── Pacientes ─────────────────────────────────────────────────────────────

    public function getAllPatients() {
        return $this->conn->query(
            "SELECT pid, pname, pemail, pphone, paddress, pbirthdate FROM patient ORDER BY pname"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updatePatient($pid, $data) {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE patient SET pname=?, pemail=?, pphone=?, paddress=? WHERE pid=?"
            );
            $stmt->execute([$data['pname'], $data['pemail'], $data['pphone'], $data['paddress'], $pid]);
            return ["status" => "success", "message" => "Paciente actualizado correctamente."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    public function deletePatient($pid) {
        try {
            $this->conn->prepare("DELETE FROM appointment WHERE pid=?")->execute([$pid]);
            $this->conn->prepare("DELETE FROM webuser WHERE email=(SELECT pemail FROM patient WHERE pid=?)")->execute([$pid]);
            $this->conn->prepare("DELETE FROM patient WHERE pid=?")->execute([$pid]);
            return ["status" => "success", "message" => "Paciente eliminado correctamente."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // ── Doctores ──────────────────────────────────────────────────────────────

    public function getAllDoctors() {
        return $this->conn->query(
            "SELECT d.docid, d.dname, d.demail, d.dphone, d.ddocument,
                    s.name AS specialty
             FROM doctor d
             LEFT JOIN specialties s ON s.id = d.specialty_id
             ORDER BY d.dname"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateDoctor($docid, $data) {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE doctor SET dname=?, demail=?, dphone=? WHERE docid=?"
            );
            $stmt->execute([$data['dname'], $data['demail'], $data['dphone'], $docid]);
            return ["status" => "success", "message" => "Doctor actualizado correctamente."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    public function deleteDoctor($docid) {
        try {
            $this->conn->beginTransaction();
            $email = $this->conn->prepare("SELECT demail FROM doctor WHERE docid=?");
            $email->execute([$docid]);
            $demail = $email->fetchColumn();

            $this->conn->prepare(
                "DELETE a FROM appointment a
                 JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
                 WHERE ds.docid=?"
            )->execute([$docid]);
            $this->conn->prepare("DELETE FROM doctor_schedule WHERE docid=?")->execute([$docid]);
            $this->conn->prepare("DELETE FROM webuser WHERE email=?")->execute([$demail]);
            $this->conn->prepare("DELETE FROM doctor WHERE docid=?")->execute([$docid]);

            $this->conn->commit();
            return ["status" => "success", "message" => "Doctor eliminado correctamente."];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    public function createDoctor($dname, $demail, $dpassword, $dphone, $ddocument, $tipo_documento_id, $specialty_name) {
        try {
            $this->conn->beginTransaction();
            $hash = password_hash($dpassword, PASSWORD_DEFAULT);

            $specStmt = $this->conn->prepare("SELECT id FROM specialties WHERE name=?");
            $specStmt->execute([$specialty_name]);
            $spec = $specStmt->fetch(PDO::FETCH_ASSOC);

            if ($spec) {
                $specialty_id = $spec['id'];
            } else {
                $ins = $this->conn->prepare("INSERT INTO specialties (name) VALUES (?)");
                $ins->execute([$specialty_name]);
                $specialty_id = $this->conn->lastInsertId();
            }

            $this->conn->prepare(
                "INSERT INTO doctor (dname, demail, dpassword, dphone, ddocument, tipo_documento_id, specialty_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([$dname, $demail, $hash, $dphone, $ddocument, $tipo_documento_id, $specialty_id]);

            $this->conn->prepare(
                "INSERT INTO webuser (email, password, usertype) VALUES (?, ?, 'd')"
            )->execute([$demail, $hash]);

            $this->conn->commit();
            return ["status" => "success", "message" => "Doctor registrado correctamente."];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // ── Citas ─────────────────────────────────────────────────────────────────

    public function getAllAppointments() {
        return $this->conn->query(
            "SELECT a.appoid, p.pname AS patient, d.dname AS doctor,
                    a.appointment_date, a.appointment_time, a.duration_min, a.status,
                    s.title AS session
             FROM appointment a
             JOIN patient p ON p.pid = a.pid
             JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
             JOIN doctor d ON d.docid = ds.docid
             LEFT JOIN session s ON s.sessionid = ds.sessionid
             ORDER BY a.appointment_date DESC, a.appointment_time ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateAppointmentStatus($appoid, $status) {
        $allowed = ['reservada', 'completada', 'cancelada'];
        if (!in_array($status, $allowed)) {
            return ["status" => "error", "message" => "Estado no válido."];
        }
        try {
            $this->conn->prepare(
                "UPDATE appointment SET status=? WHERE appoid=?"
            )->execute([$status, $appoid]);
            return ["status" => "success", "message" => "Estado actualizado."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    public function deleteAppointment($appoid) {
        try {
            $this->conn->prepare("DELETE FROM appointment WHERE appoid=?")->execute([$appoid]);
            return ["status" => "success", "message" => "Cita eliminada."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // ── Horarios (doctor_schedule) ────────────────────────────────────────────

    public function getAllSchedules() {
        return $this->conn->query(
            "SELECT ds.scheduleid, ds.docid, d.dname AS doctor,
                    ds.sessionid, s.title AS session,
                    ds.day_of_week, ds.start_time, ds.end_time,
                    ds.slot_duration_min, ds.max_patients_per_day, ds.is_active,
                    (SELECT COUNT(*) FROM appointment a
                     WHERE a.scheduleid = ds.scheduleid
                     AND a.appointment_date = CURDATE()
                     AND a.status != 'cancelada') AS reserved_today
             FROM doctor_schedule ds
             JOIN doctor d ON d.docid = ds.docid
             LEFT JOIN session s ON s.sessionid = ds.sessionid
             ORDER BY d.dname, ds.day_of_week, ds.start_time"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAvailableSlots($scheduleid, $date) {
        $stmt = $this->conn->prepare(
            "SELECT ds.start_time, ds.end_time, ds.slot_duration_min, ds.max_patients_per_day,
                    ds.docid
             FROM doctor_schedule ds WHERE ds.scheduleid=?"
        );
        $stmt->execute([$scheduleid]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$schedule) return [];

        // Generar todos los slots del horario
        $slots   = [];
        $current = strtotime($date . ' ' . $schedule['start_time']);
        $end     = strtotime($date . ' ' . $schedule['end_time']);
        $duration = (int)$schedule['slot_duration_min'];

        while ($current + ($duration * 60) <= $end) {
            $slots[] = date('H:i', $current);
            $current += $duration * 60;
        }

        // Quitar slots ya reservados ese día
        $taken = $this->conn->prepare(
            "SELECT appointment_time FROM appointment
             WHERE scheduleid=? AND appointment_date=? AND status != 'cancelada'"
        );
        $taken->execute([$scheduleid, $date]);
        $takenTimes = $taken->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_filter($slots, fn($s) => !in_array($s . ':00', $takenTimes)));
    }

    public function createSchedule($docid, $sessionid, $day_of_week, $start_time, $end_time, $slot_duration_min, $max_patients_per_day) {
        try {
            // Verificar que no exista ya ese horario para ese doctor y día
            $check = $this->conn->prepare(
                "SELECT COUNT(*) FROM doctor_schedule
                 WHERE docid=? AND day_of_week=? AND is_active=1"
            );
            $check->execute([$docid, $day_of_week]);
            if ($check->fetchColumn() > 0) {
                return ["status" => "error", "message" => "El doctor ya tiene un horario activo ese día."];
            }

            $this->conn->prepare(
                "INSERT INTO doctor_schedule
                 (docid, sessionid, day_of_week, start_time, end_time, slot_duration_min, max_patients_per_day, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
            )->execute([$docid, $sessionid, $day_of_week, $start_time, $end_time, $slot_duration_min, $max_patients_per_day]);

            return ["status" => "success", "message" => "Horario creado correctamente."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    public function updateSchedule($scheduleid, $data) {
        try {
            $this->conn->prepare(
                "UPDATE doctor_schedule
                 SET sessionid=?, day_of_week=?, start_time=?, end_time=?,
                     slot_duration_min=?, max_patients_per_day=?, is_active=?
                 WHERE scheduleid=?"
            )->execute([
                $data['sessionid'], $data['day_of_week'],
                $data['start_time'], $data['end_time'],
                $data['slot_duration_min'], $data['max_patients_per_day'],
                $data['is_active'], $scheduleid
            ]);
            return ["status" => "success", "message" => "Horario actualizado correctamente."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    public function deleteSchedule($scheduleid) {
        try {
            $this->conn->prepare("DELETE FROM appointment WHERE scheduleid=?")->execute([$scheduleid]);
            $this->conn->prepare("DELETE FROM doctor_schedule WHERE scheduleid=?")->execute([$scheduleid]);
            return ["status" => "success", "message" => "Horario eliminado."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // ── Auxiliares ────────────────────────────────────────────────────────────

    public function getAllSessions() {
        return $this->conn->query(
            "SELECT sessionid as id, title FROM session ORDER BY title"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSpecialties() {
        return $this->conn->query(
            "SELECT id, name FROM specialties ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}