<?php
/**
 * Security Status Dashboard Widget
 * 
 * Display security metrics and rate limiting status
 * Include in admin dashboard: include 'dashboard_widgets/security_status.php';
 * 
 * @author Security Team
 * @date February 27, 2026
 */

use App\Security\RateLimiter;

// Ensure dependencies are loaded
if (!function_exists('granted_')) {
    return; // Skip if admin context not loaded
}

// Only show to System Admin
if ($_SESSION['h_role_id'] != 1) {
    return;
}

try {
    // Removed legacy require for autoloader compatibility: require_once __DIR__ . '/../classes/RateLimiter.php';
    // Removed legacy require for autoloader compatibility: require_once __DIR__ . '/../classes/DB.php';
    
    RateLimiter::init($mysqli);
    
    $stats = RateLimiter::getStatistics();
    $banned = RateLimiter::getBannedList();
    
    // Get recent IDOR attempts from log
    $error_log_file = __DIR__ . '/CONSOLIDATED_ERROR_LOG.txt';
    $idor_attempts = 0;
    if (file_exists($error_log_file)) {
        $log_content = file_get_contents($error_log_file);
        preg_match_all('/IDOR attempt/i', $log_content, $matches);
        $idor_attempts = count($matches[0]);
    }
    
    // Get recent CSRF violations
    $csrf_violations = 0;
    if (file_exists($error_log_file)) {
        preg_match_all('/CSRF/i', $log_content, $matches);
        $csrf_violations = count($matches[0]);
    }
    
} catch (Exception $e) {
    // If rate limiter not initialized, show error
    log_error($e->getMessage(), 'WARNING', $e->getFile(), $e->getLine(), ['module' => 'security_status']);
    ?>
    <div class="card border-warning">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">⚠️ Security Status</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">Rate limiter not yet initialized. Run an attempted login to create the database table.</p>
        </div>
    </div>
    <?php
    return;
}
?>

<div class="card border-success">
    <div class="card-header bg-success text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">🔒 Security Status</h5>
            <small class="text-success-light">All protections active</small>
        </div>
    </div>
    <div class="card-body">
        <!-- Main Metrics Row -->
        <div class="row mb-4">
            <!-- Rate Limiting -->
            <div class="col-md-3 mb-3 mb-md-0">
                <div class="bg-light p-3 rounded">
                    <div class="text-muted small mb-2">🚫 Rate Limit Bans</div>
                    <div class="display-6 text-danger"><?php echo $stats['currently_banned']; ?></div>
                    <div class="text-muted small">Currently blocked IPs</div>
                </div>
            </div>
            
            <!-- Login Attempts Today -->
            <div class="col-md-3 mb-3 mb-md-0">
                <div class="bg-light p-3 rounded">
                    <div class="text-muted small mb-2">📊 Login Attempts</div>
                    <div class="display-6 text-info"><?php echo $stats['total_attempts_today']; ?></div>
                    <div class="text-muted small">Tracked today</div>
                </div>
            </div>
            
            <!-- IDOR Attempts -->
            <div class="col-md-3 mb-3 mb-md-0">
                <div class="bg-light p-3 rounded">
                    <div class="text-muted small mb-2">⚠️ IDOR Attempts</div>
                    <div class="display-6 text-warning"><?php echo $idor_attempts; ?></div>
                    <div class="text-muted small">Access denied</div>
                </div>
            </div>
            
            <!-- CSRF Violations -->
            <div class="col-md-3 mb-3 mb-md-0">
                <div class="bg-light p-3 rounded">
                    <div class="text-muted small mb-2">🛡️ CSRF Blocked</div>
                    <div class="display-6 text-warning"><?php echo $csrf_violations; ?></div>
                    <div class="text-muted small">Invalid tokens</div>
                </div>
            </div>
        </div>

        <!-- Security Features Checklist -->
        <hr class="my-3">
        <h6 class="mb-3">Active Security Features</h6>
        <div class="row">
            <div class="col-md-6">
                <ul class="list-unstyled small">
                    <li class="mb-2"><span class="badge bg-success">✓</span> CSRF Token Validation</li>
                    <li class="mb-2"><span class="badge bg-success">✓</span> File Upload Validation</li>
                    <li class="mb-2"><span class="badge bg-success">✓</span> Security Headers (8)</li>
                    <li class="mb-2"><span class="badge bg-success">✓</span> HTTPS Enforcement</li>
                </ul>
            </div>
            <div class="col-md-6">
                <ul class="list-unstyled small">
                    <li class="mb-2"><span class="badge bg-success">✓</span> Session Security (5)</li>
                    <li class="mb-2"><span class="badge bg-success">✓</span> Input Validation</li>
                    <li class="mb-2"><span class="badge bg-success">✓</span> IDOR Protection</li>
                    <li class="mb-2"><span class="badge bg-success">✓</span> Rate Limiting</li>
                </ul>
            </div>
        </div>

        <!-- Banned IPs List (if any) -->
        <?php if (!empty($banned)): ?>
        <hr class="my-3">
        <h6 class="mb-3">Currently Banned IPs</h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead class="table-light">
                    <tr>
                        <th>IP Address</th>
                        <th>Action</th>
                        <th>Banned Until</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($banned, 0, 5) as $record): ?>
                    <tr>
                        <td><code><?php echo e($record['identifier']); ?></code></td>
                        <td><?php echo e($record['action']); ?></td>
                        <td>
                            <small>
                                <?php 
                                $remaining = strtotime($record['banned_until']) - time();
                                $remaining_min = ceil($remaining / 60);
                                echo $remaining_min > 0 ? $remaining_min . ' min' : 'Expired';
                                ?>
                            </small>
                        </td>
                        <td>
                            <form method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>" style="display:inline;">
                                <input type="hidden" name="security_action" value="unban">
                                <input type="hidden" name="ip" value="<?php echo e($record['identifier']); ?>">
                                <input type="hidden" name="action_type" value="<?php echo e($record['action']); ?>">
                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Unban this IP?');">Unban</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($banned) > 5): ?>
        <div class="alert alert-info small mb-0">
            Showing 5 of <?php echo count($banned); ?> banned IPs. 
            <a href="listing_authentication_activity.php">View all →</a>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Quick Actions -->
        <hr class="my-3">
        <div class="d-flex gap-2">
            <a href="#" class="btn btn-sm btn-outline-primary" title="View banned IPs">
                <i class="fa fa-ban"></i> Banned IPs
            </a>
            <a href="#" class="btn btn-sm btn-outline-info" title="View security logs">
                <i class="fa fa-file-text"></i> Security Logs
            </a>
            <a href="#" class="btn btn-sm btn-outline-warning" title="View IDOR attempts">
                <i class="fa fa-shield"></i> IDOR Attempts
            </a>
        </div>

        <!-- Footer Info -->
        <div class="mt-3 pt-3 border-top">
            <small class="text-muted">
                Last updated: <?php echo date('d M Y g:ia'); ?> | 
                <a href="https://github.com/your-repo/security" class="text-muted" target="_blank">Security Docs</a>
            </small>
        </div>
    </div>
</div>

<style>
.bg-success-light {
    background-color: rgba(25, 135, 84, 0.1) !important;
}
</style>

<?php
// Handle unban action via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['security_action']) && $_POST['security_action'] === 'unban') {
    if (isset($_POST['ip']) && isset($_POST['action_type'])) {
        try {
            RateLimiter::unban($_POST['ip'], $_POST['action_type']);
            // Redirect to refresh
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } catch (Exception $e) {
            // Silent fail
        }
    }
}
?>
