<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/BaseController.php';
require_once __DIR__ . '/../config/JwtHelper.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/AdminController.php';
require_once __DIR__ . '/../controllers/AppointmentController.php';

$action = $_GET['action'] ?? '';

$publicActions = ['login', 'register', 'documentTypes'];

if (!in_array($action, $publicActions)) {
    $headers = getallheaders();
    $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token   = str_replace('Bearer ', '', trim($auth));
    if (!$token) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Token requerido"]);
        exit();
    }
    $currentUser = JwtHelper::verify($token);
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Token inválido o expirado"]);
        exit();
    }
}

$authCtrl  = new AuthController();
$adminCtrl = new AdminController();
$apptCtrl  = new AppointmentController();

switch ($action) {

    // ── Auth ──────────────────────────────────────────────────────────────────
    case 'login':           $authCtrl->login();            break;
    case 'register':        $authCtrl->registerPatient();  break;
    case 'documentTypes':   $authCtrl->getDocumentTypes(); break;

    // ── Stats ─────────────────────────────────────────────────────────────────
    case 'adminStats':      $adminCtrl->getStats();        break;

    // ── Pacientes ─────────────────────────────────────────────────────────────
    case 'adminPatients':   $adminCtrl->getPatients();     break;
    case 'updatePatient':   $adminCtrl->updatePatient();   break;
    case 'deletePatient':   $adminCtrl->deletePatient();   break;

    // ── Doctores ─────────────────────────────────────────────────────────────
    case 'adminDoctors':    $adminCtrl->getDoctors();      break;
    case 'createDoctor':    $adminCtrl->createDoctor();    break;
    case 'updateDoctor':    $adminCtrl->updateDoctor();    break;
    case 'deleteDoctor':    $adminCtrl->deleteDoctor();    break;

    // ── Citas ─────────────────────────────────────────────────────────────────
    case 'adminAppointments':       $adminCtrl->getAppointments();         break;
    case 'updateAppointmentStatus': $adminCtrl->updateAppointmentStatus(); break;
    case 'deleteAppointment':       $adminCtrl->deleteAppointment();       break;
    case 'createAppointment':       $apptCtrl->create();                   break;
    case 'getAppointmentsByPatient':$apptCtrl->getByPatient();             break;
    case 'getAppointmentsByDoctor': $apptCtrl->getByDoctor();              break;
    case 'cancelAppointment':       $apptCtrl->cancel();                   break;

    // ── Horarios ─────────────────────────────────────────────────────────────
    case 'adminSchedules':    $adminCtrl->getSchedules();      break;
    case 'availableSlots':    $adminCtrl->getAvailableSlots(); break;
    case 'createSchedule':    $adminCtrl->createSchedule();    break;
    case 'updateSchedule':    $adminCtrl->updateSchedule();    break;
    case 'deleteSchedule':    $adminCtrl->deleteSchedule();    break;

    // ── Auxiliares ────────────────────────────────────────────────────────────
    case 'getSessions':       $adminCtrl->getSessions();    break;
    case 'adminSpecialties':  $adminCtrl->getSpecialties(); break;

    default:
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Acción '$action' no encontrada"]);
        break;
}