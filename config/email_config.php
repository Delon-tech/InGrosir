<?php

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Atau include manual jika download PHPMailer
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

function sendResetPasswordEmail($to_email, $to_name, $reset_link) {
    $mail = new PHPMailer(true);
    
    try {
        // KONFIGURASI SMTP - Sesuaikan dengan hosting Rumahweb Anda
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Ganti dengan SMTP Rumahweb
        $mail->SMTPAuth   = true;
        $mail->Username   = 'muhagil282004@gmail.com'; // Email Anda
        $mail->Password   = 'omio fsuy vkus jfvd'; // Password email
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';
        
        // Pengirim
        $mail->setFrom('muhagil282004@gmail.com', 'InGrosir Support');
        
        // Penerima
        $mail->addAddress($to_email, $to_name);
        
        // Konten Email
        $mail->isHTML(true);
        $mail->Subject = 'Reset Password - InGrosir';
        
        // Template Email HTML
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #2563eb, #10b981); padding: 30px; text-align: center; color: white; border-radius: 10px 10px 0 0; }
                .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
                .button { display: inline-block; padding: 15px 30px; background: #2563eb; color: white !important; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 12px; }
                .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1 style="margin: 0; font-size: 32px;">InGrosir</h1>
                    <p style="margin: 10px 0 0 0; opacity: 0.9;">Reset Password</p>
                </div>
                
                <div class="content">
                    <h2>Halo, ' . htmlspecialchars($to_name) . '!</h2>
                    <p>Kami menerima permintaan untuk mereset password akun InGrosir Anda.</p>
                    
                    <p>Silakan klik tombol di bawah ini untuk membuat password baru:</p>
                    
                    <center>
                        <a href="' . $reset_link . '" class="button">Reset Password Saya</a>
                    </center>
                    
                    <div class="warning">
                        <strong>⚠️ Penting:</strong>
                        <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                            <li>Link ini hanya berlaku selama <strong>1 jam</strong></li>
                            <li>Jika Anda tidak meminta reset password, abaikan email ini</li>
                            <li>Jangan bagikan link ini kepada siapapun</li>
                        </ul>
                    </div>
                    
                    <p style="color: #6b7280; font-size: 14px; margin-top: 20px;">
                        Atau copy dan paste link berikut ke browser Anda:<br>
                        <a href="' . $reset_link . '" style="color: #2563eb; word-break: break-all;">' . $reset_link . '</a>
                    </p>
                </div>
                
                <div class="footer">
                    <p>Email ini dikirim otomatis oleh sistem InGrosir</p>
                    <p>© ' . date('Y') . ' InGrosir. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        // Plain text alternative
        $mail->AltBody = "Halo $to_name,\n\nKlik link berikut untuk reset password:\n$reset_link\n\nLink berlaku 1 jam.\n\nInGrosir Support";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * ALTERNATIF: Jika PHPMailer tidak tersedia, gunakan mail() PHP native
 * (Kurang reliable tapi bisa untuk testing)
 */
function sendResetPasswordEmailSimple($to_email, $to_name, $reset_link) {
    $subject = "Reset Password - InGrosir";
    
    $message = "
    Halo $to_name,
    
    Kami menerima permintaan reset password untuk akun Anda.
    
    Klik link berikut untuk reset password:
    $reset_link
    
    Link ini berlaku selama 1 jam.
    
    Jika Anda tidak meminta reset password, abaikan email ini.
    
    Terima kasih,
    Tim InGrosir
    ";
    
    $headers = "From: noreply@yourdomain.com\r\n";
    $headers .= "Reply-To: support@yourdomain.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($to_email, $subject, $message, $headers);
}
?>