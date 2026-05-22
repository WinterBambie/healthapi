<?php
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {

    private static function make(): PHPMailer {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        return $mail;
    }

    public static function passwordReset(string $toEmail, string $toName, string $token): bool {
        try {
            $mail = self::make();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = 'Recuperar contraseña — SoftyHealth';
            $mail->isHTML(true);
            $mail->Body = self::tplPasswordReset($toName, $token);
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer error: " . $e->getMessage());
            return false;
        }
    }

    public static function appointmentCreated(string $toEmail, string $toName, array $data): bool {
        try {
            $mail = self::make();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = 'Cita confirmada — SoftyHealth';
            $mail->isHTML(true);
            $mail->Body = self::tplAppointmentCreated($toName, $data);
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer error: " . $e->getMessage());
            return false;
        }
    }

    public static function appointmentCancelled(string $toEmail, string $toName, array $data): bool {
        try {
            $mail = self::make();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = 'Cita cancelada — SoftyHealth';
            $mail->isHTML(true);
            $mail->Body = self::tplAppointmentCancelled($toName, $data);
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer error: " . $e->getMessage());
            return false;
        }
    }

    private static function tplBase(string $body): string {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
          body{font-family:Arial,sans-serif;background:#f5f7fa;margin:0;padding:20px}
          .card{background:#fff;border-radius:12px;padding:32px;max-width:520px;margin:0 auto;box-shadow:0 2px 8px rgba(0,0,0,.08)}
          .logo{color:#0A76D8;font-size:22px;font-weight:700;text-align:center;margin-bottom:24px}
          .title{color:#212529;font-size:18px;font-weight:600;margin:0 0 12px}
          .row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #e9ecef;font-size:14px}
          .label{color:#666}.value{color:#212529;font-weight:500}
          .btn{display:inline-block;background:#0A76D8;color:#fff!important;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;margin:20px 0}
          .footer{text-align:center;color:#999;font-size:12px;margin-top:24px}
          p{font-size:14px;color:#666}
        </style></head><body><div class='card'>
          <div class='logo'>SoftyHealth</div>
          $body
          <div class='footer'>Mensaje automático — no respondas este correo.</div>
        </div></body></html>";
    }

    private static function tplPasswordReset(string $name, string $token): string {
        $url = FRONTEND_URL . "/reset-password?token=" . urlencode($token);
        return self::tplBase("
          <div class='title'>Recuperar contraseña</div>
          <p>Hola <strong>{$name}</strong>, recibimos una solicitud para restablecer tu contraseña.</p>
          <p style='text-align:center'><a href='{$url}' class='btn'>Restablecer contraseña</a></p>
          <p style='font-size:12px;color:#999;text-align:center'>Este enlace expira en 1 hora. Si no lo solicitaste, ignora este mensaje.</p>
        ");
    }

    private static function tplAppointmentCreated(string $name, array $d): string {
        return self::tplBase("
          <div class='title'>¡Tu cita ha sido confirmada!</div>
          <p>Hola <strong>{$name}</strong>, tu cita fue registrada exitosamente.</p>
          <div class='row'><span class='label'>Doctor</span><span class='value'>{$d['doctor']}</span></div>
          <div class='row'><span class='label'>Sesión</span><span class='value'>{$d['session']}</span></div>
          <div class='row'><span class='label'>Fecha</span><span class='value'>{$d['date']}</span></div>
          <div class='row'><span class='label'>Hora</span><span class='value'>{$d['time']}</span></div>
          <p style='margin-top:16px'>Puedes cancelar con al menos 12 horas de antelación desde tu portal.</p>
        ");
    }

    private static function tplAppointmentCancelled(string $name, array $d): string {
        return self::tplBase("
          <div class='title'>Tu cita ha sido cancelada</div>
          <p>Hola <strong>{$name}</strong>, tu cita fue cancelada.</p>
          <div class='row'><span class='label'>Doctor</span><span class='value'>{$d['doctor']}</span></div>
          <div class='row'><span class='label'>Fecha</span><span class='value'>{$d['date']}</span></div>
          <div class='row'><span class='label'>Hora</span><span class='value'>{$d['time']}</span></div>
          <p style='margin-top:16px'>Si necesitas reagendar hazlo desde tu portal de paciente.</p>
        ");
    }
}