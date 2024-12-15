<?php

require_once __DIR__ . "/../vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function SendForgotPassword($email, $token)
{
    global $nrHost, $nrEmail, $nrPassword, $nrEncryption, $nrPort;
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

        $mail->setFrom($nrEmail, 'Noreply');
        $mail->addAddress($email);
        
        $mail->isHTML(true);
        $mail->Subject = "Password reset";
        $mail->Body = "<p>Token: $token</p>";
        $mail->AltBody = "Plaintext TEST";

        $mail->send();
    } catch(Exception $e)
    {
        InternalError("Failed to send forgotpassword email: $mail->ErrorInfo");
    }
}