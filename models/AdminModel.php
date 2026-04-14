<?php
class AdminModel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    public function getTotalPatients() {
        return (int)$this->conn->query("SELECT COUNT(*) FROM patient")->fetchColumn();
    }

    public function getTotalDoctors() {
        return (int)$this->conn->query("SELECT COUNT(*) FROM doctor")->fetchColumn();
    }

    public function getAppointmentsToday() {
        return (int)$this->conn->query(
            "SELECT COUNT(*) FROM appointment
             WHERE appointment_date = CURDATE() AND status != 'cancelada'"
        )->fetchColumn();
    }

    public function getAppointmentsThisMonth() {
        return (int)$this->conn->query(
            "SELECT COUNT(*) FROM appointment
             WHERE MONTH(appointment_date) = MONTH(CURDATE())
               AND YEAR(appointment_date)  = YEAR(CURDATE())
               AND status != 'cancelada'"
        )->fetchColumn();
    }

    public function getAppointmentsReserved() {
        return (int)$this->conn->query(
            "SELECT COUNT(*) FROM appointment WHERE status = 'reservada'"
        )->fetchColumn();
    }

    // ── Pacientes ─────────────────────────────────────────────────────────────

    public function getAllPatients() {
        return $this->conn->query(
            "SELECT pid, pname, pemail, pphone, paddress, pbirthdate
             FROM patient ORDER BY pname"
        )->fetchAll();
    }

    public function updatePatient($pid, $data) {
        try {
            $this->conn->prepare(
                "UPDATE patient SET pname=?, pemail=?, pphone=?, paddress=? WHERE pid=?"
            )->execute([$data['pname'], $data['pemail'], $data['pphone'], $data['paddress'], $pid]);
            return ["status" => "success", "message" => "Paciente actualizado correctamente."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    public function deletePatient($pid) {
        try {
            $this->conn->prepare("DELETE FROM appointment WHERE pid = ?")->execute([$pid]);
            $email = $this->conn->prepare("SELECT pemail FROM patient WHERE pid = ?");
            $email->execute([$pid]);
            $pemail = $email->fetchColumn();
            if ($pemail) {
                $this->conn->prepare("DELETE FROM webuser WHERE email = ?")->execute([$pemail]);
            }
            $this->conn->prepare("DELETE FROM patient WHERE pid = ?")->execute([$pid]);
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
        )->fetchAll();
    }

    public function createDoctor($dname, $demail, $dpassword, $dphone, $ddocument, $tipo_documento_id, $specialty_name) {
        try {
            $this->conn->beginTransaction();

            // Verificar email duplicado
            $ck = $this->conn->prepare("SELECT COUNT(*) FROM webuser WHERE email = ?");
            $ck->execute([$demail]);
            if ($ck->fetchColumn() > 0) {
                $this->conn->rollBack();
                return ["status" => "error", "message" => "El correo ya está registrado."];
            }

            $hash = password_hash($dpassword, PASSWORD_DEFAULT);

            // Especialidad: buscar o crear
            $sp = $this->conn->prepare("SELECT id FROM specialties WHERE name = ?");
            $sp->execute([$specialty_name]);
            $spec = $sp->fetchColumn();
            if (!$spec) {
                $this->conn->prepare("INSERT INTO specialties (name) VALUES (?)")->execute([$specialty_name]);
                $spec = $this->conn->lastInsertId();
            }

            $this->conn->prepare(
                "INSERT INTO doctor (dname, demail, dpassword, dphone, ddocument, tipo_documento_id, specialty_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([$dname, $demail, $hash, $dphone, $ddocument, $tipo_documento_id, $spec]);

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

public function updateDoctor($docid, $data) {
    try {

        if (!$docid) {
            return ["status" => "error", "message" => "docid requerido"];
        }

        $dname  = $data['dname']  ?? '';
        $demail = $data['demail'] ?? '';
        $dphone = $data['dphone'] ?? '';

        if ($dname === '' || $demail === '') {
            return ["status" => "error", "message" => "Datos incompletos"];
        }

        $stmt = $this->conn->prepare(
            "UPDATE doctor SET dname = ?, demail = ?, dphone = ? WHERE docid = ?"
        );

        $stmt->execute([$dname, $demail, $dphone, $docid]);

        return ["status" => "success", "message" => "Doctor actualizado correctamente."];

    } catch (Exception $e) {
        return ["status" => "error", "message" => $e->getMessage()];
    }
}

    public function deleteDoctor($docid) {
        try {
            $this->conn->beginTransaction();

            $em = $this->conn->prepare("SELECT demail FROM doctor WHERE docid = ?");
            $em->execute([$docid]);
            $demail = $em->fetchColumn();

            // Eliminar citas de los horarios del doctor
            $this->conn->prepare(
                "DELETE a FROM appointment a
                 JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
                 WHERE ds.docid = ?"
            )->execute([$docid]);

            $this->conn->prepare("DELETE FROM doctor_schedule WHERE docid = ?")->execute([$docid]);
            if ($demail) {
                $this->conn->prepare("DELETE FROM webuser WHERE email = ?")->execute([$demail]);
            }
            $this->conn->prepare("DELETE FROM doctor WHERE docid = ?")->execute([$docid]);

            $this->conn->commit();
            return ["status" => "success", "message" => "Doctor eliminado correctamente."];
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
        )->fetchAll();
    }

    public function updateAppointmentStatus($appoid, $status) {
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

    public function deleteAppointment($appoid) {
        try {
            $this->conn->prepare("DELETE FROM appointment WHERE appoid = ?")->execute([$appoid]);
            return ["status" => "success", "message" => "Cita eliminada."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // ── Horarios ──────────────────────────────────────────────────────────────

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
        )->fetchAll();
    }

    public function getAvailableSlots($scheduleid, $date) {
        $stmt = $this->conn->prepare(
            "SELECT start_time, end_time, slot_duration_min FROM doctor_schedule WHERE scheduleid = ?"
        );
        $stmt->execute([$scheduleid]);
        $schedule = $stmt->fetch();
        if (!$schedule) return [];

        // Generar todos los slots
        $slots   = [];
        $current = strtotime($date . ' ' . $schedule['start_time']);
        $end     = strtotime($date . ' ' . $schedule['end_time']);
        $dur     = (int)$schedule['slot_duration_min'] * 60;

        while ($current + $dur <= $end) {
            $slots[] = date('H:i', $current);
            $current += $dur;
        }

        // Quitar los ya reservados
        $taken = $this->conn->prepare(
            "SELECT appointment_time FROM appointment
             WHERE scheduleid = ? AND appointment_date = ? AND status != 'cancelada'"
        );
        $taken->execute([$scheduleid, $date]);
        $takenTimes = $taken->fetchAll(PDO::FETCH_COLUMN);

        // appointment_time viene como HH:MM:SS — normalizar a HH:MM
        $takenNorm = array_map(fn($t) => substr($t, 0, 5), $takenTimes);

        return array_values(array_filter($slots, fn($s) => !in_array($s, $takenNorm)));
    }

    public function createSchedule($docid, $sessionid, $day_of_week, $start_time, $end_time, $slot_duration_min, $max_patients_per_day) {
        try {
            $ck = $this->conn->prepare(
                "SELECT COUNT(*) FROM doctor_schedule
                 WHERE docid = ? AND day_of_week = ? AND is_active = 1"
            );
            $ck->execute([$docid, $day_of_week]);
            if ($ck->fetchColumn() > 0) {
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
                $data['is_active'] ?? 1,
                $scheduleid,
            ]);
            return ["status" => "success", "message" => "Horario actualizado correctamente."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    public function deleteSchedule($scheduleid) {
        try {
            $this->conn->prepare("DELETE FROM appointment WHERE scheduleid = ?")->execute([$scheduleid]);
            $this->conn->prepare("DELETE FROM doctor_schedule WHERE scheduleid = ?")->execute([$scheduleid]);
            return ["status" => "success", "message" => "Horario eliminado."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // ── Auxiliares ────────────────────────────────────────────────────────────

    public function getAllSessions() {
        return $this->conn->query(
            "SELECT sessionid AS id, title FROM session ORDER BY title"
        )->fetchAll();
    }

    public function getSpecialties() {
        return $this->conn->query(
            "SELECT id, name FROM specialties ORDER BY name"
        )->fetchAll();
    }
}
