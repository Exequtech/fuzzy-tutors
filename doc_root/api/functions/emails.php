<?php

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function SendForgotPassword($username, $email, $token)
{
    global $nrHost, $nrEmail, $nrPassword, $nrEncryption, $nrPort, $domain;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $nrHost;
        $mail->SMTPAuth = true;
        $mail->Username = $nrEmail;
        $mail->Password = $nrPassword;
        $mail->SMTPSecure = match($nrEncryption)
        {
            '' => '',
            'tls' => PHPMailer::ENCRYPTION_STARTTLS,
            'ssl' => PHPMailer::ENCRYPTION_SMTPS
        };
        $mail->Port = $nrPort;

        $mail->setFrom($nrEmail, 'Fuzzy Tutors');
        $mail->addAddress($email);
        
        $mail->isHTML(true);
        $mail->Subject = "Password reset";
        $mail->Body = "<p>Hello <b>$username</b>!<br> A forgot password was logged on your account. If this wasn't you, please ignore this email. <br><br> Click <a href=\"https://$domain/reset-password.html?token=$token\">here</a> to reset your password (the link expires in 30 minutes)<br><br>Best regards, <br><b>Fuzzy Tutors</b></p>";
        $mail->AltBody = "Hello $username!\n Here's your password reset link (ignore it if this wasn't you): https://$domain/reset-password.html?token=$token\nThe link expires in 30 minutes. Best regards,\nFuzzy Tutors";

        $mail->send();
    } catch(Exception $e)
    {
        InternalError("Failed to send forgotpassword email: $mail->ErrorInfo");
    }
}
