<?php
namespace App\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private PHPMailer $mail;

    public function __construct(array $config)
    {
        $this->mail = new PHPMailer(true);

        // SMTP config
        $this->mail->isSMTP();
        $this->mail->Host       = $config['host'] ?? '';
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = $config['username'] ?? '';
        $this->mail->Password   = $config['password'] ?? '';
        $this->mail->SMTPSecure = $config['encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = (int)($config['port'] ?? 587);

        // Sender
        if (!empty($config['from_email'])) {
            $this->mail->setFrom($config['from_email'], $config['from_name'] ?? '');
        }
    }

    /**
     * Send an email.
     * @return bool True on success, false on failure
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, ?string $plainBody = null): bool
    {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($toEmail, $toName);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $htmlBody;
            if ($plainBody !== null) {
                $this->mail->AltBody = $plainBody;
            }
            return $this->mail->send();
        } catch (Exception $e) {
            error_log('Mailer Error: ' . $this->mail->ErrorInfo);
            return false;
        }
    }
}
