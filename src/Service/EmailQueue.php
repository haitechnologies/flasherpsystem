<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Container;
use App\Core\Database;
use App\Core\DB;
use App\Core\ErrorCapture;
use Throwable;

class EmailQueue
{
    private Database $db;
    private string $tableName;

    public function __construct(?Database $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            try {
                $container = Container::getInstance();
                if ($container->has(Database::class)) {
                    $this->db = $container->get(Database::class);
                } else {
                    $this->db = new Database();
                }
            } catch (Throwable $e) {
                $this->db = new Database();
            }
        }

        $this->tableName = class_exists('DB') && defined('DB::EMAIL_QUEUE') ? (string)constant('DB::EMAIL_QUEUE') : 'erp_email_queue';
    }

    /**
     * Add email to queue
     *
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param array $headers
     * @param int $priority (1-high, 2-medium, 3-low)
     * @return int|bool Queue ID or false
     */
    public function enqueue(string $to, string $subject, string $body, array $headers = [], int $priority = 2): int|bool
    {
        $headersJson = json_encode($headers);
        $maxRetries = 3;
        $status = 'pending';

        $sql = "INSERT INTO `" . $this->tableName . "` 
                (recipient_email, recipient, subject, body, headers, priority, status, max_retries, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        try {
            return (int)$this->db->insert($sql, [$to, $to, $subject, $body, $headersJson, $priority, $status, $maxRetries]);
        } catch (Throwable $e) {
            ErrorCapture::record("Failed to insert into email queue: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process pending emails in queue
     *
     * @param int $limit Maximum emails to process
     * @return int Number of emails sent
     */
    public function processPending(int $limit = 10): int
    {
        $status = 'pending';

        $sql = "SELECT id, recipient, subject, body, headers, retries 
                FROM `" . $this->tableName . "` 
                WHERE status = ? 
                ORDER BY priority ASC, created_at ASC 
                LIMIT ?";

        $sent = 0;

        try {
            $rows = $this->db->fetchAll($sql, [$status, $limit]);
            foreach ($rows as $row) {
                $headers = json_decode($row['headers'], true) ?: [];
                if ($this->sendEmail($row['recipient'], $row['subject'], $row['body'], $headers)) {
                    $this->markAsSent((int)$row['id']);
                    $sent++;
                } else {
                    $this->incrementRetry((int)$row['id']);
                }
            }
        } catch (Throwable $e) {
            ErrorCapture::record("Failed processing pending email queue: " . $e->getMessage());
        }

        return $sent;
    }

    /**
     * Send email using SMTP (via SMTPMailer class)
     */
    private function sendEmail(string $to, string $subject, string $body, array $headers = []): bool
    {
        try {
            if (class_exists(SMTPMailer::class)) {
                $mailer = new SMTPMailer();
                return $mailer->send($to, $subject, $body, $headers);
            }
            if (class_exists('SMTPMailer')) {
                $class = 'SMTPMailer';
                $mailer = new $class();
                return $mailer->send($to, $subject, $body, $headers);
            }
            ErrorCapture::record("[EmailQueue] SMTPMailer not found, fallback is disabled. Email not sent.");
            return false;
        } catch (Throwable $e) {
            ErrorCapture::record("[EmailQueue] Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark email as successfully sent
     */
    private function markAsSent(int $queueId): bool
    {
        $status = 'sent';
        $sql = "UPDATE `" . $this->tableName . "` 
                SET status = ?, sent_at = NOW() 
                WHERE id = ?";

        try {
            $this->db->execute($sql, [$status, $queueId]);
            return true;
        } catch (Throwable $e) {
            ErrorCapture::record("Failed to mark email as sent: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Increment retry count for failed email
     */
    private function incrementRetry(int $queueId): bool
    {
        $sql = "UPDATE `" . $this->tableName . "` 
                SET retries = retries + 1, 
                    updated_at = NOW(),
                    status = IF(retries >= max_retries - 1, 'failed', 'pending')
                WHERE id = ?";

        try {
            $this->db->execute($sql, [$queueId]);
            return true;
        } catch (Throwable $e) {
            ErrorCapture::record("Failed to increment retry: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get queue statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(IF(status = 'pending', 1, 0)) as pending,
                    SUM(IF(status = 'sent', 1, 0)) as sent,
                    SUM(IF(status = 'failed', 1, 0)) as failed
                 FROM `" . $this->tableName . "`";

        try {
            return $this->db->fetchOne($sql) ?: [];
        } catch (Throwable $e) {
            ErrorCapture::record("Failed to get email queue stats: " . $e->getMessage());
            return [];
        }
    }
}
