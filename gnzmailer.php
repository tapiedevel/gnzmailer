<?php // Cache-buster: 1724788800
/**
 * GnzMailer - Una clase para el envío de correos electrónicos vía SMTP o mail().
 */
class GnzMailer
{
    // Propiedades del correo
    protected $to = [];
    protected $from = '';
    protected $fromName = '';
    protected $subject = '';
    protected $body = '';
    protected $cc = [];
    protected $bcc = [];
    public $attachments = [];
    protected $headers = [];
    public $error = '';

    // Propiedades de SMTP
    public $smtp_host;
    public $smtp_port;
    public $smtp_user;
    public $smtp_pass;
    public $smtp_encryption;
    public $smtp_connection = null;

    // Tipo de envío: 'mail' o 'smtp'
    public $mailer = 'mail';

    public function __construct($config_path = 'smtp_config.json')
    {
        $this->addHeader('MIME-Version: 1.0');
        $this->addHeader('Content-type: text/html; charset=utf-8');

        if (file_exists($config_path)) {
            $config = json_decode(file_get_contents($config_path), true);
            if ($config && isset($config['host'])) {
                $this->smtp_host = $config['host'];
                $this->smtp_port = $config['port'];
                $this->smtp_user = $config['username'];
                $this->smtp_pass = $config['password'];
                $this->smtp_encryption = isset($config['encryption']) ? $config['encryption'] : null;
                $this->mailer = 'smtp';

                if (isset($config['from_email'])) {
                    $this->setFrom($config['from_email'], isset($config['from_name']) ? $config['from_name'] : '');
                }
                if (isset($config['subject'])) {
                    $this->setSubject($config['subject']);
                }
                if (isset($config['body'])) {
                    $this->setBody($config['body']);
                }
            }
        }
    }

    public function __destruct()
    {
        if (is_resource($this->smtp_connection)) {
            // No es necesario enviar QUIT aquí si la conexión ya se cerró o falló.
            fclose($this->smtp_connection);
        }
    }

    public function setFrom($email, $name = '')
    {
        $this->from = filter_var($email, FILTER_SANITIZE_EMAIL);
        $this->fromName = htmlspecialchars($name);
    }

    public function addTo($email)
    {
        $this->to[] = filter_var($email, FILTER_SANITIZE_EMAIL);
    }

    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function addHeader($header)
    {
        $this->headers[] = $header;
    }

    public function addCC($email)
    {
        $this->cc[] = filter_var($email, FILTER_SANITIZE_EMAIL);
    }

    public function addBCC($email)
    {
        $this->bcc[] = filter_var($email, FILTER_SANITIZE_EMAIL);
    }

    public function addAttachment($path)
    {
        if (file_exists($path)) {
            $this->attachments[] = $path;
        }
    }

    public function send()
    {
        if (empty($this->to) || empty($this->from) || empty($this->subject) || empty($this->body)) {
            $this->error = "Faltan datos para el envío (To, From, Subject, Body).";
            error_log("GnzMailer: " . $this->error);
            return false;
        }

        if ($this->mailer === 'smtp') {
            return $this->sendSmtp();
        } else {
            return $this->sendMail();
        }
    }

    private function sendMail()
    {
        $to = implode(', ', $this->to);
        $fromHeader = "From: " . ($this->fromName ? "{$this->fromName}\" " : "") . "<{$this->from}>";
        
        $headers = $this->headers;
        $headers[] = $fromHeader;
        if (!empty($this->cc)) {
            $headers[] = "Cc: " . implode(', ', $this->cc);
        }
        if (!empty($this->bcc)) {
            $headers[] = "Bcc: " . implode(', ', $this->bcc);
        }

        if (empty($this->attachments)) {
            $headers_string = implode("\r\n", $headers);
            if (mail($to, htmlspecialchars($this->subject), $this->body, $headers_string)) {
                return true;
            } else {
                $this->error = "Falló el envío del correo a través de la función mail().";
                error_log("GnzMailer: " . $this->error);
                return false;
            }
        } else {
            $boundary = "boundary-" . md5(time());
            
            // Filter out the old Content-Type header
            $headers = array_filter($headers, function($header) {
                return strpos(strtolower($header), 'content-type:') === false;
            });

            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
            $headers_string = implode("\r\n", $headers);

            $message = "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=\"utf-8\"\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $this->body . "\r\n";

            foreach ($this->attachments as $attachment) {
                $file_name = basename($attachment);
                $file_content = chunk_split(base64_encode(file_get_contents($attachment)));
                $message .= "--{$boundary}\r\n";
                $message .= "Content-Type: application/octet-stream; name=\"{$file_name}\"\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n";
                $message .= "Content-Disposition: attachment; filename=\"{$file_name}\"\r\n\r\n";
                $message .= $file_content . "\r\n";
            }

            $message .= "--{$boundary}--";

            if (mail($to, htmlspecialchars($this->subject), $message, $headers_string)) {
                return true;
            } else {
                $this->error = "Falló el envío del correo con adjuntos a través de la función mail().";
                error_log("GnzMailer: " . $this->error);
                return false;
            }
        }
    }

    private function sendSmtp()
    {
        if (!$this->smtpConnect()) {
            return false;
        }

        if (!$this->smtpCommand("EHLO " . $this->smtp_host, 250)) return false;

        if ($this->smtp_encryption === 'tls') {
            if (!$this->smtpCommand("STARTTLS", 220)) return false;
            if (!stream_socket_enable_crypto($this->smtp_connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->error = "Failed to enable TLS.";
                error_log("GnzMailer SMTP Error: " . $this->error);
                return false;
            }
            if (!$this->smtpCommand("EHLO " . $this->smtp_host, 250)) return false;
        }

        if (!empty($this->smtp_user)){
            if (!$this->smtpCommand("AUTH LOGIN", 334)) return false;
            if (!$this->smtpCommand(base64_encode($this->smtp_user), 334)) return false;
            if (!$this->smtpCommand(base64_encode($this->smtp_pass), 235)) return false;
        }

        if (!$this->smtpCommand("MAIL FROM:<{$this->from}>", 250)) return false;
        foreach ($this->to as $recipient) {
            if (!$this->smtpCommand("RCPT TO:<{$recipient}>", 250)) return false;
        }

        foreach ($this->cc as $recipient) {
            if (!$this->smtpCommand("RCPT TO:<{$recipient}>", 250)) return false;
        }

        foreach ($this->bcc as $recipient) {
            if (!$this->smtpCommand("RCPT TO:<{$recipient}>", 250)) return false;
        }

        if (!$this->smtpCommand("DATA", 354)) return false;

        $fromHeader = "From: " . ($this->fromName ? "{$this->fromName}\" " : "") . "<{$this->from}>";
        $toHeader = "To: " . implode(', ', $this->to);
        $ccHeader = !empty($this->cc) ? "Cc: " . implode(', ', $this->cc) : "";
        $subjectHeader = "Subject: " . htmlspecialchars($this->subject);

        if (empty($this->attachments)) {
            $fullHeaders = implode("\r\n", array_filter(array_merge([$fromHeader, $toHeader, $ccHeader, $subjectHeader], $this->headers)));
            $data = $fullHeaders . "\r\n\r\n" . $this->body . "\r\n.";
        } else {
            $boundary = "boundary-" . md5(time());
            
            // Filter out the old Content-Type header
            $this->headers = array_filter($this->headers, function($header) {
                return strpos(strtolower($header), 'content-type:') === false;
            });

            $this->headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
            
            $fullHeaders = implode("\r\n", array_filter(array_merge([$fromHeader, $toHeader, $ccHeader, $subjectHeader], $this->headers)));

            $message = "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=\"utf-8\"\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $this->body . "\r\n";

            foreach ($this->attachments as $attachment) {
                $file_name = basename($attachment);
                $file_content = chunk_split(base64_encode(file_get_contents($attachment)));
                $message .= "--{$boundary}\r\n";
                $message .= "Content-Type: application/octet-stream; name=\"{$file_name}\"\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n";
                $message .= "Content-Disposition: attachment; filename=\"{$file_name}\"\r\n\r\n";
                $message .= $file_content . "\r\n";
            }

            $message .= "--{$boundary}--";
            $data = $fullHeaders . "\r\n\r\n" . $message . "\r\n.";
        }
        
        if (!$this->smtpCommand($data, 250)) return false;
        
        $this->smtpCommand("QUIT", 221);

        return true;
    }

    private function smtpConnect()
    {
        $host = $this->smtp_host;
        if ($this->smtp_encryption === 'ssl') {
            $host = 'ssl://' . $host;
        }

        $this->smtp_connection = @fsockopen($host, $this->smtp_port, $errno, $errstr, 30);

        if (!is_resource($this->smtp_connection)) {
            $this->error = "{$errno} - {$errstr}";
            error_log("GnzMailer SMTP Error: " . $this->error);
            return false;
        }

        return $this->smtpGetResponse(220);
    }

    private function smtpCommand($command, $expected_code)
    {
        if (!is_resource($this->smtp_connection)) {
             $this->error = "No connection available.";
             error_log("GnzMailer SMTP Error: " . $this->error);
             return false;
        }
        fputs($this->smtp_connection, $command . "\r\n");
        return $this->smtpGetResponse($expected_code);
    }

    private function smtpGetResponse($expected_code)
    {
        $response = '';
        while ($str = fgets($this->smtp_connection, 512)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);
        if ($code !== $expected_code) {
            $this->error = "Expected code {$expected_code}, got {$code}. Response: {$response}";
            error_log("GnzMailer SMTP Error: " . $this->error);
            return false;
        }
        return true;
    }
}
