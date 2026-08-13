<?php

use App\Core\DB;
/**
 * AJAX Email History Details Endpoint
 *
 * Returns full email history details for modal display.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF token validation failed']);
    exit;
}

if (!granted_('view', 'email_history')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid email history ID']);
    exit;
}

try {
    $sql = "SELECT 
                eh.id,
                eh.user_id,
                eh.campaign_id,
                eh.recipient_email,
                eh.company_id,
                eh.provider_id,
                eh.status,
                eh.error_message,
                eh.sent_at,
                eh.delivered_at,
                eh.opened_at,
                eh.clicked_at,
                eh.message_id,
                eh.tracking_id,
                eh.subject,
                eh.body,
                eh.from_name,
                eh.from_email,
                eh.created_at,
                eh.updated_at,
                CASE
                    WHEN du.id IS NOT NULL THEN 'Dashboard'
                    ELSE 'Website'
                END AS source_label,
                '-' AS campaign_name,
                ep.provider_name,
                ep.email AS provider_email,
                u.full_name AS user_name,
                u.email AS user_email
            FROM `" . DB::EMAIL_HISTORY . "` eh
            LEFT JOIN `" . DB::USERS . "` du ON du.id = eh.user_id
            LEFT JOIN `" . DB::EMAIL_PROVIDERS . "` ep ON ep.id = eh.provider_id
            LEFT JOIN `" . DB::USERS . "` u ON u.id = eh.user_id
            WHERE eh.id = ?
            LIMIT 1";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $mysqli->error);
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Email history record not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int)($row['id'] ?? 0),
            'user_id' => (int)($row['user_id'] ?? 0),
            'user_name' => (string)($row['user_name'] ?? ''),
            'user_email' => (string)($row['user_email'] ?? ''),
            'campaign_id' => !empty($row['campaign_id']) ? (int)$row['campaign_id'] : null,
            'campaign_name' => (string)($row['campaign_name'] ?? ''),
            'recipient_email' => (string)($row['recipient_email'] ?? ''),
            'company_id' => !empty($row['company_id']) ? (int)$row['company_id'] : null,
            'provider_id' => !empty($row['provider_id']) ? (int)$row['provider_id'] : null,
            'provider_name' => (string)($row['provider_name'] ?? ''),
            'provider_email' => (string)($row['provider_email'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'source' => (string)($row['source_label'] ?? 'Website'),
            'error_message' => (string)($row['error_message'] ?? ''),
            'sent_at' => (string)($row['sent_at'] ?? ''),
            'delivered_at' => (string)($row['delivered_at'] ?? ''),
            'opened_at' => (string)($row['opened_at'] ?? ''),
            'clicked_at' => (string)($row['clicked_at'] ?? ''),
            'message_id' => (string)($row['message_id'] ?? ''),
            'tracking_id' => (string)($row['tracking_id'] ?? ''),
            'subject' => (string)($row['subject'] ?? ''),
            'body' => (string)($row['body'] ?? ''),
            'from_name' => (string)($row['from_name'] ?? ''),
            'from_email' => (string)($row['from_email'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ]
    ]);
} catch (Exception $e) {
    log_error('Email history details endpoint error: ' . $e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'email', 'action' => 'email_history_details']);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
