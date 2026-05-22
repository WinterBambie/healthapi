<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Pre-flight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
$action        = $_GET['action'] ?? '';
$publicActions = ['login', 'register', 'documentTypes', 'forgotPassword', 'resetPassword', 'validateResetToken'];

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/BaseController.php';
require_once __DIR__ . '/../config/JwtHelper.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/AdminController.php';
require_once __DIR__ . '/../controllers/PatientController.php';
require_once __DIR__ . '/../controllers/DoctorController.php';
require_once __DIR__ . '/../controllers/PasswordResetController.php';
require_once __DIR__ . '/../controllers/HistoriaClinicaController.php';

// ── Autenticación ─────────────────────────────────────────────────────────────
if (!in_array($action, $publicActions)) {
    $headers = getallheaders();
    $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token   = str_replace('Bearer ', '', trim($auth));

    if (!$token) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Token requerido."]);
        exit;
    }

    $currentUser = JwtHelper::verify($token);
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Token inválido o expirado."]);
        exit;
    }
}

// ── Instanciar controladores ──────────────────────────────────────────────────
$authCtrl    = new AuthController();
$adminCtrl   = new AdminController();
$patientCtrl = new PatientController();
$doctorCtrl  = new DoctorController();
$resetCtrl = new PasswordResetController();
$hcCtrl = new HistoriaClinicaController();

// ── Rutas ─────────────────────────────────────────────────────────────────────
switch ($action) {

    // Auth
    case 'login':         $authCtrl->login();            break;
    case 'register':      $authCtrl->registerPatient();  break;
    case 'documentTypes': $authCtrl->getDocumentTypes(); break;

    // Admin — stats
    case 'adminStats':    $adminCtrl->getStats();        break;

    // Admin — pacientes
    case 'adminPatients': $adminCtrl->getPatients();     break;
    case 'deletePatient': $adminCtrl->deletePatient();   break;

    // Admin — doctores
    case 'adminDoctors':  $adminCtrl->getDoctors();      break;
    case 'createDoctor':  $adminCtrl->createDoctor();    break;
    case 'updateDoctor':  $adminCtrl->updateDoctor();    break;
    case 'deleteDoctor':  $adminCtrl->deleteDoctor();    break;

    // Admin — citas
    case 'adminAppointments':       $adminCtrl->getAppointments();         break;
    case 'updateAppointmentStatus': $adminCtrl->updateAppointmentStatus(); break;
    case 'deleteAppointment':       $adminCtrl->deleteAppointment();       break;

    // Admin — horarios
    case 'adminSchedules': $adminCtrl->getSchedules();      break;
    case 'availableSlots': $adminCtrl->getAvailableSlots(); break;
    case 'createSchedule': $adminCtrl->createSchedule();    break;
    case 'updateSchedule': $adminCtrl->updateSchedule();    break;
    case 'deleteSchedule': $adminCtrl->deleteSchedule();    break;

    // Admin — auxiliares
    case 'getSessions':      $adminCtrl->getSessions();    break;
    case 'adminSpecialties': $adminCtrl->getSpecialties(); break;

    // Paciente
    case 'getAppointmentsByPatient': $patientCtrl->getByPatient();      break;
    case 'patientStats':             $patientCtrl->getStatsByPatient(); break;
    case 'cancelAppointment':        $patientCtrl->cancelByPatient();   break;
    case 'createAppointment':        $patientCtrl->create();            break;
    case 'updatePatient':            $patientCtrl->updateProfile();     break;
    case 'deletePatient':            $patientCtrl->deleteAccount();     break;

    // Doctor
    case 'doctorAppointments':      $doctorCtrl->getAppointments();         break;
    case 'doctorToday':             $doctorCtrl->getTodayAppointments();    break;
    case 'doctorWeeklyAgenda':      $doctorCtrl->getWeeklyAgenda();         break;
    case 'doctorStats':             $doctorCtrl->getStats();                break;
    case 'doctorSchedules':         $doctorCtrl->getSchedules();            break;
    case 'doctorUpdateAppointment': $doctorCtrl->updateAppointmentStatus(); break;
    case 'doctorRequestSchedule':   $doctorCtrl->requestScheduleChange();   break;
    case 'doctorUpdateProfile':     $doctorCtrl->updateProfile();           break;
    case 'doctorProfile':           $doctorCtrl->getProfile();              break;
    case 'forgotPassword':      $resetCtrl->forgotPassword(); break;
    case 'resetPassword':       $resetCtrl->resetPassword();  break;
    case 'validateResetToken':  $resetCtrl->validateToken();  break;
    //HISTORIA CLÍNICA
    case 'hcPacientes':      $hcCtrl->getPacientes();    break;
    case 'hcGet':            $hcCtrl->getHistoria();     break;
    case 'hcCrearRegistro':  $hcCtrl->crearRegistro();   break;
    case 'hcAnularRegistro': $hcCtrl->anularRegistro();  break;
//nomax65539@pmdeal.com
    default:
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Acción '$action' no encontrada."]);
        break;
}
