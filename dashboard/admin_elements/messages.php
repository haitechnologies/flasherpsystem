<?php

/**
 * Standardized Success/Error/Info Toast Alerts (Centralized Queue)
 *
 * Design:
 * - Floating glassmorphic notifications at top-right of page.
 * - Pulls messages dynamically from App\Core\FlashMessage queue.
 * - Preserves backward compatibility for legacy session/request variables.
 */

if (!function_exists('dashboard_render_messages')) {
    function dashboard_render_messages()
    {
        // Start session if not started to check session flash messages
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $messages = [];

        // 1. Fetch from namespaced Centralized Flash Message Queue
        if (class_exists('App\Core\FlashMessage')) {
            $messages = \App\Core\FlashMessage::all();
        }

        // 2. Fetch from legacy session variables (compatibility)
        if (isset($_SESSION['success_message']) && trim((string)$_SESSION['success_message']) !== '') {
            $messages[] = ['type' => 'success', 'message' => trim((string)$_SESSION['success_message'])];
        }
        if (isset($_SESSION['error_message']) && trim((string)$_SESSION['error_message']) !== '') {
            $messages[] = ['type' => 'danger', 'message' => trim((string)$_SESSION['error_message'])];
        }
        if (isset($_SESSION['info_message']) && trim((string)$_SESSION['info_message']) !== '') {
            $messages[] = ['type' => 'info', 'message' => trim((string)$_SESSION['info_message'])];
        }

        // 3. Fetch from request query parameters (compatibility)
        if (isset($_REQUEST['success_message']) && trim((string)$_REQUEST['success_message']) !== '') {
            $messages[] = ['type' => 'success', 'message' => trim((string)$_REQUEST['success_message'])];
        }
        if (isset($_REQUEST['error_message']) && trim((string)$_REQUEST['error_message']) !== '') {
            $messages[] = ['type' => 'danger', 'message' => trim((string)$_REQUEST['error_message'])];
        }
        if (isset($_REQUEST['info_message']) && trim((string)$_REQUEST['info_message']) !== '') {
            $messages[] = ['type' => 'info', 'message' => trim((string)$_REQUEST['info_message'])];
        }

        // 4. Fetch from global variables (compatibility)
        if (isset($GLOBALS['success_message']) && trim((string)$GLOBALS['success_message']) !== '') {
            $messages[] = ['type' => 'success', 'message' => trim((string)$GLOBALS['success_message'])];
        }
        if (isset($GLOBALS['error_message']) && trim((string)$GLOBALS['error_message']) !== '') {
            $messages[] = ['type' => 'danger', 'message' => trim((string)$GLOBALS['error_message'])];
        }
        if (isset($GLOBALS['info_message']) && trim((string)$GLOBALS['info_message']) !== '') {
            $messages[] = ['type' => 'info', 'message' => trim((string)$GLOBALS['info_message'])];
        }

        if (empty($messages)) {
            return;
        }

        if (!isset($GLOBALS['__dashboard_rendered_message_keys']) || !is_array($GLOBALS['__dashboard_rendered_message_keys'])) {
            $GLOBALS['__dashboard_rendered_message_keys'] = [];
        }

        $hasOutput = false;

        // Configuration mapping for rendering
        $config = [
            'success' => [
                'title' => 'Success',
                'icon' => 'ph-check-circle',
                'class' => 'toast-alert-success'
            ],
            'danger' => [
                'title' => 'Error',
                'icon' => 'ph-warning-circle',
                'class' => 'toast-alert-danger'
            ],
            'warning' => [
                'title' => 'Warning',
                'icon' => 'ph-warning-circle',
                'class' => 'toast-alert-warning'
            ],
            'info' => [
                'title' => 'Information',
                'icon' => 'ph-info',
                'class' => 'toast-alert-info'
            ]
        ];

        foreach ($messages as $msg) {
            $type = strtolower(trim((string)$msg['type']));
            // Translate legacy type names
            if ($type === 'error') {
                $type = 'danger';
            }
            if (!isset($config[$type])) {
                $type = 'info';
            }

            $messageText = trim(strip_tags((string)$msg['message']));
            if ($messageText === '') {
                continue;
            }

            // Deduplicate
            $key = $type . '|' . $messageText;
            if (isset($GLOBALS['__dashboard_rendered_message_keys'][$key])) {
                continue;
            }
            $GLOBALS['__dashboard_rendered_message_keys'][$key] = true;

            if (!$hasOutput) {
                // Output styling once per page
                echo '<style>
                    .toast-alerts-container {
                        position: fixed;
                        top: 20px;
                        left: 50%;
                        transform: translateX(-50%);
                        z-index: 10050;
                        display: flex;
                        flex-direction: column;
                        gap: 12px;
                        max-width: 480px;
                        width: calc(100% - 40px);
                        pointer-events: none;
                    }
                    .toast-alert {
                        pointer-events: auto;
                        display: flex;
                        align-items: flex-start;
                        gap: 12px;
                        padding: 14px 18px;
                        border-radius: 12px;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                        background: rgba(255, 255, 255, 0.85);
                        backdrop-filter: blur(12px) saturate(180%);
                        -webkit-backdrop-filter: blur(12px) saturate(180%);
                        border: 1px solid rgba(255, 255, 255, 0.4);
                        transform: translateX(120%);
                        opacity: 0;
                        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s ease;
                    }
                    .toast-alert.show {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    .toast-alert.fade-out {
                        transform: translateY(-20px) scale(0.9);
                        opacity: 0;
                        transition: transform 0.3s ease, opacity 0.3s ease;
                    }
                    .toast-alert-icon {
                        font-size: 1.25rem;
                        flex-shrink: 0;
                        margin-top: 2px;
                    }
                    .toast-alert-content {
                        flex-grow: 1;
                        line-height: 1.4;
                        font-size: 0.875rem;
                        color: #1e293b;
                    }
                    .toast-alert-title {
                        font-weight: 700;
                        margin-bottom: 2px;
                    }
                    .toast-alert-close {
                        background: none;
                        border: none;
                        padding: 4px;
                        font-size: 1rem;
                        cursor: pointer;
                        color: #94a3b8;
                        transition: color 0.2s ease;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        margin-top: -2px;
                        margin-right: -4px;
                    }
                    .toast-alert-close:hover {
                        color: #475569;
                    }
                    .toast-alert-success {
                        border-left: 4px solid #10b981;
                    }
                    .toast-alert-success .toast-alert-icon {
                        color: #10b981;
                    }
                    .toast-alert-danger {
                        border-left: 4px solid #ef4444;
                    }
                    .toast-alert-danger .toast-alert-icon {
                        color: #ef4444;
                    }
                    .toast-alert-warning {
                        border-left: 4px solid #f59e0b;
                    }
                    .toast-alert-warning .toast-alert-icon {
                        color: #f59e0b;
                    }
                    .toast-alert-info {
                        border-left: 4px solid #3b82f6;
                    }
                    .toast-alert-info .toast-alert-icon {
                        color: #3b82f6;
                    }
                </style>';
                echo '<div class="toast-alerts-container">';
                $hasOutput = true;
            }

            $cfg = $config[$type];
            echo '<div class="toast-alert ' . $cfg['class'] . '" role="alert">';
            echo '<i class="' . $cfg['icon'] . ' toast-alert-icon"></i>';
            echo '<div class="toast-alert-content">';
            echo '<div class="toast-alert-title">' . $cfg['title'] . '</div>';
            echo htmlspecialchars($messageText, ENT_QUOTES, 'UTF-8');
            echo '</div>';
            echo '<button type="button" class="toast-alert-close" aria-label="Close">&times;</button>';
            echo '</div>';
        }

        if ($hasOutput) {
            echo '</div>'; // close toast-alerts-container
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    const toasts = document.querySelectorAll(".toast-alert");
                    toasts.forEach(function(toast) {
                        setTimeout(function() {
                            toast.classList.add("show");
                        }, 100);

                        const dismissTimeout = setTimeout(function() {
                            dismissToast(toast);
                        }, 4500);

                        const closeBtn = toast.querySelector(".toast-alert-close");
                        if (closeBtn) {
                            closeBtn.addEventListener("click", function() {
                                clearTimeout(dismissTimeout);
                                dismissToast(toast);
                            });
                        }
                    });

                    function dismissToast(toast) {
                        toast.classList.remove("show");
                        toast.classList.add("fade-out");
                        setTimeout(function() {
                            toast.remove();
                        }, 400);
                    }
                });
            </script>';
        }

        // Clean up session and global variables to prevent repeat rendering
        $GLOBALS['success_message'] = '';
        $GLOBALS['error_message'] = '';
        $GLOBALS['info_message'] = '';
        unset($_SESSION['success_message'], $_SESSION['error_message'], $_SESSION['info_message']);
        unset($_REQUEST['success_message'], $_REQUEST['error_message'], $_REQUEST['info_message']);
        unset($_GET['success_message'], $_GET['error_message'], $_GET['info_message']);
    }
}

dashboard_render_messages();
