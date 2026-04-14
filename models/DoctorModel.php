<?php
class DoctorModel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Obtener la agenda específica de un doctor
     */
    public function getDoctorAgenda($docid) {
        $stmt = $this->conn->prepare(
            "SELECT 
                a.appoid, 
                p.pname AS patient_name, 
                p.pphone AS patient_phone,
                s.title AS session_title,
                a.appointment_date, 
                a.appointment_number, 
                a.status
             FROM appointment a
             JOIN patient p ON p.pid = a.pid
             JOIN schedule sc ON sc.scheduleid = a.scheduleid
             LEFT JOIN session s ON s.sessionid = sc.sessionid
             WHERE sc.docid = ?
             ORDER BY a.appointment_date ASC, a.appointment_number ASC"
        );
        $stmt->bind_param("i", $docid);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtener los horarios (schedules) creados por el doctor
     */
    public function getDoctorSchedules($docid) {
        $stmt = $this->conn->prepare(
            "SELECT 
                sc.scheduleid, 
                s.title AS session_title, 
                sc.scheduledate, 
                sc.scheduletime, 
                sc.max_patients,
                (SELECT COUNT(*) FROM appointment a WHERE a.scheduleid = sc.scheduleid) AS reserved_slots
             FROM schedule sc
             LEFT JOIN session s ON s.sessionid = sc.sessionid
             WHERE sc.docid = ?
             ORDER BY sc.scheduledate DESC"
        );
        $stmt->bind_param("i", $docid);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtener estadísticas rápidas para el Dashboard del Doctor
     */
    public function getDoctorStats($docid) {
        $stats = [];

        // Citas para hoy
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM appointment a 
                                     JOIN schedule sc ON a.scheduleid = sc.scheduleid 
                                     WHERE sc.docid = ? AND a.appointment_date = CURDATE()");
        $stmt->bind_param("i", $docid);
        $stmt->execute();
        $stats['today_appointments'] = $stmt->get_result()->fetch_assoc()['total'];

        // Total de pacientes atendidos (únicos)
        $stmt = $this->conn->prepare("SELECT COUNT(DISTINCT a.pid) as total FROM appointment a 
                                     JOIN schedule sc ON a.scheduleid = sc.scheduleid 
                                     WHERE sc.docid = ?");
        $stmt->bind_param("i", $docid);
        $stmt->execute();
        $stats['total_patients'] = $stmt->get_result()->fetch_assoc()['total'];

        return $stats;
    }
}