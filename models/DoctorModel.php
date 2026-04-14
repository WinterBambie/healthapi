<?php
class DoctorModel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getTodayAppointments($docid) {
        $stmt = $this->conn->prepare(
            "SELECT a.appoid, p.pname AS patient, p.pphone AS patient_phone,
                    a.appointment_time, a.duration_min, a.status, s.title AS session
             FROM appointment a
             JOIN patient p ON p.pid = a.pid
             JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
             LEFT JOIN session s ON s.sessionid = ds.sessionid
             WHERE ds.docid = ? AND a.appointment_date = CURDATE() AND a.status != 'cancelada'
             ORDER BY a.appointment_time ASC"
        );
        $stmt->execute([$docid]);
        return $stmt->fetchAll();
    }

    public function getWeeklyAgenda($docid) {
        $stmt = $this->conn->prepare(
            "SELECT a.appoid, p.pname AS patient, p.pphone AS patient_phone,
                    a.appointment_date, a.appointment_time, a.duration_min, a.status,
                    s.title AS session
             FROM appointment a
             JOIN patient p ON p.pid = a.pid
             JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
             LEFT JOIN session s ON s.sessionid = ds.sessionid
             WHERE ds.docid = ?
               AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
               AND a.status != 'cancelada'
             ORDER BY a.appointment_date ASC, a.appointment_time ASC"
        );
        $stmt->execute([$docid]);
        return $stmt->fetchAll();
    }

    public function getDoctorStats($docid) {
        $run = function($sql, $params) {
            $s = $this->conn->prepare($sql);
            $s->execute($params);
            return (int)$s->fetchColumn();
        };
        return [
            "today_appointments" => $run(
                "SELECT COUNT(*) FROM appointment a
                 JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
                 WHERE ds.docid = ? AND a.appointment_date = CURDATE() AND a.status != 'cancelada'",
                [$docid]
            ),
            "week_appointments" => $run(
                "SELECT COUNT(*) FROM appointment a
                 JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
                 WHERE ds.docid = ?
                   AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                   AND a.status != 'cancelada'",
                [$docid]
            ),
            "total_patients" => $run(
                "SELECT COUNT(DISTINCT a.pid) FROM appointment a
                 JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
                 WHERE ds.docid = ?",
                [$docid]
            ),
            "completed" => $run(
                "SELECT COUNT(*) FROM appointment a
                 JOIN doctor_schedule ds ON ds.scheduleid = a.scheduleid
                 WHERE ds.docid = ? AND a.status = 'completada'",
                [$docid]
            ),
        ];
    }

    public function getDoctorSchedules($docid) {
        $stmt = $this->conn->prepare(
            "SELECT ds.scheduleid, ds.day_of_week, ds.start_time, ds.end_time,
                    ds.slot_duration_min, ds.max_patients_per_day, ds.is_active,
                    s.title AS session
             FROM doctor_schedule ds
             LEFT JOIN session s ON s.sessionid = ds.sessionid
             WHERE ds.docid = ?
             ORDER BY ds.day_of_week, ds.start_time"
        );
        $stmt->execute([$docid]);
        return $stmt->fetchAll();
    }

    public function updateProfile($conn, $docid, $data) {
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("SELECT demail FROM doctor WHERE docid = ?");
            $stmt->execute([$docid]);
            $doctor = $stmt->fetch();
            if (!$doctor) {
                $conn->rollBack();
                return ["status" => "error", "message" => "Doctor no encontrado."];
            }

            $oldEmail = $doctor['demail'];
            $newEmail = !empty($data['demail']) ? trim($data['demail']) : $oldEmail;

            $conn->prepare(
                "UPDATE doctor SET dname=?, demail=?, dphone=? WHERE docid=?"
            )->execute([trim($data['dname'] ?? ''), $newEmail, trim($data['dphone'] ?? ''), $docid]);

            if ($oldEmail !== $newEmail) {
                $conn->prepare("UPDATE webuser SET email=? WHERE email=?")->execute([$newEmail, $oldEmail]);
            }

            if (!empty($data['new_password'])) {
                $wu = $conn->prepare("SELECT password FROM webuser WHERE email=?");
                $wu->execute([$newEmail]);
                $webuser = $wu->fetch();
                if (!$webuser) {
                    $wu2 = $conn->prepare("SELECT password FROM webuser WHERE email=?");
                    $wu2->execute([$oldEmail]);
                    $webuser = $wu2->fetch();
                }
                if (!$webuser || !password_verify($data['current_password'] ?? '', $webuser['password'])) {
                    $conn->rollBack();
                    return ["status" => "error", "message" => "La contraseña actual es incorrecta."];
                }
                $newHash = password_hash($data['new_password'], PASSWORD_DEFAULT);
                $conn->prepare("UPDATE doctor SET dpassword=? WHERE docid=?")->execute([$newHash, $docid]);
                $conn->prepare("UPDATE webuser SET password=? WHERE email=?")->execute([$newHash, $newEmail]);
            }

            $conn->commit();
            return ["status" => "success", "message" => "Perfil actualizado correctamente."];
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }
}
