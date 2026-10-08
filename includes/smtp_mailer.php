<?php
/**
 * Imperial Hospital - Production-Grade SMTP Mailer
 * Connects directly via secure sockets (TLS/SSL) to SMTP servers (e.g. Gmail)
 * Loads credentials securely from .env file.
 */

class SimpleSMTPMailer {
    private string $host;
    private int $port;
    private string $secure;
    private string $username;
    private string $password;
    private string $fromName;
    private string $fromEmail;
    private string $lastError = '';

    public function __construct(?string $envPath = null) {
        $this->loadEnv($envPath ?? dirname(__DIR__) . '/.env');
        
        $this->host = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $this->port = (int)($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587);
        $this->secure = strtolower($_ENV['SMTP_SECURE'] ?? getenv('SMTP_SECURE') ?: 'tls');
        $this->username = $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?: '';
        $this->password = str_replace(' ', '', $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '');
        $this->fromName = $_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'Imperial Hospital';
        $this->fromEmail = $_ENV['SMTP_FROM_EMAIL'] ?? getenv('SMTP_FROM_EMAIL') ?: $this->username;
    }

    /**
     * Parse .env file without external dependencies
     */
    private function loadEnv(string $filePath): void {
        if (!file_exists($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Strip quotes if present
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }

    public function getLastError(): string {
        return $this->lastError;
    }

    /**
     * Send email via SMTP
     */
    public function send(string $to, string $subject, string $htmlBody, ?string $replyTo = null): bool {
        $socket = null;
        try {
            $remoteHost = ($this->secure === 'ssl') ? 'ssl://' . $this->host : $this->host;
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);

            $socket = @stream_socket_client(
                $remoteHost . ':' . $this->port,
                $errno,
                $errstr,
                15,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                throw new Exception("Connection failed: $errstr ($errno)");
            }

            stream_set_timeout($socket, 15);

            // Read greeting
            $res = $this->readResponse($socket);
            if (!str_starts_with($res, '220')) {
                throw new Exception("Invalid greeting: $res");
            }

            // EHLO
            $this->sendCommand($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            $res = $this->readResponse($socket);

            // STARTTLS if TLS
            if ($this->secure === 'tls') {
                $this->sendCommand($socket, 'STARTTLS');
                $res = $this->readResponse($socket);
                if (!str_starts_with($res, '220')) {
                    throw new Exception("STARTTLS failed: $res");
                }

                $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                if (!$crypto) {
                    throw new Exception("TLS negotiation failed");
                }

                // Re-issue EHLO after STARTTLS
                $this->sendCommand($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
                $res = $this->readResponse($socket);
            }

            // Authenticate
            if (!empty($this->username) && !empty($this->password)) {
                $this->sendCommand($socket, 'AUTH LOGIN');
                $res = $this->readResponse($socket);
                if (!str_starts_with($res, '334')) {
                    throw new Exception("AUTH LOGIN rejected: $res");
                }

                $this->sendCommand($socket, base64_encode($this->username));
                $res = $this->readResponse($socket);
                if (!str_starts_with($res, '334')) {
                    throw new Exception("Username rejected: $res");
                }

                $this->sendCommand($socket, base64_encode($this->password));
                $res = $this->readResponse($socket);
                if (!str_starts_with($res, '235')) {
                    throw new Exception("Password rejected: $res");
                }
            }

            // MAIL FROM
            $this->sendCommand($socket, 'MAIL FROM:<' . $this->fromEmail . '>');
            $res = $this->readResponse($socket);
            if (!str_starts_with($res, '250')) {
                throw new Exception("MAIL FROM rejected: $res");
            }

            // RCPT TO
            $this->sendCommand($socket, 'RCPT TO:<' . $to . '>');
            $res = $this->readResponse($socket);
            if (!str_starts_with($res, '250')) {
                throw new Exception("RCPT TO rejected: $res");
            }

            // DATA
            $this->sendCommand($socket, 'DATA');
            $res = $this->readResponse($socket);
            if (!str_starts_with($res, '354')) {
                throw new Exception("DATA command rejected: $res");
            }

            // Build MIME Message
            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $encodedFromName = '=?UTF-8?B?' . base64_encode($this->fromName) . '?=';
            
            $headers = [];
            $headers[] = "From: $encodedFromName <{$this->fromEmail}>";
            $headers[] = "To: <$to>";
            if (!empty($replyTo) && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $headers[] = "Reply-To: <$replyTo>";
            }
            $headers[] = "Subject: $encodedSubject";
            $headers[] = "Date: " . date('r');
            $headers[] = "Message-ID: <" . md5(uniqid(microtime(), true)) . "@" . ($this->host ?: 'imperialhospital') . ">";
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: text/html; charset=UTF-8";
            $headers[] = "Content-Transfer-Encoding: base64";
            $headers[] = "X-Mailer: ImperialHospital-SMTP";

            $messageData = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($htmlBody)) . "\r\n.";
            
            $this->sendCommand($socket, $messageData);
            $res = $this->readResponse($socket);
            if (!str_starts_with($res, '250')) {
                throw new Exception("Message submission rejected: $res");
            }

            // QUIT
            $this->sendCommand($socket, 'QUIT');
            fclose($socket);
            return true;

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            if ($socket && is_resource($socket)) {
                @fclose($socket);
            }
            return false;
        }
    }

    private function sendCommand($socket, string $command): void {
        fwrite($socket, $command . "\r\n");
    }

    private function readResponse($socket): string {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (preg_match('/^\d{3}\s/', $line)) {
                break;
            }
        }
        return trim($response);
    }
}
