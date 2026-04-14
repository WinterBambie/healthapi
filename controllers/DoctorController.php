<?php
require_once __DIR__ . '/../models/AppointmentModel.php';
require_once __DIR__ . '/../models/DoctorModel.php';
class DoctorController {

    private $model;

    public function __construct($conn) {
        $this->model = new AppointmentModel($conn);
    }

    public function handle($method) {

        switch ($method) {

            case "GET":
                $docid = $_GET["docid"];
                echo json_encode($this->model->getByDoctor($docid));
                break;
        }
    }
}