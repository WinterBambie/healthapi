<?php

require_once __DIR__ . '/../models/AppointmentModel.php';

class PatientController {

    private $appointmentModel;

    public function __construct($conn) {
        $this->appointmentModel = new AppointmentModel($conn);
    }

    public function handle($method) {

        $id = $_GET['id'] ?? null;

        switch ($method) {

            case "GET":
                echo json_encode(
                    $this->appointmentModel->getByPatient($id)
                );
                break;

            case "PUT":
                $data = json_decode(file_get_contents("php://input"), true);
                echo json_encode(
                    $this->appointmentModel->cancel($data["appointment_id"])
                );
                break;

            default:
                echo json_encode(["error" => "Método no permitido"]);
        }
    }
}