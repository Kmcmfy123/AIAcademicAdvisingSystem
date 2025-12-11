<?php
// Mailer.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/includes/init.php';

class Mailer {
    private PHPMailer $mail;

    public function __construct(array $config) {
        $this->mail = new PHPMailer(true);

        // SMTP config
        $this->mail->isSMTP();
        $this->mail->Host       = $config['host'];
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = $config['username'];
        $this->mail->Password   = $config['password'];
        $this->mail->SMTPSecure = $config['encryption'];
        $this->mail->Port       = $config['port'];

        // Sender
        $this->mail->setFrom($config['from_email'], $config['from_name']);
    }

    /**
     * Send an email.
     * 
     * @param string $to_email   Recipient email
     * @param string $to_name    Recipient name (optional)
     * @param string $subject    Email subject
     * @param string $body_html  HTML body
     * @param string|null $body_plain Plain-text version (optional)
     * @return bool             True on success, false on failure
     */
    public function send(string $to_email, string $to_name, string $subject, string $body_html, string $body_plain = null): bool {
        try {
            $this->mail->addAddress($to_email, $to_name);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body_html;

            if ($body_plain) {
                $this->mail->AltBody = $body_plain;
            }

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Mailer Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }
}
