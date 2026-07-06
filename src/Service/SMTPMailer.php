<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Container;
use App\Core\Database;
use App\Core\DB;
use Throwable;

class SMTPMailer
{
    private string $provider;
    private string $from_address;
    private string $from_name;
    private string $smtp_host;
    private int $smtp_port;
    private string $smtp_encryption;
    private string $smtp_username;
    private string $smtp_password;
    private ?EmailProviderService $providerManager = null;
    private ?Database $db = null;
    private ?array $activeProvider = null;
    private string $lastError = '';
    private string $lastSMTPCode = '';
    private string $lastSMTPResponse = '';
    private bool $skipHistoryLog = false;

    private static function _log(string $message): void
    {
        error_log("[SMTPMailer] " . $message);
    }

    public function __construct(?Database $db = null)
    {
        $this->provider = 'database';
        $this->from_address = '';
        $this->from_name = '';
        $this->smtp_host = '';
        $this->smtp_port = 0;
        $this->smtp_encryption = 'tls';
        $this->smtp_username = '';
        $this->smtp_password = '';

        if ($db !== null) {
            $this->db = $db;
        } else {
            try {
                $container = Container::getInstance();
                if ($container->has(Database::class)) {
                    $this->db = $container->get(Database::class);
                }
            } catch (Throwable $e) {
            }
        }

        $this->providerManager = new EmailProviderService($this->db);
    }

    /**
     * Send email via configured provider
     */
    public function send(string $to, string $subject, string $body, array $headers = []): bool
    {
        try {
            $this->lastError = '';
            $this->lastSMTPCode = '';
            $this->lastSMTPResponse = '';
            $this->skipHistoryLog = !empty($headers['skip_history_log']);

            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $this->lastError = "Invalid recipient email: $to";
                self::_log("[SMTPMailer] " . $this->lastError);
                return false;
            }

            if (!$this->resolveProviderConfig($headers)) {
                return false;
            }

            $providerRef = !empty($this->activeProvider['id']) ? ('#' . $this->activeProvider['id']) : $this->from_address;
            self::_log("[SMTPMailer] Sending email to $to via database provider {$providerRef}");

            return $this->sendViaSMTP($to, $subject, $body, $headers);
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            self::_log("[SMTPMailer] Exception: " . $this->lastError);
            return false;
        }
    }

    private function resolveProviderConfig(array $headers = []): bool
    {
        if (!$this->providerManager) {
            $this->lastError = 'EmailProviderService is unavailable. DB provider resolution failed.';
            self::_log('[SMTPMailer] ' . $this->lastError);
            return false;
        }

        $provider = null;
        $providerId = (int)($headers['provider_id'] ?? 0);
        $fromHeader = trim((string)($headers['from'] ?? $headers['From'] ?? ''));

        if ($providerId > 0) {
            $provider = $this->providerManager->getById($providerId);
        }

        if (!$provider && $fromHeader !== '') {
            $provider = $this->providerManager->getByEmail($fromHeader);
        }

        if (!$provider) {
            $provider = $this->providerManager->getDefault();
        }

        if (!$provider) {
            $provider = $this->providerManager->getAvailableWithQuota();
        }

        if (!$provider) {
            $this->lastError = 'No active email provider found in email_providers table.';
            self::_log('[SMTPMailer] ' . $this->lastError);
            return false;
        }

        $providerId = (int)($provider['id'] ?? 0);
        if ($providerId > 0 && $this->db !== null) {
            $dailyLimit = (int)($provider['daily_limit'] ?? 0);
            if ($dailyLimit <= 0) {
                $dailyLimit = 100;
            }

            try {
                $emailHistoryTable = class_exists('DB') && defined('DB::EMAIL_HISTORY') ? (string)constant('DB::EMAIL_HISTORY') : 'erp_email_history';
                $row = $this->db->fetchOne(
                    "SELECT COUNT(*) AS cnt FROM `" . $emailHistoryTable . "`
                     WHERE provider_id = ? AND status = 'sent' AND DATE(COALESCE(sent_at, created_at)) = CURDATE()",
                    [$providerId]
                );
                $sentToday = (int)($row['cnt'] ?? 0);

                if ($sentToday >= $dailyLimit) {
                    self::_log("[SMTPMailer] Provider #$providerId has reached daily limit ($sentToday/$dailyLimit). Switching to next available provider.");
                    $fallback = $this->providerManager->getAvailableWithQuota([$providerId]);
                    if (!$fallback) {
                        $this->lastError = "All email providers have reached their daily sending limit for today.";
                        self::_log('[SMTPMailer] ' . $this->lastError);
                        return false;
                    }
                    $provider = $fallback;
                    self::_log('[SMTPMailer] Switched to provider #' . (int)$provider['id'] . ' (' . ($provider['email'] ?? '') . ').');
                }
            } catch (Throwable $e) {
                self::_log('[SMTPMailer] Quota check failed: ' . $e->getMessage());
            }
        }

        $this->activeProvider = $provider;
        $this->from_address = trim((string)($provider['email'] ?? ''));
        $this->from_name = trim((string)($headers['from_name'] ?? $provider['provider_name'] ?? (defined('APP_NAME') ? APP_NAME : 'HAIZON')));
        $this->smtp_host = trim((string)($provider['smtp_host'] ?? ''));
        $this->smtp_port = (int)($provider['smtp_port'] ?? 0);
        $this->smtp_encryption = strtolower(trim((string)($provider['email_encryption'] ?? 'tls')));
        $this->smtp_username = trim((string)($provider['smtp_username'] ?? $this->from_address));
        $this->smtp_password = (string)($provider['smtp_password_decrypted'] ?? $provider['smtp_password'] ?? '');

        if (stripos($this->smtp_host, 'titan.email') !== false) {
            $this->smtp_port = 465;
            $this->smtp_encryption = 'ssl';
        }

        if (
            $this->from_address === '' ||
            $this->smtp_host === '' ||
            $this->smtp_port <= 0 ||
            $this->smtp_username === '' ||
            $this->smtp_password === ''
        ) {
            $this->lastError = 'Selected email provider has incomplete SMTP configuration.';
            self::_log('[SMTPMailer] ' . $this->lastError);
            return false;
        }

        return true;
    }

    private function sendViaSMTP(string $to, string $subject, string $body, array $headers = []): bool
    {
        $mail_headers = "Date: " . date('r') . "\r\n";
        $mail_headers .= "MIME-Version: 1.0\r\n";
        $app_name_slug = defined('APP_NAME') ? str_replace(' ', '-', APP_NAME) : 'HAIZON';
        $mail_headers .= "X-Mailer: {$app_name_slug}-SMTP-Mailer/2.0\r\n";
        $mail_headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        if (!empty($headers['Message-ID'])) {
            $mail_headers .= "Message-ID: {$headers['Message-ID']}\r\n";
        } else {
            $domain = preg_replace('/^.*@/', '', (string)$this->from_address);
            if ($domain === '' || strpos($domain, '.') === false) {
                $domain = defined('APP_DOMAIN') ? APP_DOMAIN : 'flasherpsystem.com';
            }
            $mail_headers .= "Message-ID: <" . uniqid('hai-', true) . "@{$domain}>\r\n";
        }

        $replyTo = !empty($headers['Reply-To']) ? $headers['Reply-To'] : (defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'support@flasherpsystem.com');
        $mail_headers .= "Reply-To: {$replyTo}\r\n";
        if (!empty($headers['CC'])) {
            $mail_headers .= "CC: {$headers['CC']}\r\n";
        }
        if (!empty($headers['BCC'])) {
            $mail_headers .= "BCC: {$headers['BCC']}\r\n";
        }
        foreach (['Message-ID', 'In-Reply-To', 'References'] as $_hdr) {
            if (!empty($headers[$_hdr])) {
                $mail_headers .= "$_hdr: {$headers[$_hdr]}\r\n";
            }
        }

        if ($this->smtp_username !== '' && $this->smtp_password !== '') {
            return $this->sendViaSMTPConnection($to, $subject, $body, $mail_headers);
        }

        $this->lastError = "No SMTP credentials provided. Email not sent. (host: {$this->smtp_host}, user: {$this->smtp_username})";
        self::_log("[SMTPMailer] " . $this->lastError);
        return false;
    }

    private function readSMTPResponse($socket): string
    {
        $response = '';
        $responseCode = '';

        while (true) {
            $line = fgets($socket, 1024);
            if ($line === false || empty($line)) {
                break;
            }

            $response .= $line;

            if ($responseCode === '') {
                preg_match('/^(\d{3})/', $line, $matches);
                $responseCode = $matches[1] ?? '';
            }

            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }

        $response = trim($response);
        $this->lastSMTPResponse = $response;
        $this->lastSMTPCode = $responseCode;

        return $responseCode !== '' ? substr($response, 0, 3) : $response;
    }

    private function sendViaSMTPConnection(string $to, string $subject, string $body, string $headers): bool
    {
        try {
            $hostname = ($this->smtp_encryption === 'ssl')
                ? "ssl://{$this->smtp_host}"
                : "tcp://{$this->smtp_host}";

            $socket = @fsockopen(
                $hostname,
                (int)$this->smtp_port,
                $errno,
                $errstr,
                10.0
            );

            if (!$socket) {
                $this->lastError = "SMTP connection failed: [$errno] $errstr";
                self::_log("[SMTPMailer] " . $this->lastError);
                return false;
            }

            stream_set_timeout($socket, 10);

            $code = $this->readSMTPResponse($socket);
            if ($code !== '220') {
                fclose($socket);
                $this->lastError = "Invalid SMTP greeting: $code";
                self::_log("[SMTPMailer] " . $this->lastError);
                return false;
            }

            $ehlo_domain = defined('APP_DOMAIN') ? APP_DOMAIN : 'haizon.local';
            fwrite($socket, "EHLO {$ehlo_domain}\r\n");
            $code = $this->readSMTPResponse($socket);
            if (strpos($code, '2') === false) {
                fclose($socket);
                $this->lastError = "EHLO failed: $code";
                self::_log("[SMTPMailer] " . $this->lastError);
                return false;
            }

            if ($this->smtp_encryption === 'tls') {
                fwrite($socket, "STARTTLS\r\n");
                $code = $this->readSMTPResponse($socket);

                if (strpos($code, '220') === false) {
                    fclose($socket);
                    $this->lastError = "STARTTLS failed: $code";
                    self::_log("[SMTPMailer] " . $this->lastError);
                    return false;
                }

                stream_context_set_option($socket, 'ssl', 'verify_peer', false);
                stream_context_set_option($socket, 'ssl', 'verify_peer_name', false);
                $tlsEnabled = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($tlsEnabled !== true) {
                    fclose($socket);
                    $this->lastError = "TLS negotiation failed";
                    self::_log("[SMTPMailer] " . $this->lastError);
                    return false;
                }

                $ehlo_domain = defined('APP_DOMAIN') ? APP_DOMAIN : 'haizon.local';
                fwrite($socket, "EHLO {$ehlo_domain}\r\n");
                $code = $this->readSMTPResponse($socket);
                if (strpos($code, '2') === false) {
                    fclose($socket);
                    $this->lastError = "EHLO after STARTTLS failed: $code";
                    self::_log("[SMTPMailer] " . $this->lastError);
                    return false;
                }
            }

            fwrite($socket, "AUTH LOGIN\r\n");
            $code = $this->readSMTPResponse($socket);

            if ($code !== '334') {
                fclose($socket);
                $this->lastError = "AUTH LOGIN failed: $code";
                self::_log("[SMTPMailer] " . $this->lastError);
                return false;
            }

            fwrite($socket, base64_encode($this->smtp_username) . "\r\n");
            $code = $this->readSMTPResponse($socket);

            if ($code !== '334') {
                fclose($socket);
                $this->lastError = "Username submission failed: $code";
                self::_log("[SMTPMailer] " . $this->lastError);
                return false;
            }

            fwrite($socket, base64_encode($this->smtp_password) . "\r\n");
            $code = $this->readSMTPResponse($socket);

            if ($code !== '235') {
                fclose($socket);
                $this->lastError = "Authentication failed: $code";
                self::_log("[SMTPMailer] " . $this->lastError);
                return false;
            }

            fwrite($socket, "MAIL FROM: <{$this->from_address}>\r\n");
            $code = $this->readSMTPResponse($socket);
            if (strpos($code, '2') === false) {
                fclose($socket);
                $this->lastError = "MAIL FROM failed: $code";
                self::_log("[SMTPMailer] " . $this->lastError);
                return false;
            }

            fwrite($socket, "RCPT TO: <$to>\r\n");
            $code = $this->readSMTPResponse($socket);
            if (strpos($code, '2') === false) {
                fclose($socket);
                $this->lastError = "RCPT TO failed: $code";
                self::_log("[SMTPMailer] " . $this->lastError);
                return false;
            }

            fwrite($socket, "DATA\r\n");
            $code = $this->readSMTPResponse($socket);
            if ($code !== '354') {
                fclose($socket);
                $this->lastError = "DATA failed: $code";
                self::_log("[SMTPMailer] " . $this->lastError);
                return false;
            }

            $message = "To: $to\r\n";
            $message .= "From: {$this->from_name} <{$this->from_address}>\r\n";
            $message .= "Subject: " . $this->encodeSubject($subject) . "\r\n";
            $message .= $headers;
            $message .= "\r\n" . $body;
            $message .= "\r\n.\r\n";

            fwrite($socket, $message);
            $code = $this->readSMTPResponse($socket);
            if (strpos($code, '2') === false) {
                fclose($socket);
                $this->lastError = "Message send failed: $code";
                self::_log("[SMTPMailer] " . $this->lastError);
                return false;
            }

            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            $success = strpos($code, '250') !== false;

            if ($success && !$this->skipHistoryLog) {
                $this->logToHistory($to, $subject);
            }

            return $success;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            self::_log("[SMTPMailer] SMTP connection error: " . $this->lastError);
            return false;
        }
    }

    private function encodeSubject(string $subject): string
    {
        if (preg_match('/[\x80-\xFF]/', $subject)) {
            return '=?UTF-8?B?' . base64_encode($subject) . '?=';
        }
        return $subject;
    }

    private function logToHistory(string $to, string $subject): void
    {
        if ($this->db === null) {
            return;
        }
        $providerId = isset($this->activeProvider['id']) ? (int)$this->activeProvider['id'] : null;
        $fromName = $this->from_name;
        $fromEmail = $this->from_address;
        $subjectEsc = substr($subject, 0, 500);
        $emailHistoryTable = class_exists('DB') && defined('DB::EMAIL_HISTORY') ? (string)constant('DB::EMAIL_HISTORY') : 'erp_email_history';

        try {
            $this->db->execute(
                "INSERT INTO `" . $emailHistoryTable . "`
                 (recipient_email, subject, provider_id, status, sent_at, from_name, from_email)
                 VALUES (?, ?, ?, 'sent', NOW(), ?, ?)",
                [$to, $subjectEsc, $providerId, $fromName, $fromEmail]
            );
        } catch (Throwable $e) {
            self::_log('[SMTPMailer] logToHistory failed: ' . $e->getMessage());
        }
    }

    /**
     * Test SMTP connectivity/auth for a provider configuration.
     */
    public function testConnection(string $host, int $port, string $username, string $password, string $encryption = 'tls'): array
    {
        $host = trim($host);
        $username = trim($username);
        $encryption = strtolower(trim($encryption));

        if ($host === '' || $port <= 0 || $username === '' || $password === '') {
            return ['success' => false, 'message' => 'Incomplete SMTP configuration'];
        }

        $hostname = ($encryption === 'ssl') ? "ssl://{$host}" : "tcp://{$host}";

        try {
            $socket = @fsockopen($hostname, $port, $errno, $errstr, 10.0);
            if (!$socket) {
                return ['success' => false, 'message' => "Connection failed: [$errno] $errstr"];
            }

            stream_set_timeout($socket, 10);

            $code = $this->readSMTPResponse($socket);
            if ($code !== '220') {
                fclose($socket);
                return ['success' => false, 'message' => "Invalid SMTP greeting: $code"];
            }

            $ehlo_domain = defined('APP_DOMAIN') ? APP_DOMAIN : 'haizon.local';
            fwrite($socket, "EHLO {$ehlo_domain}\r\n");
            $code = $this->readSMTPResponse($socket);
            if (strpos($code, '2') !== 0) {
                fclose($socket);
                return ['success' => false, 'message' => "EHLO failed: $code"];
            }

            if ($encryption === 'tls') {
                fwrite($socket, "STARTTLS\r\n");
                $code = $this->readSMTPResponse($socket);
                if (strpos($code, '220') !== 0) {
                    fclose($socket);
                    return ['success' => false, 'message' => "STARTTLS failed: $code"];
                }

                stream_context_set_option($socket, 'ssl', 'verify_peer', false);
                stream_context_set_option($socket, 'ssl', 'verify_peer_name', false);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

                $ehlo_domain = defined('APP_DOMAIN') ? APP_DOMAIN : 'haizon.local';
                fwrite($socket, "EHLO {$ehlo_domain}\r\n");
                $code = $this->readSMTPResponse($socket);
                if (strpos($code, '2') !== 0) {
                    fclose($socket);
                    return ['success' => false, 'message' => "EHLO after STARTTLS failed: $code"];
                }
            }

            fwrite($socket, "AUTH LOGIN\r\n");
            $code = $this->readSMTPResponse($socket);
            if ($code !== '334') {
                fclose($socket);
                return ['success' => false, 'message' => "AUTH LOGIN failed: $code"];
            }

            fwrite($socket, base64_encode($username) . "\r\n");
            $code = $this->readSMTPResponse($socket);
            if ($code !== '334') {
                fclose($socket);
                return ['success' => false, 'message' => "Username rejected: $code"];
            }

            fwrite($socket, base64_encode($password) . "\r\n");
            $code = $this->readSMTPResponse($socket);
            if ($code !== '235') {
                fclose($socket);
                return ['success' => false, 'message' => "Authentication failed: $code"];
            }

            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            return ['success' => true, 'message' => 'SMTP connection and authentication successful'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getConfig(): array
    {
        return [
            'provider' => $this->provider,
            'from_address' => $this->from_address,
            'from_name' => $this->from_name,
            'provider_id' => $this->activeProvider['id'] ?? null
        ];
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getLastSMTPCode(): string
    {
        return $this->lastSMTPCode;
    }

    public function getLastSMTPResponse(): string
    {
        return $this->lastSMTPResponse;
    }
}
