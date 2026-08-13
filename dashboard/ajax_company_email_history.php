<?php

use App\Core\DB;
/**
 * AJAX Company Email History Endpoint
 *
 * Returns latest sent email history rows for a company from email_history table.
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

if (!granted_('view', 'companies')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$companyId = (int)($_POST['company_id'] ?? 0);
if ($companyId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid company ID']);
    exit;
}

try {
    $sql = "SELECT
                eh.id,
                eh.recipient_email,
                eh.subject,
                eh.sent_at,
                eh.sent_by,
                COALESCE(u.full_name, '') AS sent_by_name
            FROM `" . DB::EMAIL_HISTORY . "` eh
            LEFT JOIN `" . DB::USERS . "` u ON u.id = eh.sent_by
            WHERE eh.module = ?
              AND eh.related_id = ?
            ORDER BY eh.sent_at DESC, eh.id DESC
            LIMIT 10";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $mysqli->error);
    }

    $module = 'companies';
    $stmt->bind_param('si', $module, $companyId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'recipient_email' => (string)($row['recipient_email'] ?? ''),
            'subject' => (string)($row['subject'] ?? ''),
            'sent_at' => (string)($row['sent_at'] ?? ''),
            'sent_by' => (int)($row['sent_by'] ?? 0),
            'sent_by_name' => (string)($row['sent_by_name'] ?? ''),
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'data' => $rows,
    ]);
} catch (Throwable $e) {
    log_error('Company email history endpoint error: ' . $e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'email', 'action' => 'company_email_history']);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
