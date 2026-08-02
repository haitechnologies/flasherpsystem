<?php
require_once dirname(__DIR__) . '/admin_elements/error_handler_init.php';

/**
 * BaseAPI.php - Abstract Base Class for All API Endpoints
 * 
 * Provides:
 * - Request validation (HTTP method, CSRF token)
 * - Uniform response format
 * - Error handling and logging
 * - Rate limiting hooks
 * 
 * @package API
 * @version 1.0.0
 */

abstract class BaseAPI {
    
    /**
     * @var mysqli Database connection
     */
    protected $mysqli;
    
    /**
     * @var array Standard response format
     */
    protected $response = [
        'status' => 'error',
        'data' => null,
        'message' => '',
        'timestamp' => null
    ];
    
    /**
     * @var int HTTP status code
     */
    protected $http_code = 400;
    
    /**
     * @var string Action name for logging
     */
    protected $action = '';
    
    /**
     * Constructor - Validate and initialize request
     */
    public function __construct() {
        global $mysqli;
        $this->mysqli = $mysqli;
        $this->response['timestamp'] = date('Y-m-d H:i:s');
        
        $this->validateRequest();
        $this->validateCSRF();
        $this->logRequest('INIT', $_POST);
    }
    
    /**
     * Validate HTTP request method
     * Only POST allowed for all API endpoints
     */
    protected function validateRequest() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $this->response = [
                'status' => 'error',
                'message' => 'Method Not Allowed. Only POST requests are accepted.',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $this->sendResponse();
        }
    }
    
    /**
     * Validate CSRF token
     */
    protected function validateCSRF() {
        $project_pre = $GLOBALS['project_pre'] ?? 'flasherpsystem';
        $storedToken = $_SESSION[$project_pre]['DASHBOARD']['csrf_token'] ?? '';
        if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $storedToken) {
            http_response_code(403);
            $this->response = [
                'status' => 'error',
                'message' => 'CSRF Token Invalid. Request rejected.',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $this->logRequest('CSRF_FAIL', ['provided' => $_POST['csrf_token'] ?? 'none']);
            $this->sendResponse();
        }
    }
    
    /**
     * Validate input parameter
     * 
     * @param string $param Parameter name
     * @param string $type Expected type (int, string, email, etc)
     * @param bool $required Required parameter
     * @return mixed Validated value or false
     */
    protected function validateParam($param, $type = 'string', $required = false) {
        $value = $_POST[$param] ?? null;
        
        if ($required && empty($value)) {
            throw new InvalidArgumentException("Required parameter missing: $param");
        }
        
        if ($value === null) {
            return null;
        }
        
        switch ($type) {
            case 'int':
            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT);
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL);
            case 'string':
                return is_string($value) ? strip_tags($value) : (string)$value;
            default:
                return $value;
        }
    }
    
    /**
     * Set response data
     */
    protected function setSuccess($data = null, $message = 'Success') {
        $this->response = [
            'status' => 'success',
            'data' => $data,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $this->http_code = 200;
    }
    
    /**
     * Set error response
     */
    protected function setError($message, $code = 400, $data = null) {
        $this->response = [
            'status' => 'error',
            'data' => $data,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $this->http_code = $code;
    }
    
    /**
     * Send JSON response and exit
     */
    protected function sendResponse() {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        http_response_code($this->http_code);
        echo json_encode($this->response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Log API request for audit trail
     */
    protected function logRequest($event, $data = []) {
        $log_entry = sprintf(
            "[%s] API: %s | Event: %s | IP: %s | User: %s | Data: %s\n",
            date('Y-m-d H:i:s'),
            $this->action,
            $event,
            $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
            $_SESSION[$GLOBALS['project_pre']]['DASHBOARD']['user_id'] ?? 'GUEST',
            json_encode($data)
        );
        error_log($log_entry, 3, __DIR__ . '/../../api-requests.log');

        // Route errors/warnings to centralized backend error logs
        if (function_exists('log_error')) {
            $errorEvents = ['QUERY_ERROR', 'CSRF_FAIL'];
            $isError = false;
            foreach ($errorEvents as $errEvent) {
                if (stripos($event, $errEvent) !== false || stripos($event, 'ERROR') !== false) {
                    $isError = true;
                    break;
                }
            }
            if ($isError) {
                $errorMsg = $data['error'] ?? ($data['provided'] ?? $event);
                log_error(
                    '[API:' . $this->action . '] ' . $event . ': ' . $errorMsg,
                    'ERROR',
                    __FILE__,
                    __LINE__
                );
            }
        }
    }
    
    /**
     * Execute query with prepared statements (prevent SQL injection)
     */
    protected function executeQuery($sql, $params = [], $types = '') {
        try {
            $stmt = $this->mysqli->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->mysqli->error);
            }
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            return $stmt;
        } catch (Exception $e) {
            $this->logRequest('QUERY_ERROR', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
