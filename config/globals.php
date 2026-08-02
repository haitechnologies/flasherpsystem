<?php

use App\Core\DB;
use App\Security\Roles;

// Base URLs (frontend + dashboard).
if (!isset($base_url) || $base_url === '') {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
  $script_dir = rtrim($script_dir, '/');
  $base_path = preg_replace('#/dashboard$#', '', $script_dir);
  $base_url = $scheme . '://' . $host . $base_path;
}

if (!isset($admin_base_url) || $admin_base_url === '') {
  $admin_base_url = rtrim($base_url, '/') . '/dashboard';
}

/**
 * Unified error logger — writes to both file and DB when available.
 * Replaces bare _log_error() calls so errors are visible in view_backend_error_logs.php.
 *
 * Always writes to the PHP error log (file-based), and additionally writes
 * to the erp_backend_error_logs database table if log_error() is defined.
 */
if (!function_exists('_log_error')) {
  function _log_error(string $message, string $severity = 'ERROR', array $context = []): void {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $file = $trace[1]['file'] ?? __FILE__;
    $line = $trace[1]['line'] ?? __LINE__;
        error_log("[$severity] $message in $file:$line");
    if (function_exists('log_error')) {
      log_error($message, $severity, $file, $line, $context);
    }
  }
}

/**
 * Check for mysqli database errors and return error message - Upgraded
 * Used for error checking after database queries
 *
 * @param mysqli|null $mysqli Database connection object
 * @return string|false Returns error message if error exists, false otherwise
 */
if (!function_exists('_err_')) {
  function _err_($mysqli): string|false {
    if (!$mysqli instanceof mysqli) {
      _log_error("_err_: Invalid mysqli object provided");
      return false;
    }
    
    if ($mysqli->error) {
      // Log error for debugging
      _log_error("MySQL Error: " . $mysqli->error);
      return $mysqli->error;
    }
    
    return false;
  }
}

/**
 * Check for mysqli errors and throw exception if error exists - Upgraded
 *
 * @param mysqli|null $mysqli Database connection object
 * @return void
 * @throws Exception If database error is found
 */
if (!function_exists('_err_throw_')) {
  function _err_throw_($mysqli): void {
    if (!$mysqli instanceof mysqli) {
      _log_error("_err_throw_: Invalid mysqli object provided");
      throw new Exception("Invalid database connection object");
    }
    
    if ($mysqli->error) {
      $errorMsg = "Database error: " . $mysqli->error;
      _log_error($errorMsg);
      throw new Exception($errorMsg);
    }
  }
}

/**
 * Resolve module ID by slug/name with caching.
 */
if (!function_exists('getModuleIdBySlug')) {
  function getModuleIdBySlug(string $moduleSlug, ?mysqli $mysqli = null): int {
    static $cache = [];

    $moduleSlug = trim($moduleSlug);
    if ($moduleSlug === '') {
      return 0;
    }

    if (isset($cache[$moduleSlug])) {
      return $cache[$moduleSlug];
    }

    $db = $mysqli ?? ($GLOBALS['mysqli'] ?? ($GLOBALS['conn'] ?? null));
    if (!$db instanceof mysqli) {
      $cache[$moduleSlug] = 0;
      return 0;
    }

    $query = "SELECT id FROM " . DB::MODULES . " WHERE slug = ? OR module_name = ? LIMIT 1";
    $stmt = $db->prepare($query);
    if (!$stmt) {
      $cache[$moduleSlug] = 0;
      return 0;
    }

    $stmt->bind_param('ss', $moduleSlug, $moduleSlug);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $cache[$moduleSlug] = (int)($row['id'] ?? 0);
    return $cache[$moduleSlug];
  }
}

  /**
   * Convert date from Y-m-d to d-m-Y format - Upgraded with type hints
   * Example: 2024-12-01 -> 01-12-2024
   * 
   * @param string|null $date Date in Y-m-d format
   * @return string Date in d-m-Y format or empty string if invalid
   */
  if (!function_exists('processDateYtoD')) {
    function processDateYtoD(?string $date): string {
      if ($date === null || $date === '' || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '';
      }
      return dd_($date, 'd-m-Y');
    }
  }

  /*
  |--------------------------------------------------------------------------
 	|	----------------------- 01-12-2024 --> 2024-12-01 -----------------------
  |--------------------------------------------------------------------------
  */
  /**
   * Convert date from d-m-Y to Y-m-d format - Upgraded with type hints
   * Example: 01-12-2024 -> 2024-12-01
   * 
   * @param string|null $date Date in d-m-Y format
   * @return string Date in Y-m-d format or empty string if invalid
   */
  if (!function_exists('processDateDtoY')) {
    function processDateDtoY(?string $date): string {
      if (empty($date)){
        return '';
      }

      try {
        $dt = DateTime::createFromFormat('d-m-Y', $date);
        if ($dt instanceof DateTime) {
          return $dt->format('Y-m-d');
        }
      } catch (Exception $e) {
        _log_error("processDateDtoY error for '{$date}': " . $e->getMessage());
      }

      // Fallback to old method if DateTime fails
      $new_date = explode('-', $date);
      if (count($new_date) === 3) {
        return $new_date[2] . '-' . $new_date[1] . '-' . $new_date[0];
      }

      return '';
    }
  }


  /*
  |--------------------------------------------------------------------------
 	|	------------- 2024-12-01 00:00:00 --> 01-12-2024 00:00 ------------------
  |--------------------------------------------------------------------------
  */
  /**
   * Convert datetime from Y-m-d H:i:s to d-m-Y H:i format - Upgraded
   * Example: 2024-12-01 14:30:45 -> 01-12-2024 14:30
   * 
   * @param string|null $datetime DateTime in Y-m-d H:i:s format
   * @return string DateTime in d-m-Y H:i format or empty string if invalid
   */
	if (!function_exists('processDateTimeYtoD')) {
		function processDateTimeYtoD(?string $datetime): string {
      if ($datetime === null || $datetime === '' || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
        return '';
      }
      return dd_($datetime, 'd-m-Y H:i');
    }
	}

  /**
   * Display date in user-friendly format (01-12-2024 14:30)
   * Used throughout dashboard for consistent date display
   * @param string $datetime DateTime string (Y-m-d H:i:s format)
   * @return string Formatted date (d-m-Y H:i) or '-' if empty
   */
  /**
   * Display datetime in user-friendly format - Upgraded with type hints
   * Default format: 06 Feb 2026 1:20pm (d M Y g:ia)
   * 
   * @param string|DateTime|null $datetime DateTime string or DateTime object
   * @param string|null $format Custom format string (default: 'd M Y g:ia')
   * @return string Formatted datetime or '-' if empty/invalid
   */
  function dd_(string|DateTime|null $datetime, ?string $format = null): string {
    // Handle empty/null/zero dates
    if ($datetime === null || $datetime === '' || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
      return '-';
    }
    
    try {
      // Convert to DateTime object if it's a string
      if (is_string($datetime)) {
        // Try Y-m-d H:i:s format first
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        if (!($dt instanceof DateTime)) {
          // Try Y-m-d format
          $dt = DateTime::createFromFormat('Y-m-d', $datetime);
        }
        if (!($dt instanceof DateTime)) {
          // Last resort: try to parse as standard format
          $dt = new DateTime($datetime);
        }
      } else {
        $dt = $datetime;
      }
      
      if ($dt instanceof DateTime) {
        // Use custom format or default
        $outputFormat = $format ?? 'd M Y g:ia';
        return $dt->format($outputFormat);
      }
    } catch (Exception $e) {
      _log_error("Date formatting error for '{$datetime}': " . $e->getMessage());
    }
    
    // Fallback for unparseable dates
    return is_string($datetime) ? $datetime : '-';
  }

  /**
  * Display detailed datetime with seconds - Upgraded with type hints
  * Default format: 06 Feb 2026 1:20:45pm (d M Y g:i:sa)
   * 
   * @param string|DateTime|null $datetime DateTime string or DateTime object
  * @param string|null $format Custom format string (default: 'd M Y g:i:sa')
   * @return string Formatted datetime or '-' if empty/invalid
   */
  function dd__(string|DateTime|null $datetime, ?string $format = null): string {
    return dd_($datetime, $format ?? 'd M Y g:i:sa');
  }

  /**
   * Display datetime as relative time (e.g., "2 hours ago", "3 days ago", "1 month ago")
   * Returns small-text HTML formatted relative time
   * Switches to relative format for dates more than 30 days old
   * 
   * @param string|DateTime|null $datetime DateTime string or DateTime object
   * @param bool $includeSmallClass Include small text CSS class (default: true)
   * @return string Formatted relative time with HTML or '-' if empty/invalid
   */
  function timeAgo(string|DateTime|null $datetime, bool $includeSmallClass = true): string {
    // Handle empty/null/zero dates
    if ($datetime === null || $datetime === '' || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
      return '-';
    }
    
    try {
      // Convert to DateTime object if it's a string
      if (is_string($datetime)) {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        if (!($dt instanceof DateTime)) {
          $dt = DateTime::createFromFormat('Y-m-d', $datetime);
        }
        if (!($dt instanceof DateTime)) {
          $dt = new DateTime($datetime);
        }
      } else {
        $dt = $datetime;
      }
      
      if ($dt instanceof DateTime) {
        $now = new DateTime();
        $diff = $now->getTimestamp() - $dt->getTimestamp();
        
        // Handle future dates
        if ($diff < 0) {
          return '<small class="text-muted">In the future</small>';
        }
        
        // Calculate time units
        $minute = 60;
        $hour = $minute * 60;
        $day = $hour * 24;
        $week = $day * 7;
        $month = $day * 30;
        $year = $day * 365;
        
        // Format relative time
        if ($diff < $minute) {
          $timeStr = 'Just now';
        } elseif ($diff < $hour) {
          $minutes = round($diff / $minute);
          $timeStr = $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < $day) {
          $hours = round($diff / $hour);
          $timeStr = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < $week) {
          $days = round($diff / $day);
          $timeStr = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } elseif ($diff < $month) {
          $weeks = round($diff / $week);
          $timeStr = $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
        } elseif ($diff < $year) {
          $months = round($diff / $month);
          $timeStr = $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
        } else {
          $years = round($diff / $year);
          $timeStr = $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
        }
        
        // Wrap in small tag if requested
        if ($includeSmallClass) {
          return '<small class="text-muted">' . $timeStr . '</small>';
        } else {
          return $timeStr;
        }
      }
    } catch (Exception $e) {
      _log_error("Relative time formatting error for '{$datetime}': " . $e->getMessage());
    }
    
    // Fallback for unparseable dates
    return is_string($datetime) ? $datetime : '-';
  }

  /**
   * Calculate page load time in seconds - Upgraded with type hints
   * 
   * @param float $start_time Start time from microtime(true)
   * @return string Formatted time string (e.g., "0.25 seconds")
   */
  function loading_time(float $start_time): string {
    $end_time = microtime(true);
    $page_load_time = round(($end_time - $start_time), 4);
    return $page_load_time . ' seconds';
  }

  /**
   * Convert number to words (used in invoices/PDFs).
   */
/** @deprecated — dead code, zero callers */
/*
function convert_number_to_words($num)
{
    $num = str_replace(array(',', ' '), '', trim((string)$num));
    if ($num === '' || !is_numeric($num)) {
      return '';
    }

    $num = (int)$num;
    if ($num === 0) {
      return 'zero';
    }

    $list1 = array(
      '', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven',
      'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'
    );
    $list2 = array('', 'ten', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety');
    $list3 = array(
      '', 'thousand', 'million', 'billion', 'trillion', 'quadrillion', 'quintillion', 'sextillion', 'septillion',
      'octillion', 'nonillion', 'decillion', 'undecillion', 'duodecillion', 'tredecillion', 'quattuordecillion',
      'quindecillion', 'sexdecillion', 'septendecillion', 'octodecillion', 'novemdecillion', 'vigintillion'
    );

    $words = array();
    $i = 0;
    while ($num > 0) {
      $chunk = $num % 1000;
      if ($chunk) {
        $chunk_words = array();
        $hundreds = intdiv($chunk, 100);
        $remainder = $chunk % 100;

        if ($hundreds) {
          $chunk_words[] = $list1[$hundreds] . ' hundred';
        }

        if ($remainder) {
          if ($remainder < 20) {
            $chunk_words[] = $list1[$remainder];
          } else {
            $tens = intdiv($remainder, 10);
            $ones = $remainder % 10;
            $chunk_words[] = trim($list2[$tens] . ' ' . $list1[$ones]);
          }
        }

        $scale = $list3[$i];
        $words[] = trim(implode(' ', $chunk_words) . ' ' . $scale);
      }

      $num = intdiv($num, 1000);
      $i++;
    }

    return trim(implode(' ', array_reverse($words)));
}
*/

  /**
   * Sanitize strings from DB.
   */
  function s__($value)
  {
    if (is_array($value)) {
      return array_map('s__', $value);
    }

    return trim(stripslashes((string)$value));
  }

  /**
   * Format decimal numbers for display (currency-friendly).
   *
   * @param mixed $number Numeric value to format
   * @param int $decimals Number of decimal places
   * @return string
   */
  function dec_($number, $decimals = 2): string
  {
    if (!is_numeric($decimals)) {
      $decimals = 2;
    } else {
      $decimals = (int)$decimals;
    }

    if ($number === null || $number === '') {
      return number_format(0, $decimals);
    }

    if (is_string($number)) {
      $number = str_replace([',', ' '], '', $number);
    }

    if (!is_numeric($number)) {
      return number_format(0, $decimals);
    }

    return number_format((float)$number, $decimals);
  }

  /**
     * Normalize stored HTML entities for safe display text.
     */
    if (!function_exists('display_text')) {
      function display_text($text): string
      {
        $value = (string)($text ?? '');
        if ($value === '') {
          return '';
        }

        $value = str_replace("\xC2\xA0", ' ', $value);
        for ($attempt = 0; $attempt < 3; $attempt++) {
          $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
          if ($decoded === $value) {
            break;
          }
          $value = $decoded;
        }

        return $value;
      }
    }

    /**
   * Escape output for safe HTML rendering.
   */
  if (!function_exists('e')) {
    function e($text): string
    {
        return htmlspecialchars(display_text($text), ENT_QUOTES, 'UTF-8');
    }
  }

  /**
   * Escape input strings for SQL usage.
   */
  function e_s__($value)
  {
    if (is_array($value)) {
      return array_map('e_s__', $value);
    }

    $value = trim((string)$value);
    $mysqli = $GLOBALS['DB']['MSQLI'] ?? null;
    if ($mysqli) {
      return $mysqli->real_escape_string($value);
    }

    return addslashes($value);
  }

  /*
  |--------------------------------------------------------------------------
  | 	getTableAttr
  |--------------------------------------------------------------------------
  |
  */
  function getTableAttr($field_name, $tbl_name, $id)
  {
    if (empty($id)) {
      return '';
    }

    if (strpos($tbl_name, 'geo_states') !== false || strpos($tbl_name, 'geo_countries') !== false) {
      if (function_exists('getGeoAttr')) {
        return getGeoAttr($field_name, $tbl_name, $id);
      }
    }

    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field_name)) {
      _log_error("getTableAttr: Invalid field name");
      return '';
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', str_replace('`', '', $tbl_name))) {
      _log_error("getTableAttr: Invalid table name");
      return '';
    }

    $mysqli = $GLOBALS['DB']['MSQLI'];
    $stmt = $mysqli->prepare("SELECT `" . $field_name . "` FROM `" . $tbl_name . "` WHERE `id` = ?");
    if (!$stmt) {
      _log_error("getTableAttr prepare failed: " . $mysqli->error);
      return '';
    }
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_row() : null;
    $stmt->close();

    if ($row !== null && $row[0] !== null) {
      return stripslashes((string)$row[0]);
    }

    return '';
  }

  /*
  |--------------------------------------------------------------------------
  | 	PERMISSION HELPERS (Dashboard) - Upgraded with static caching
  |--------------------------------------------------------------------------
  */
  /**
   * Check if user has permission for a module - Optimized with caching
   * 
   * @param string $permission Permission type (view, create, edit, delete)
   * @param string|int $module_id Module ID or module name
   * @return bool True if user has permission, false otherwise
   */
  function granted($permission, $module_id): bool
  {
    static $cache = [];
    static $moduleCache = [];
    static $permissionIdCache = [];
    static $rolePermissionCache = [];
    
    $role_id = $_SESSION[$GLOBALS['project_pre']]['DASHBOARD']['role_id'] ?? null;
    if (!$role_id) {
      return false;
    }

    // System admins have full access - no need to check further
    if (Roles::hasFullAccess($role_id)) {
      return true;
    }

    // Validate permission type
    $allowed = array('view', 'create', 'edit', 'delete');
    if (!in_array($permission, $allowed, true)) {
      _log_error("granted(): Invalid permission type '{$permission}'");
      return false;
    }

    $mysqli = $GLOBALS['DB']['MSQLI'] ?? null;
    if (!$mysqli) {
      _log_error("granted(): Database connection not available");
      return false;
    }

    // Resolve module name to ID if needed (with caching)
    if (!is_numeric($module_id)) {
      // Check module cache first
      if (!isset($moduleCache[$module_id])) {
        $stmt = $mysqli->prepare("SELECT id FROM " . DB::MODULES . " WHERE module_name = ? OR slug = ? LIMIT 1");
        if (!$stmt) {
          _log_error("granted(): Failed to prepare module query: " . $mysqli->error);
          return false;
        }
        $stmt->bind_param('ss', $module_id, $module_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $moduleCache[$module_id] = $row['id'] ?? null;
        $stmt->close();
      }
      $module_id = $moduleCache[$module_id];
    }

    if (!$module_id) {
      _log_error("granted(): Module not found");
      return false;
    }

    // Load role permissions into cache once per request
    if (!isset($rolePermissionCache[$role_id])) {
      $rolePermissionCache[$role_id] = [];
      $stmt = $mysqli->prepare(
        "SELECT p.module_id, mp.slug
         FROM " . DB::PERMISSIONS . " p
         INNER JOIN " . DB::MODULE_PERMISSIONS . " mp ON mp.id = p.permission_id
         WHERE p.role_id = ?"
      );
      if ($stmt) {
        $stmt->bind_param('i', $role_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
          $modId = (int)$row['module_id'];
          $slug = $row['slug'] ?? '';
          if ($modId > 0 && $slug !== '') {
            $rolePermissionCache[$role_id][$modId][$slug] = true;
          }
        }
        $stmt->close();
      }
    }

    if (isset($rolePermissionCache[$role_id][$module_id])) {
      return !empty($rolePermissionCache[$role_id][$module_id][$permission]);
    }

    // Resolve permission ID for this module + permission slug
    $permissionKey = $module_id . ':' . $permission;
    if (!isset($permissionIdCache[$permissionKey])) {
      $stmt = $mysqli->prepare("SELECT id FROM " . DB::MODULE_PERMISSIONS . " WHERE module_id = ? AND slug = ? LIMIT 1");
      if (!$stmt) {
        _log_error("granted(): Failed to prepare module permission query: " . $mysqli->error);
        return false;
      }
      $stmt->bind_param('is', $module_id, $permission);
      $stmt->execute();
      $result = $stmt->get_result();
      $row = $result ? $result->fetch_assoc() : null;
      $stmt->close();

      $permissionIdCache[$permissionKey] = (int)($row['id'] ?? 0);
    }

    $permissionId = $permissionIdCache[$permissionKey];
    if (!$permissionId) {
      _log_error("granted(): Permission '{$permission}' not defined for module_id {$module_id}");
      return false;
    }

    // Check permission cache
    $cacheKey = "{$role_id}:{$module_id}:{$permissionId}";
    if (isset($cache[$cacheKey])) {
      return $cache[$cacheKey];
    }

    // Query permission mapping table
    $stmt = $mysqli->prepare("SELECT 1 FROM " . DB::PERMISSIONS . " WHERE role_id = ? AND module_id = ? AND permission_id = ? LIMIT 1");
    if (!$stmt) {
      _log_error("granted(): Failed to prepare permission mapping query: " . $mysqli->error);
      return false;
    }
    $stmt->bind_param('iii', $role_id, $module_id, $permissionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $hasPermission = $result && $result->num_rows > 0;
    $stmt->close();

    // Cache the result
    $cache[$cacheKey] = $hasPermission;

    return $hasPermission;
  }

  /**
   * Shorthand for granted() using module name
   * 
   * @param string $permission Permission type (view, create, edit, delete)
   * @param string $module_name Module name
   * @return bool True if user has permission, false otherwise
   */
  function granted_($permission, $module_name): bool
  {
    return granted($permission, $module_name);
  }

  /*
  |--------------------------------------------------------------------------
  | 	IDOR Protection Functions - Prevent unauthorized resource access
  |--------------------------------------------------------------------------
  |
  */

  /**
   * Check if current user owns a resource (IDOR protection)
   * 
   * @param string $tableName Table name (use DB:: constants)
   * @param int $resourceId Resource ID to check
   * @param string $ownerColumn Column name that contains owner user ID (default: 'user_id')
   * @param int|null $userId Override current user ID (defaults to session user)
   * @return bool True if user owns resource, false otherwise
   * 
   * @example
   * // Check if user owns invoice before showing details
   * if (!checkOwnership(DB::INVOICES, $_GET['id'], 'customer_id')) {
   *     header('Location: 404.php');
   *     exit;
   * }
   */
  function checkOwnership($tableName, $resourceId, $ownerColumn = 'user_id', $userId = null) {
    global $conn;
    
    // Default to current session user
    if ($userId === null) {
      $userId = $_SESSION['h_id'] ?? null;
    }
    
    // No user ID = not logged in
    if (!$userId) {
      return false;
    }
    
    // Validate resource ID
    if (!is_numeric($resourceId) || $resourceId <= 0) {
      return false;
    }
    
    // Validate table name (prevent SQL injection)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
      _log_error("checkOwnership: Invalid table name - $tableName");
      return false;
    }
    
    // Validate owner column (prevent SQL injection)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $ownerColumn)) {
      _log_error("checkOwnership: Invalid column name - $ownerColumn");
      return false;
    }
    
    // Check if database connection exists
    if (!$conn) {
      _log_error("checkOwnership: Database connection not available");
      return false;
    }
    
    // Query to check ownership
    $stmt = $conn->prepare("SELECT id FROM `$tableName` WHERE id = ? AND `$ownerColumn` = ? LIMIT 1");
    if (!$stmt) {
      _log_error("checkOwnership: Failed to prepare statement - " . $conn->error);
      return false;
    }
    
    $stmt->bind_param("ii", $resourceId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $owns = $result->num_rows > 0;
    
    $stmt->close();
    
    return $owns;
  }

  /**
   * Check ownership or die with 404 (convenience wrapper)
   * 
   * @param string $tableName Table name
   * @param int $resourceId Resource ID
   * @param string $ownerColumn Owner column name
   * @param string $redirectUrl URL to redirect to (default: 404.php)
   * @return void Exits if user doesn't own resource
   * 
   * @example
   * // Protect customer detail page
   * checkOwnershipOrDie(DB::CUSTOMERS, $_GET['id'], 'user_id');
   */
  function checkOwnershipOrDie($tableName, $resourceId, $ownerColumn = 'user_id', $redirectUrl = '404.php') {
    if (!checkOwnership($tableName, $resourceId, $ownerColumn)) {
      header("Location: $redirectUrl");
      exit;
    }
  }

  /**
   * Check if user has permission OR owns resource (flexible access control)
   * 
   * @param string $permission Permission type (view, edit, delete)
   * @param string $module Module name for permission check
   * @param string $tableName Table name for ownership check
   * @param int $resourceId Resource ID
   * @param string $ownerColumn Owner column name
   * @return bool True if user has permission OR owns resource
   * 
   * @example
   * // Allow edit if user has global edit permission OR owns the company
   * if (canAccessResource('edit', 'companies', DB::COMPANIES, $_GET['id'])) {
   *     // Show edit form
   * }
   */
  function canAccessResource($permission, $module, $tableName, $resourceId, $ownerColumn = 'user_id') {
    // Check global permission first
    if (granted_($permission, $module)) {
      return true;
    }
    
    // If no global permission, check ownership
    return checkOwnership($tableName, $resourceId, $ownerColumn);
  }

  /**
   * Get list of resource IDs owned by user (for bulk operations)
   * 
   * @param string $tableName Table name
   * @param string $ownerColumn Owner column name
   * @param int|null $userId User ID (defaults to session user)
   * @return array Array of resource IDs
   * 
   * @example
   * // Get all customer IDs owned by current user
   * $ownedCustomers = getOwnedResources(DB::CUSTOMERS, 'user_id');
   * // Use in query: WHERE id IN (1,2,3)
   */
  function getOwnedResources($tableName, $ownerColumn = 'user_id', $userId = null) {
    global $conn;
    
    if ($userId === null) {
      $userId = $_SESSION['h_id'] ?? null;
    }
    
    if (!$userId) {
      return [];
    }
    
    // Validate inputs
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName) || !preg_match('/^[a-zA-Z0-9_]+$/', $ownerColumn)) {
      return [];
    }
    
    if (!$conn) {
      return [];
    }
    
    $stmt = $conn->prepare("SELECT id FROM `$tableName` WHERE `$ownerColumn` = ?");
    if (!$stmt) {
      return [];
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ids = [];
    while ($row = $result->fetch_assoc()) {
      $ids[] = (int)$row['id'];
    }
    
    $stmt->close();
    
    return $ids;
  }

  /**
   * Filter array of IDs to only include resources owned by user
   * 
   * @param array $ids Array of resource IDs to filter
   * @param string $tableName Table name
   * @param string $ownerColumn Owner column name
   * @param int|null $userId User ID (defaults to session user)
   * @return array Filtered array of IDs user owns
   * 
   * @example
   * // Only delete invoices the user owns
   * $requestedIds = $_POST['ids']; // [1, 2, 3, 4, 5]
   * $ownedIds = filterOwnedResources($requestedIds, DB::INVOICES, 'customer_id');
   * // $ownedIds might be [1, 3, 5] if user only owns those
   */
  function filterOwnedResources($ids, $tableName, $ownerColumn = 'user_id', $userId = null) {
    global $conn;
    
    if (empty($ids) || !is_array($ids)) {
      return [];
    }
    
    if ($userId === null) {
      $userId = $_SESSION['h_id'] ?? null;
    }
    
    if (!$userId) {
      return [];
    }
    
    // Validate inputs
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName) || !preg_match('/^[a-zA-Z0-9_]+$/', $ownerColumn)) {
      return [];
    }
    
    if (!$conn) {
      return [];
    }
    
    // Sanitize IDs (only keep integers)
    $ids = array_filter($ids, 'is_numeric');
    $ids = array_map('intval', $ids);
    
    if (empty($ids)) {
      return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("SELECT id FROM `$tableName` WHERE id IN ($placeholders) AND `$ownerColumn` = ?");
    
    if (!$stmt) {
      return [];
    }
    
    // Bind parameters dynamically
    $types = str_repeat('i', count($ids)) . 'i';
    $params = array_merge($ids, [$userId]);
    $stmt->bind_param($types, ...$params);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ownedIds = [];
    while ($row = $result->fetch_assoc()) {
      $ownedIds[] = (int)$row['id'];
    }
    
    $stmt->close();
    
    return $ownedIds;
  }

    /*
    |--------------------------------------------------------------------------
    | 	getTableAttrv - Upgraded with security & caching
    |--------------------------------------------------------------------------
    |
    */
    /**
     * Get table attribute value with WHERE condition (SQL injection protected)
     * 
     * @param mixed $field_name Field name to retrieve (string or array)
     * @param string $tbl_name Table name
     * @param string $condition WHERE condition (without 'WHERE' keyword)
     * @return mixed|null Field value or null if not found
     */
		function getTableAttrv($field_name, $tbl_name, $condition = ''){
      static $cache = [];
      
      // Input validation
      if (empty($field_name) || empty($tbl_name)) {
        return null;
      }
      
      // Generate cache key
      $cacheKey = md5($field_name . '|' . $tbl_name . '|' . $condition);
      if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
      }

			$mysqli = $GLOBALS['DB']['MSQLI'] ?? null;
      if (!$mysqli) {
        _log_error("getTableAttrv: Database connection not available");
        return null;
      }
      
      // Build safe query (condition is already escaped by caller, but we validate table/field names)
      if (!preg_match('/^[a-zA-Z0-9_]+$/', str_replace('`', '', $tbl_name))) {
        _log_error("getTableAttrv: Invalid table name: " . $tbl_name);
        return null;
      }
      
      $whereClause = !empty($condition) ? " WHERE " . $condition : "";
      $query = "SELECT {$field_name} FROM `{$tbl_name}`{$whereClause}";
      
			$result = $mysqli->query($query);
      
      if (!$result) {
        _log_error("getTableAttrv query failed: " . $mysqli->error);
        return null;
      }
      
			$row = $result->fetch_row();

      $value = null;
      if (!empty($row[0])) {
        $value = stripslashes($row[0]);
      }
      
      // Cache the result
      $cache[$cacheKey] = $value;
      
      return $value;
		}

    /*
    |--------------------------------------------------------------------------
    | 	getVendorPayables
    |--------------------------------------------------------------------------
    |
    */
    function getVendorPayables($vendor_id, $mysqli) {
      $vendor_id = intval($vendor_id);

      $query = "SELECT COALESCE(SUM(grand_total), 0) as total_purchases
                FROM `" . DB::PURCHASES . "`
                WHERE vendor_id = {$vendor_id}
                AND purchase_status NOT IN ('draft', 'declined', 'expired')";
      $rs = $mysqli->query($query);
      $row = $rs->fetch_assoc();
      $total_purchases = floatval($row['total_purchases'] ?? 0);

      $query = "SELECT COALESCE(SUM(total_amount_paid), 0) as total_paid
                FROM `" . tbl_payments_made . "`
                WHERE vendor_id = {$vendor_id}
                AND payment_status != 'void'";
      $rs = $mysqli->query($query);
      $row = $rs->fetch_assoc();
      $total_paid = floatval($row['total_paid'] ?? 0);

      $payables = $total_purchases - $total_paid;
      return $payables > 0 ? $payables : 0;
    }

    /*
    |--------------------------------------------------------------------------
    | 	getTableAttrbySlug - Upgraded with security & caching
    |--------------------------------------------------------------------------
    |
    */
    /**
     * Get table attribute by slug (SQL injection protected with prepared statement)
     * 
     * @param string $field_name Field name to retrieve
     * @param string $tbl_name Table name
     * @param string|null $slug Slug value to search for
     * @return mixed|null Field value or null if not found
     */
		function getTableAttrbySlug($field_name, $tbl_name, $slug){
      static $cache = [];
      
      // Input validation
      if (empty($slug) || empty($field_name) || empty($tbl_name)){
        return null;
      }
      
      // Generate cache key
      $cacheKey = md5($field_name . '|' . $tbl_name . '|' . $slug);
      if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
      }

      $mysqli = $GLOBALS['DB']['MSQLI'] ?? null;
      if (!$mysqli) {
        _log_error("getTableAttrbySlug: Database connection not available");
        return null;
      }
      
      // Validate table name to prevent SQL injection
      if (!preg_match('/^[a-zA-Z0-9_]+$/', str_replace('`', '', $tbl_name))) {
        _log_error("getTableAttrbySlug: Invalid table name: " . $tbl_name);
        return null;
      }
      
      // Use prepared statement for slug parameter
      $stmt = $mysqli->prepare("SELECT {$field_name} FROM `{$tbl_name}` WHERE slug = ? LIMIT 1");
      if (!$stmt) {
        _log_error("getTableAttrbySlug prepare failed: " . $mysqli->error);
        return null;
      }
      
      $stmt->bind_param('s', $slug);
      $stmt->execute();
      $result = $stmt->get_result();
      $row = $result ? $result->fetch_row() : null;
      $stmt->close();
      
      $value = null;
      if (!empty($row[0])) {
        $value = stripslashes($row[0]);
      }
      
      // Cache the result
      $cache[$cacheKey] = $value;
      
      return $value;
		}

    
    /*
    |--------------------------------------------------------------------------
    | 	USER BLOCK
    |--------------------------------------------------------------------------
    |
    */
		function log_auth_failed($email){
    $mysqli = $GLOBALS['DB']['MSQLI'] ?? null;
    if (!$mysqli || empty($email)) {
      return;
    }

    $stmt = $mysqli->prepare("SELECT id FROM `" . DB::USERS . "` WHERE email = ? LIMIT 1");
    if (!$stmt) {
      return;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_id = (int)(($result->fetch_row()[0] ?? 0));
    $stmt->close();

    // Skip auth_activity insert when email has no matching user row.
    // This table enforces FK(user_id -> hai_users.id), so unknown emails cannot be inserted.
    if ($user_id <= 0) {
      return;
    }
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $created_at = date("Y-m-d H:i:s");

    $stmt = $mysqli->prepare("INSERT INTO `" . DB::AUTHENTICATION_ACTIVITY . "` (user_id, activity_type, ip_address, user_agent, created_at) VALUES (?, 'login_failed', ?, ?, ?)");
    if ($stmt) {
      $stmt->bind_param('isss', $user_id, $ip_address, $user_agent, $created_at);
      $stmt->execute();
      $stmt->close();
    }
	}

/** @deprecated — dead code, zero callers */
/*
	function log_user_block($email){
    log_auth_failed($email);
	}
*/

	function log_account_blocked($email){
    $mysqli = $GLOBALS['DB']['MSQLI'] ?? null;
    if (!$mysqli || empty($email)) {
      return;
    }

    $stmt = $mysqli->prepare("SELECT id FROM `" . DB::USERS . "` WHERE email = ? LIMIT 1");
    if (!$stmt) {
      return;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_id = (int)(($result->fetch_row()[0] ?? 0));
    $stmt->close();

    if ($user_id <= 0) {
      return;
    }
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $created_at = date("Y-m-d H:i:s");

    $stmt = $mysqli->prepare("INSERT INTO `" . DB::AUTHENTICATION_ACTIVITY . "` (user_id, activity_type, ip_address, user_agent, created_at) VALUES (?, 'account_blocked', ?, ?, ?)");
    if ($stmt) {
      $stmt->bind_param('isss', $user_id, $ip_address, $user_agent, $created_at);
      $stmt->execute();
      $stmt->close();
    }
	}

    /*
    |--------------------------------------------------------------------------
    | 	LEAD LOGS
    |--------------------------------------------------------------------------
    |
    */
    function updateEntityLog($entity_type, $entity_id, $module, $record_id = null, $action = 'edit') {
      if (empty($entity_id) || empty($module) || empty($entity_type)) {
        return;
      }

      $mysqli = $GLOBALS['DB']['MSQLI'];
      $table = defined('DB::ENTITY_LOGS') ? constant('DB::ENTITY_LOGS') : 'erp_entity_logs';
      $created_at = date('Y-m-d H:i:s');

      $record_id_val = ($record_id === '' || $record_id === null) ? null : (int)$record_id;

      $stmt = $mysqli->prepare("INSERT INTO `" . $table . "` (entity_type, entity_id, record_id, module, action, created_at) VALUES (?, ?, ?, ?, ?, ?)");
      if ($stmt) {
        $stmt->bind_param('siisss', $entity_type, $entity_id, $record_id_val, $module, $action, $created_at);
        $stmt->execute();
        $stmt->close();
      }
    }

    function updateLeadLogs($lead_id, $module, $record_id, $action = 'edit') {
      updateEntityLog('lead', $lead_id, $module, $record_id, $action);
    }

    function updateCustomerLogs($customer_id, $module, $action = 'edit') {
      updateEntityLog('customer', $customer_id, $module, '', $action);
    }
    /*
    |--------------------------------------------------------------------------
    | 	USER LOG
    |--------------------------------------------------------------------------
    |
    */
		function log_user_login($email){
    $mysqli   = $GLOBALS['DB']['MSQLI'] ?? null;
    if (!$mysqli || empty($email)) {
      return;
    }

    $stmt = $mysqli->prepare("SELECT id FROM `" . DB::USERS . "` WHERE email = ? LIMIT 1");
    if (!$stmt) {
      return;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_id = (int)(($result->fetch_row()[0] ?? 0));
    $stmt->close();

    if ($user_id <= 0) {
      return;
    }

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $created_at = date("Y-m-d H:i:s");

    // Log successful login to unified authentication activity table
    $stmt = $mysqli->prepare("INSERT INTO `" . DB::AUTHENTICATION_ACTIVITY . "` (user_id, activity_type, ip_address, user_agent, created_at) VALUES (?, 'login_success', ?, ?, ?)");
    if ($stmt) {
      $stmt->bind_param('isss', $user_id, $ip_address, $user_agent, $created_at);
      $stmt->execute();
      $stmt->close();
    }
	}

    /*
    |--------------------------------------------------------------------------
    | 	colorfulInvoiceStatus
    |--------------------------------------------------------------------------
    |
    */
	function colorfulInvoiceStatus($status){

        if ($status == 'not_confirmed') {
          $status = '<span class="badge bg-primary"> Not Confirmed </span>';
        } else if ($status == 'confirmed') {
          $status = '<span class="badge bg-success">Confirmed</span>';
        } else if ($status == 'on_hold') {
          $status = 'Confirmed';
        } else if ($status == 'on_hold') {
          $status = 'On Hold';
        } else if ($status == 'cancelled') {
          $status = 'Cancelled';
        }

        return $status;
		}



    /*
    |--------------------------------------------------------------------------
    | 	checkDuplicateRow
    |--------------------------------------------------------------------------
    |
    */
		function checkDuplicateRow($tbl_name, $field_name, $field_value){

			if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field_name)) {
				_log_error("checkDuplicateRow: Invalid field name");
				return 0;
			}
			if (!preg_match('/^[a-zA-Z0-9_]+$/', str_replace('`', '', $tbl_name))) {
				_log_error("checkDuplicateRow: Invalid table name");
				return 0;
			}

			$mysqli = $GLOBALS['DB']['MSQLI'];
			$stmt = $mysqli->prepare("SELECT count(id) FROM `" . $tbl_name . "` WHERE `" . $field_name . "` = ?");
			if (!$stmt) {
				_log_error("checkDuplicateRow prepare failed: " . $mysqli->error);
				return 0;
			}
			$stmt->bind_param('s', $field_value);
			$stmt->execute();
			$result = $stmt->get_result();
			$row = $result->fetch_row();
			$stmt->close();
			return stripslashes($row[0] ?? '0');
		}


   
    /*
    |--------------------------------------------------------------------------
    | 	fp__
    |--------------------------------------------------------------------------
    |
    */
		function fp__($tbl_name, $id){
			
      $mysqli     = $GLOBALS['DB']['MSQLI'];
      $user_id	  = (int)($_SESSION[$GLOBALS['project_pre']]['DASHBOARD']['user_id'] ?? 0);
      $datetime   = date("Y-m-d H:i:s");
      $id         = (int)$id;

      if ($id <= 0 || !preg_match('/^[a-zA-Z0-9_]+$/', str_replace('`', '', $tbl_name))) {
        return;
      }

      $created_by = getTableAttr("created_by", $tbl_name, $id);
      
      if ($created_by == '0000-00-00 00:00:00'){
        $stmt = $mysqli->prepare("UPDATE `" . $tbl_name . "` SET created_by = ?, created_at = ?, updated_at = ? WHERE id = ?");
        if ($stmt) {
          $stmt->bind_param('issi', $user_id, $datetime, $datetime, $id);
          $stmt->execute();
          $stmt->close();
        }
      } else {
        $stmt = $mysqli->prepare("UPDATE `" . $tbl_name . "` SET created_by = ?, updated_at = ? WHERE id = ?");
        if ($stmt) {
          $stmt->bind_param('isi', $user_id, $datetime, $id);
          $stmt->execute();
          $stmt->close();
        }
      }
    }

      /*
      |--------------------------------------------------------------------------
      | 	publish / unpublish
      |--------------------------------------------------------------------------
      | Legacy listing pages expect these helpers to toggle status fields.
      | Default status field is `is_active` (standard column name).
      | */
  /** @deprecated — dead code, zero callers */
/*
    function publish(string $module_caption, string $tbl_name, int $id, string $status_field = 'is_active'): bool {
        $mysqli = $GLOBALS['DB']['MSQLI'];
        $user_id = (int)($_SESSION[$GLOBALS['project_pre']]['DASHBOARD']['user_id'] ?? 0);
        $datetime = date("Y-m-d H:i:s");

        $id = (int)$id;
        if ($id <= 0) {
          return false;
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$tbl_name) || !preg_match('/^[a-zA-Z0-9_]+$/', (string)$status_field)) {
          return false;
        }

        $columnExistsResult = $mysqli->query("SHOW COLUMNS FROM `" . $tbl_name . "` LIKE '" . $mysqli->real_escape_string($status_field) . "'");
        if (!$columnExistsResult || $columnExistsResult->num_rows === 0) {
          return false;
        }

        $setParts = ["`$status_field` = 1"];

        $hasUpdatedAt = $mysqli->query("SHOW COLUMNS FROM `" . $tbl_name . "` LIKE 'updated_at'");
        if ($hasUpdatedAt && $hasUpdatedAt->num_rows > 0) {
          $setParts[] = "`updated_at` = '" . $mysqli->real_escape_string($datetime) . "'";
        }

        $hasUpdatedBy = $mysqli->query("SHOW COLUMNS FROM `" . $tbl_name . "` LIKE 'updated_by'");
        if ($hasUpdatedBy && $hasUpdatedBy->num_rows > 0) {
          $setParts[] = "`updated_by` = " . $user_id;
        }

        $sql = "UPDATE `" . $tbl_name . "` SET " . implode(', ', $setParts) . " WHERE id = " . $id;
        return (bool)$mysqli->query($sql);
      }
*/

/** @deprecated — dead code, zero callers */
/*
      function unpublish(string $module_caption, string $tbl_name, int $id, string $status_field = 'is_active'): bool {
        $mysqli = $GLOBALS['DB']['MSQLI'];
        $user_id = (int)($_SESSION[$GLOBALS['project_pre']]['DASHBOARD']['user_id'] ?? 0);
        $datetime = date("Y-m-d H:i:s");

        $id = (int)$id;
        if ($id <= 0) {
          return false;
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$tbl_name) || !preg_match('/^[a-zA-Z0-9_]+$/', (string)$status_field)) {
          return false;
        }

        $columnExistsResult = $mysqli->query("SHOW COLUMNS FROM `" . $tbl_name . "` LIKE '" . $mysqli->real_escape_string($status_field) . "'");
        if (!$columnExistsResult || $columnExistsResult->num_rows === 0) {
          return false;
        }

        $setParts = ["`$status_field` = 0"];

        $hasUpdatedAt = $mysqli->query("SHOW COLUMNS FROM `" . $tbl_name . "` LIKE 'updated_at'");
        if ($hasUpdatedAt && $hasUpdatedAt->num_rows > 0) {
          $setParts[] = "`updated_at` = '" . $mysqli->real_escape_string($datetime) . "'";
        }

        $hasUpdatedBy = $mysqli->query("SHOW COLUMNS FROM `" . $tbl_name . "` LIKE 'updated_by'");
        if ($hasUpdatedBy && $hasUpdatedBy->num_rows > 0) {
          $setParts[] = "`updated_by` = " . $user_id;
        }

        $sql = "UPDATE `" . $tbl_name . "` SET " . implode(', ', $setParts) . " WHERE id = " . $id;
        return (bool)$mysqli->query($sql);
      }
*/

      /*
      |--------------------------------------------------------------------------
      | 	delete
      |--------------------------------------------------------------------------
      | Handles deletion of records in both tenant-isolated and global tables.
      | */
      if (!function_exists('delete')) {
        function delete($tbl_name, $id) {
          $mysqli = $GLOBALS['DB']['MSQLI'] ?? $GLOBALS['conn'] ?? null;
          if (!$mysqli instanceof mysqli) {
            _log_error("delete: Database connection not available");
            return false;
          }

          $id = (int)$id;
          if ($id <= 0) {
            return false;
          }

          if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$tbl_name)) {
            return false;
          }

          $activeOrgId = 0;
          if (function_exists('dashboardGetActiveOrganizationId')) {
            $activeOrgId = dashboardGetActiveOrganizationId(false);
          }

          // Check if organization_id column exists
          $hasOrgId = false;
          $columnCheck = $mysqli->query("SHOW COLUMNS FROM `" . $tbl_name . "` LIKE 'organization_id'");
          if ($columnCheck && $columnCheck->num_rows > 0) {
            $hasOrgId = true;
          }

          if ($hasOrgId && $activeOrgId > 0) {
            $stmt = $mysqli->prepare("DELETE FROM `" . $tbl_name . "` WHERE id = ? AND organization_id = ?");
            if (!$stmt) {
              return false;
            }
            $stmt->bind_param('ii', $id, $activeOrgId);
            $res = $stmt->execute();
            $stmt->close();
            return $res;
          } else {
            $stmt = $mysqli->prepare("DELETE FROM `" . $tbl_name . "` WHERE id = ?");
            if (!$stmt) {
              return false;
            }
            $stmt->bind_param('i', $id);
            $res = $stmt->execute();
            $stmt->close();
            return $res;
          }
        }
      }

    
  // function timezone($datetime)
  // {
  //   if (preg_match('/localhost/', $_SERVER['HTTP_HOST']) || preg_match('/127.0.0.1/', $_SERVER['HTTP_HOST']) || preg_match('/192.168.0.105/', $_SERVER['HTTP_HOST']))
  //     $formatted_datetime =  date("g:i a, j M Y", strtotime("+120 minutes", strtotime(date($datetime))));
  //   else
  //     $formatted_datetime =  date("g:i a, j M Y", strtotime("+240 minutes", strtotime(date($datetime))));
  //   return $formatted_datetime;
  // }

    
     
    /*

    /*
    |--------------------------------------------------------------------------
    | 	getUsernameByID
    |--------------------------------------------------------------------------
    |
    */
    function getUsernameByID($id){

      if (empty($id)) return '';
      $mysqli = $GLOBALS['DB']['MSQLI'];
      $result = $mysqli->query("SELECT email FROM `".$GLOBALS['TBL']['PREFIX']. "users` WHERE id='".$id."'");
      $row = $result->fetch_array();
      return ($row !== null) ? $row[0] : '';

    }
 

    /*
    |--------------------------------------------------------------------------
    | 	getUserType
    |--------------------------------------------------------------------------
    |
    */
/** @deprecated — dead code, zero callers */
/*
    function getUserType($id){

      $mysqli = $GLOBALS['DB']['MSQLI'];
      $result = $mysqli->query("SELECT type FROM `".$GLOBALS['TBL']['PREFIX']. "users` WHERE id='".$id."'");
      $row = $result->fetch_array();
          return $row[0];

    }
*/
 

    /*
    |--------------------------------------------------------------------------
    | 	getUserAttr
    |--------------------------------------------------------------------------
    |
    */
/** @deprecated — dead code, zero callers */
/*
    function getUserAttr($field_name, $id){

      $mysqli = $GLOBALS['DB']['MSQLI'];
      $result = $mysqli->query("SELECT ".$field_name." FROM `".$GLOBALS['TBL']['PREFIX']."users` WHERE id='$id'");
      $row = $result->fetch_row();
          return stripslashes($row[0]);

    }
*/




    /*
    |--------------------------------------------------------------------------
    | 	getCreatedBy_EmailID
    |--------------------------------------------------------------------------
    |
    */
/** @deprecated — dead code, zero callers */
/*
    function getCreatedBy_EmailID($id){

      $mysqli = $GLOBALS['DB']['MSQLI'];
      $result = $mysqli->query("SELECT created_by FROM `".$GLOBALS['TBL']['PREFIX']."emails` WHERE id=$id");
      $row = $result->fetch_array();
          return $row[0];
    }
*/
 

    /*
    |--------------------------------------------------------------------------
    | 	getMyAvatar
    |--------------------------------------------------------------------------
    |
    */
/** @deprecated — dead code, zero callers */
/*
    function getMyAvatar($id){

      $mysqli = $GLOBALS['DB']['MSQLI'];
      $result = $mysqli->query("SELECT photo FROM `".$GLOBALS['TBL']['PREFIX']."users` WHERE id='$id'");
      $row = $result->fetch_array();
          return $row[0];
    }
*/

    /*
    |--------------------------------------------------------------------------
    | 	getCount
    |--------------------------------------------------------------------------
    |
    */
/** @deprecated — dead code, zero callers */
/*
    function getCount(string $table_name, string $condition = ''): int {

      $mysqli = $GLOBALS['DB']['MSQLI'];
      if (!empty($condition))
        $result = $mysqli->query("SELECT count(id) FROM $table_name WHERE ".$condition."");
      else
        $result = $mysqli->query("SELECT count(id) FROM $table_name");
        $row = $result->fetch_array();
            return $row[0];

    }
*/
 
    /*
    |--------------------------------------------------------------------------
    | 	getPageNameBySlug
    |--------------------------------------------------------------------------
    |
    */
/** @deprecated — dead code, zero callers */
/*
    function getPageNameBySlug($slug){

      $mysqli = $GLOBALS['DB']['MSQLI'];
      $slug 				= '"'.$mysqli->real_escape_string($slug).'"';
      $result = $mysqli->query("SELECT page_name FROM `".$GLOBALS['TBL']['PREFIX']."slugs` WHERE slug=$slug"); _err_($mysqli);
      $row = $result->fetch_array();
        return stripslashes($row[0]);
    }
*/
    

    /*
    |--------------------------------------------------------------------------
    | 	getIDFromUsername
    |--------------------------------------------------------------------------
    |
    */
/** @deprecated — dead code, zero callers */
/*
    function getIDFromUsername($username, $table_name){

      $mysqli = $GLOBALS['DB']['MSQLI'];
        // echo "SELECT id FROM `" . $table_name . "` WHERE username= '" . $username . "' ";
        $result = $mysqli->query("SELECT id FROM `".$table_name."` WHERE username= '".$username."' ");
        $row = $result->fetch_array();
        if (!empty($row[0])) return $row[0];
    }
*/

    /*
    |--------------------------------------------------------------------------
    | 	getUsernameFromID
    |--------------------------------------------------------------------------
    |
    */
/** @deprecated — dead code, zero callers */
/*
    function getUsernameFromID($id, $table_name){

      $mysqli = $GLOBALS['DB']['MSQLI'];
      $result = $mysqli->query("SELECT username FROM `".$table_name."` WHERE id='".$id."' ");
      $row = $result->fetch_array();
          return $row[0];
    }
*/

    /*
    |--------------------------------------------------------------------------
    | 	GET ALL VARIABLES ADD/UPDATE
    |--------------------------------------------------------------------------
    |
    */
/** @deprecated — dead code, zero callers */
/*
    function checkDuplicateUsername($username, $table_name){

      $mysqli = $GLOBALS['DB']['MSQLI'];
      $result = $mysqli->query("SELECT username FROM `".$GLOBALS['TBL']['PREFIX']."users` WHERE username='$username'");
      $row = $result->fetch_array();
          if (!empty($row['0'])) return true;
          else return false;
    }
*/



    /*
    |--------------------------------------------------------------------------
    | 	getClientName
    |--------------------------------------------------------------------------
    |
    */
    // function getClientName($client_id)
    // {

    //   $mysqli = $GLOBALS['DB']['MSQLI'];

    //   $client_name = '';

    //   if (!empty($client_id)){

    //     $result = $mysqli->query("SELECT * FROM `".tbl_clients."` WHERE id=$client_id");
    //     $row = $result->fetch_array();

    //     $title                  = s__($row['title']);

    //     if (!empty($title))     $title = ucwords($title);

    //     $first_name             = s__($row['first_name']);
    //     $last_name              = s__($row['last_name']);
    //     $company_name           = s__($row['company_name']);
    //     $company_is_primary     = s__($row['company_is_primary']);

    //     if ($company_is_primary == 1) {
    //       $client_name = $company_name . ' (' . $title . ' ' . $first_name . ' ' . $last_name . ')';
    //     } else {
    //       $client_name = $title . ' ' . $first_name . ' ' . $last_name . ' (' . $company_name . ')';
    //     }
        
    //   } 

    //   return $client_name;
      
    // }
        

    /*
    |--------------------------------------------------------------------------
    | 	GET ALL VARIABLES ADD/UPDATE
    |--------------------------------------------------------------------------
    |
    */
/** @deprecated — dead code, zero callers */
/*
    function checkDuplicateEmail($email, $id, $table_name){

      $mysqli = $GLOBALS['DB']['MSQLI'];

      if (!empty($id)){
        $result = $mysqli->query("SELECT email FROM `".$GLOBALS['TBL']['PREFIX']."users` WHERE email='$email' AND id!='$id'");
        $row = $result->fetch_array();
      } else {
        $result = $mysqli->query("SELECT email FROM `".$GLOBALS['TBL']['PREFIX']."users` WHERE email='$email'");
        $row = $result->fetch_array();
      }

          if (!empty($row['0'])) return true;
          else return false;
    }
*/




/*
  |--------------------------------------------------------------------------
  | 	SLUGIFY
  |  this%20is%20the%20example%20demo%20page
  |  this-is-the-example-demo-page
  |--------------------------------------------------------------------------
  |
  */
function slugify($tbl_name, $title)
{
  $regex  = "/\.+$/";
  $regex  = "/[.*?!@#$&-_ ]+$/";
  $result = preg_replace($regex, "", $title);
  $val    = preg_replace('/\s+/u', '-', trim($result));
  $val    = str_replace(array('(', ')', ':'), '', $val);
  $val    = str_replace('--', '-', $val);
  $val    = str_replace('---', '-', $val);
  $val    = str_replace('----', '-', $val);
  $val    = str_replace('-----', '-', $val);
  $val    = str_replace('------', '-', $val);
  $val    = str_replace('-------', '-', $val);
  $val    = str_replace('/', '', $val);
  $val    = str_replace('&', '-', $val);
  $val    = str_replace(',', '', $val);

  return strtolower(trim($val, '-'));
}

// Account/category dropdown helpers removed (missing tables).
/*
    |--------------------------------------------------------------------------
    | 	GET ALL VARIABLES ADD/UPDATE
    |--------------------------------------------------------------------------
    |
    */
/* backup the db OR just a table */
/** @deprecated — dead code, zero callers */
/*
///function backup_tables($host, $user, $pass, $name, $tables = '*')
function backup_tables($mysqli, $tables = '*')
{
  $mysqli = $mysqli;
  //get all of the tables
  if ($tables == '*') {
    $tables = array();
    $result = $mysqli->query("SHOW TABLES"); //_err_($mysqli);
    while ($row = $result->fetch_array()) {
      $tables[] = $row[0];
    }
  } else {
    $tables = is_array($tables) ? $tables : explode(',', $tables);
  }

  $return = '';
  //cycle through
  foreach ($tables as $table) {
    $result = $mysqli->query("SELECT * FROM " . $table);
    $num_fields = mysqli_num_fields($result);

    $return .= 'DROP TABLE IF EXISTS `' . $table . '`;';
    $row2 = mysqli_fetch_row($mysqli->query("SHOW CREATE TABLE " . $table));
    $return .= "\n\n" . $row2[1] . ";\n\n";

    for ($i = 0; $i < $num_fields; $i++) {
      while ($row = $result->fetch_array()) {
        $return .= 'INSERT INTO ' . $table . ' VALUES(';
        for ($j = 0; $j < $num_fields; $j++) {
          if (!empty($row[$j])) {
            $row[$j] = addslashes($row[$j]);
            $row[$j] = str_replace("\n", "\\n", $row[$j]);
          }

          if (isset($row[$j])) {
            $return .= '"' . $row[$j] . '"';
          } else {
            $return .= '""';
          }
          if ($j < ($num_fields - 1)) {
            $return .= ',';
          }
        }
        $return .= ");\n";
      }
    }
    $return .= "\n\n\n";
  }

  //save file
  //$handle = fopen('db/db-'.time().'-'.(md5(implode(',',$tables))).'.sql','w+');
  $handle = fopen('cron/' . date("Y-m-d_His") . '.sql', 'w+');
  fwrite($handle, $return);
  fclose($handle);

  return true;
}
*/






/*
|--------------------------------------------------------------------------|
| Function to get hscodes recursively
|--------------------------------------------------------------------------|
*/

/** @deprecated — dead code, zero callers */
/*
function fetchHsCodes($parent_id = null)
{
  $mysqli = $GLOBALS['DB']['MSQLI'];

  $sql = "SELECT h.*, te.description as hscode_name FROM `" . DB::HS_CODES . "` h LEFT JOIN `" . DB::HS_CODE_TEXTS . "` te ON h.id = te.hs_code_id AND te.lang = 'en' WHERE h.parent_id " . ($parent_id === null ? "IS NULL" : "= ?");
  $stmt = $mysqli->prepare($sql);

  if ($parent_id !== null) {
    $stmt->bind_param("i", $parent_id);
  }

  $stmt->execute();
  $result = $stmt->get_result();
  $hscodes = $result->fetch_all(MYSQLI_ASSOC);

  $html = '';
  if (!empty($hscodes)) {
    $html .= "<ul>";
    foreach ($hscodes as $hscode) {

      $id = $hscode['id'];

      $html .= "<li><a href=\"hscodes.php?action=edit_hscodes&id=" . $id . "\"> " . htmlspecialchars($hscode['hscode_name']);
      $html .= fetchHsCodes($hscode['id']); // Recursive call
      $html .= "</a></li>";
    }
    $html .= "</ul>";
  }

  // $stmt->close();

  return $html;
}
*/


/*
|--------------------------------------------------------------------------|
| Function to get hscodes recursively
|--------------------------------------------------------------------------|
*/

/** @deprecated — dead code, zero callers */
/*
function fetchHsCodesDropdown($parent_id = null, $prefix = '', $selected = null)
{
  $mysqli = $GLOBALS['DB']['MSQLI'];

  $sql = "SELECT h.*, te.description as hscode_name FROM `" . DB::HS_CODES . "` h LEFT JOIN `" . DB::HS_CODE_TEXTS . "` te ON h.id = te.hs_code_id AND te.lang = 'en' WHERE h.parent_id " . ($parent_id === null ? "IS NULL" : "= ?");
  $stmt = $mysqli->prepare($sql);

  if ($parent_id !== null) {
    $stmt->bind_param("i", $parent_id);
  }

  $stmt->execute();
  $result = $stmt->get_result();
  $hscodes = $result->fetch_all(MYSQLI_ASSOC);

  $html = '';
  if (!empty($hscodes)) {
    foreach ($hscodes as $hscode) {
      $id = $hscode['id'];
      $name = htmlspecialchars($hscode['hscode_name']);

      $html .= "<option value=\"$id\" " . (($id == $selected) ? 'selected' : '') . ">" . $prefix . $name . "</option>";
      // Recursive call with a prefix to indicate nesting visually
      $html .= fetchHsCodesDropdown($id, $prefix . "--");
    }
  }

  // $stmt->close();

  return $html;
}
*/


    /*
    |--------------------------------------------------------------------------
    | ROLE CHECKING FUNCTIONS (Optimized)
    |--------------------------------------------------------------------------
    |
    | These are simplified wrappers for common role checks.
    | Use these for backward compatibility, but prefer Roles:: class for
    | new code as it's more type-safe and flexible.
    |
    | MIGRATION PATH:
    | Old: is_SystemAdmin() || is_SuperAdmin()
    | New: Roles::currentUserHasFullAccess()
    |
    | Old: is_role() == 'accounts'
    | New: Roles::isAccounts(Roles::getCurrentRoleId())
    |
    */
    
    /**
     * Check if current user is System Admin
     * 
     * @deprecated Use Roles::isSystemAdmin(Roles::getCurrentRoleId()) instead
     * @return bool
     */
    function is_SystemAdmin()
    {
        $role_id = $_SESSION[$GLOBALS['project_pre']]['DASHBOARD']['role_id'] ?? null;
        return Roles::isSystemAdmin($role_id);
    }
    
    /**
     * Check if current user is Super Admin
     * 
     * @deprecated Use Roles::isSuperAdmin(Roles::getCurrentRoleId()) instead
     * @return bool
     */
    function is_SuperAdmin()
    {
        $role_id = $_SESSION[$GLOBALS['project_pre']]['DASHBOARD']['role_id'] ?? null;
        return Roles::isSuperAdmin($role_id);
    }
    
    /**
     * Check if current user has full access (System or Super Admin)
     * 
     * RECOMMENDED: Use this instead of (is_SystemAdmin() || is_SuperAdmin())
     * 
     * @return bool
     */
    function has_full_access()
    {
        return Roles::currentUserHasFullAccess();
    }
    
    /**
     * Check if current user is Sales role
     * 
     * @return bool
     */
    function is_sales()
    {
        $role_id = $_SESSION[$GLOBALS['project_pre']]['DASHBOARD']['role_id'] ?? null;
        return Roles::isSales($role_id);
    }
    
    /**
     * Check if current user is Operations role
     * 
     * @return bool
     */
    function is_operations()
    {
        $role_id = $_SESSION[$GLOBALS['project_pre']]['DASHBOARD']['role_id'] ?? null;
        return Roles::isOperations($role_id);
    }
    
    /**
     * Check if current user is Accounts role
     * 
     * @return bool
     */
    function is_accounts()
    {
        $role_id = $_SESSION[$GLOBALS['project_pre']]['DASHBOARD']['role_id'] ?? null;
        return Roles::isAccounts($role_id);
    }

    /**
     * Get current user's role slug
     *
     * @deprecated Use Roles::getName(Roles::getCurrentRoleId()) instead
     * @return string
     */
    function is_role()
    {
        $role_id = $_SESSION[$GLOBALS['project_pre']]['DASHBOARD']['role_id'] ?? null;
        return $role_id !== null ? strtolower(trim(Roles::getName((int)$role_id))) : '';
    }
    
    // Account/category dropdown helpers removed (missing tables).


/*
|--------------------------------------------------------------------------|
| InvoiceDueDays
|--------------------------------------------------------------------------|
*/
function getInvoiceDueDay($invoice_status, $invoice_date, $payment_term_duration)
{
  // 1. Initial Cleanup & Check
  $invoice_status = strtolower(trim($invoice_status ?? ''));

  // Only calculate overdue if the status is 'sent'
  if ($invoice_status === 'sent') {
    $due_date_obj = new DateTime($invoice_date);
    $today = new DateTime();
    $today->setTime(0, 0, 0);

    // 2. Calculate Due Date
    switch (strtolower(trim($payment_term_duration ?? ''))) {
      case 'due end of the month':
        $due_date_obj->modify('last day of this month');
        break;
      case 'due end of the next month':
        $due_date_obj->modify('last day of next month');
        break;
      case 'net 15':
        $due_date_obj->modify('+15 days');
        break;
      case 'net 30':
        $due_date_obj->modify('+30 days');
        break;
      case 'net 60':
        $due_date_obj->modify('+60 days');
        break;
      default:
        // Default: Due on Receipt (invoice date)
        break;
    }

    $due_date_obj->setTime(0, 0, 0);

    // 3. Compare Dates
    if ($today > $due_date_obj) {
      $interval = $due_date_obj->diff($today);
      $days_overdue = $interval->days;
      return '<span class="text-danger">OVERDUE BY ' . $days_overdue . ' DAYS</span>';
    }
  }

  // Default return for non-sent or non-overdue invoices
  return strtoupper($invoice_status);
}



// --------------------------------------------------------------------------
/**
 * Calculates the exact due date based on invoice date and terms.
 * Returns date string in 'Y-m-d' format (e.g., 2024-01-31)
 */
// --------------------------------------------------------------------------

function calculateInvoiceDueDate($invoice_status, $invoice_date, $payment_term_duration)
{
  // Handle empty or null dates to prevent errors
  if (empty($invoice_date)) {
    return null;
  }

  if ($invoice_status != 'sent') {
    return null;
  }

  $date = new DateTime($invoice_date);
  $term = strtolower(trim($payment_term_duration ?? ''));

  switch ($term) {
    case 'due end of the month':
      $date->modify('last day of this month');
      break;
    case 'due end of the next month':
      $date->modify('last day of next month');
      break;
    case 'net 15':
      $date->modify('+15 days');
      break;
    case 'net 30':
      $date->modify('+30 days');
      break;
    case 'net 45':
      $date->modify('+45 days');
      break;
    case 'net 60':
      $date->modify('+60 days');
      break;
    case 'due on receipt':
    default:
      // No modification needed, due date is invoice date
      break;
  }

  return $date->format('Y-m-d');
}



/*
|--------------------------------------------------------------------------|
| Customer Receivables
|--------------------------------------------------------------------------|
*/
/**
 * Calculates the current outstanding balance for a specific customer.
 * Formula: (Total Invoices) - (Total Payments + Write-offs)
 * * @param mysqli $mysqli The database connection
 * @param int $customer_id The ID of the customer
 * @return float The total amount currently owed
 */


/*
|==================================================================================
| FRONTEND ERROR LOGGING HELPERS
|==================================================================================
| Convenient helper functions for consistent error logging across frontend code
|==================================================================================
*/

/**
 * Log error message
 * 
 * @param string $message Error message
 * @param array $context Additional context data
 * @param string|null $file File path (auto-detected if null)
 * @param int|null $line Line number (auto-detected if null)
 */
/** @deprecated — dead code, zero callers */
/*
function logError($message, $context = [], $file = null, $line = null) {
    $logger = $GLOBALS['frontendLogger'] ?? null;
    if ($logger) {
        $logger->error($message, $context, $file, $line);
    }
}
*/

/**
 * Log warning message
 * 
 * @param string $message Warning message
 * @param array $context Additional context data
 * @param string|null $file File path (auto-detected if null)
 * @param int|null $line Line number (auto-detected if null)
 */
/** @deprecated — dead code, zero callers */
/*
function logWarning($message, $context = [], $file = null, $line = null) {
    $logger = $GLOBALS['frontendLogger'] ?? null;
    if ($logger) {
        $logger->warning($message, $context, $file, $line);
    }
}
*/

/**
 * Log notice message
 * 
 * @param string $message Notice message
 * @param array $context Additional context data
 * @param string|null $file File path (auto-detected if null)
 * @param int|null $line Line number (auto-detected if null)
 */
/** @deprecated — dead code, zero callers */
/*
function logNotice($message, $context = [], $file = null, $line = null) {
    $logger = $GLOBALS['frontendLogger'] ?? null;
    if ($logger) {
        $logger->notice($message, $context, $file, $line);
    }
}
*/

/**
 * Log debug message (dev-only)
 * 
 * @param string $message Debug message
 * @param array $context Additional context data
 * @param string|null $file File path (auto-detected if null)
 * @param int|null $line Line number (auto-detected if null)
 */
/** @deprecated — dead code, zero callers */
/*
function logDebug($message, $context = [], $file = null, $line = null) {
    $logger = $GLOBALS['frontendLogger'] ?? null;
    if ($logger) {
        $logger->debug($message, $context, $file, $line);
    }
}
*/

/**
 * Log database error with query details
 * 
 * @param string $query The SQL query that failed
 * @param string $error The error message from database
 * @param string|null $file File path (auto-detected if null)
 * @param int|null $line Line number (auto-detected if null)
 */
/** @deprecated — dead code, zero callers */
/*
function logDatabaseError($query, $error, $file = null, $line = null) {
    logError('Database Error', [
        'query' => $query,
        'error' => $error,
        'timestamp' => date('Y-m-d H:i:s')
    ], $file, $line);
}
*/

/**
 * Log database query execution (dev-only)
 * Only logged in development, useful for profiling
 * 
 * @param string $query The SQL query executed
 * @param int $rows Number of rows affected/returned
 * @param float $time Execution time in seconds
 */
/** @deprecated — dead code, zero callers */
/*
function logDatabaseQuery($query, $rows = 0, $time = 0) {
  $appEnv = strtolower((string)(getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? '')));
  if ($appEnv === 'development') {
        logDebug('Database Query', [
            'query' => $query,
            'rows_affected' => $rows,
            'execution_time_ms' => round($time * 1000, 2)
        ]);
    }
}
*/

/**
 * Get system setting value by slug - Upgraded with prepared statements & caching
 * 
 * @param string $slug Setting slug (e.g., 'logo', 'favicon', 'amp_logo')
 * @param mixed $default Default value if setting not found
 * @return string Setting value
 */
function getSystemSetting(string $slug, mixed $default = ''): string {
    static $cache = [];
    
    // Input validation
    if (empty($slug)) {
        return $default;
    }
    
    // Check static cache first (faster than globals)
    if (isset($cache[$slug])) {
        return $cache[$slug];
    }
    
    // Check if cached in globals (backward compatibility)
    if (isset($GLOBALS['SYSTEM_SETTINGS'][$slug])) {
        $cache[$slug] = $GLOBALS['SYSTEM_SETTINGS'][$slug];
        return $GLOBALS['SYSTEM_SETTINGS'][$slug];
    }
    
    // Query database with prepared statement
    $mysqli = $GLOBALS['DB']['MSQLI'] ?? null;
    if (!$mysqli instanceof mysqli) {
        _log_error("getSystemSetting: Database connection not available");
        return $default;
    }
    
    // Use DB constant if available, otherwise fallback to table name
    $table_name = defined('DB::SYSTEM_SETTINGS') ? constant('DB::SYSTEM_SETTINGS') : 'erp_system_settings';
    
    $stmt = $mysqli->prepare("SELECT setting_value FROM {$table_name} WHERE setting_slug = ? LIMIT 1");
    if (!$stmt) {
        _log_error("getSystemSetting prepare failed: " . $mysqli->error);
        return $default;
    }
    
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    
    if ($row && isset($row['setting_value'])) {
        $value = $row['setting_value'];
        // Cache it in both locations
        $cache[$slug] = $value;
        $GLOBALS['SYSTEM_SETTINGS'][$slug] = $value;
        return $value;
    }
    
    return $default;
}

/**
 * Get logo URL from system settings
 * 
 * @param string $type Type of logo: 'logo', 'favicon', or 'amp_logo'
 * @param bool $absolute Return absolute URL (default: relative)
 * @return string Logo URL or empty string if not set
 */
/** @deprecated — dead code, zero callers */
/*
function getSystemLogo($type = 'logo', $absolute = false) {
    $logo = getSystemSetting($type, '');
    
    if (empty($logo)) {
        return '';
    }
    
    $base_path = $absolute ? (rtrim($GLOBALS['base_url'] ?? '', '/') . '/') : '';
    $upload_path = 'uploads/system_settings/';
    
    return $base_path . $upload_path . $logo;
}
*/

/**
 * Get favicon URL from system settings with fallback
 * 
 * @return string Favicon URL
 */
function getSystemFavicon() {
    $favicon = getSystemSetting('favicon', '');
    
    if (!empty($favicon)) {
        return 'uploads/system_settings/' . $favicon;
    }
    
    return 'favicon.ico'; // Fallback to default
}

// ============================================
// UI COLOR SETTINGS FUNCTIONS
// ============================================

/**
 * Get UI color setting with fallback to default
 * 
 * @param string $colorKey Color setting key (e.g., 'admin_header_bg_color')
 * @param string $default Default color if not set (default: '#ffffff')
 * @return string Color hex value
 */
/**
 * Get UI color setting with fallback to default - Upgraded with type hints
 * 
 * @param string $colorKey Color setting key (e.g., 'admin_header_bg_color')
 * @param string $default Default color if not set (default: '#ffffff')
 * @return string Color hex value
 */
function getUIColor(string $colorKey, string $default = '#ffffff'): string {
    return getSystemSetting($colorKey, $default);
}

/**
 * Get all admin header colors - Upgraded with type hints
 * 
 * @return array<string, string> Array of color settings for admin header
 */
function getAdminHeaderColors(): array {
    return [
        'background' => getUIColor('admin_header_bg_color', '#ffffff'),
        'text' => getUIColor('admin_header_text_color', '#0b1a2b'),
        'accent' => getUIColor('admin_header_accent_color', '#3ba1ff') ?? '#3ba1ff',
    ];
}

/**
 * Get all sidebar colors - Upgraded with type hints
 * 
 * @return array<string, string> Array of color settings for sidebar
 */
function getSidebarColors(): array {
    return [
        'background' => getUIColor('sidebar_bg_color', '#f7f7fe') ?? '#f7f7fe',
        'text' => getUIColor('sidebar_text_color', '#2f3b4f') ?? '#2f3b4f',
        'active_bg' => getUIColor('sidebar_active_bg_color', '#e7f0ee') ?? '#e7f0ee',
        'active_text' => getUIColor('sidebar_active_text_color', '#3ba1ff') ?? '#3ba1ff',
        'hover_bg' => getUIColor('sidebar_hover_bg_color', '#f0f0f7') ?? '#f0f0f7',
    ];
}

/**
 * Get all login page colors - Upgraded with type hints
 * 
 * @return array<string, string> Array of color settings for login page
 */
function getLoginPageColors(): array {
    return [
        'header_bg' => getUIColor('login_header_bg_color', '#3ba1ff') ?? '#3ba1ff',
        'header_text' => getUIColor('login_header_text_color', '#ffffff') ?? '#ffffff',
        'form_bg' => getUIColor('login_form_bg_color', '#ffffff') ?? '#ffffff',
        'button_bg' => getUIColor('login_button_bg_color', '#3ba1ff') ?? '#3ba1ff',
        'button_text' => getUIColor('login_button_text_color', '#ffffff') ?? '#ffffff',
        'button_hover' => getUIColor('login_button_hover_color', '#2a8ae8') ?? '#2a8ae8',
    ];
}



/**
 * Generate CSS color variables for use in stylesheets
 * This function outputs CSS that can be used to dynamically apply colors
 * 
 * @return string CSS variable declarations
 */
function generateColorVariablesCSS() {
    $headerColors = getAdminHeaderColors();
    $sidebarColors = getSidebarColors();
    $loginColors = getLoginPageColors();
    
    $css = <<<CSS
    <style>
    :root {
        /* Admin Header Colors */
        --admin-header-bg: {$headerColors['background']};
        --admin-header-text: {$headerColors['text']};
        --admin-header-accent: {$headerColors['accent']};
        
        /* Sidebar Colors */
        --sidebar-bg: {$sidebarColors['background']};
        --sidebar-text: {$sidebarColors['text']};
        --sidebar-active-bg: {$sidebarColors['active_bg']};
        --sidebar-active-text: {$sidebarColors['active_text']};
        --sidebar-hover-bg: {$sidebarColors['hover_bg']};
        
        /* Login Page Colors */
        --login-header-bg: {$loginColors['header_bg']};
        --login-header-text: {$loginColors['header_text']};
        --login-form-bg: {$loginColors['form_bg']};
        --login-btn-bg: {$loginColors['button_bg']};
        --login-btn-text: {$loginColors['button_text']};
        --login-btn-hover: {$loginColors['button_hover']};
    }
    </style>
CSS;
    
    return $css;
}

/**
 * Generate inline style attribute from color settings
 * Useful for applying colors directly to HTML elements
 * 
 * @param string $element Element to style: 'admin-header', 'sidebar', 'login', etc.
 * @return string Inline CSS style
 */
/** @deprecated — dead code, zero callers */
/*
function getColorStyle($element = 'admin-header') {
    $element = strtolower($element);
    
    switch ($element) {
        case 'admin-header':
        case 'header':
            $colors = getAdminHeaderColors();
            return "background-color: {$colors['background']}; color: {$colors['text']};";
            
        case 'sidebar':
            $colors = getSidebarColors();
            return "background-color: {$colors['background']};";            
        case 'login':
        case 'login-header':
            $colors = getLoginPageColors();
            return "background-color: {$colors['header_bg']}; color: {$colors['header_text']};";
            
        default:
            return "";
    }
}
*/

/**
 * Generate a unique referral code for a company
 * 
 * Generates an 8-character alphanumeric code that's guaranteed to be unique
 * across all companies in the system.
 *
 * @global mysqli $conn Database connection object
 * @param int $company_id Company ID (for uniqueness check)
 * @return string 8-character referral code (e.g., "ABC12XYZ")
 * @throws Exception If unable to generate unique code after 10 attempts
 */
/** @deprecated — dead code, zero callers */
/*
function generateUniqueReferralCode($company_id = null) {
    global $conn;
    
    // Character set for code generation (no ambiguous characters: 0/O, 1/I/l)
    $chars = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    $maxAttempts = 10;
    
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        // Generate random 8-character code
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        // Check if code already exists
        $checkQuery = "SELECT COUNT(*) as cnt FROM " . DB::COMPANIES . 
                      " WHERE referral_code = '" . $conn->real_escape_string($code) . "' 
                      AND id != " . (int)$company_id;
        
        $result = $conn->query($checkQuery);
        $row = $result->fetch_assoc();
        
        if ($row['cnt'] == 0) {
            // Code is unique
            return $code;
        }
    }
    
    // Fallback: Very unlikely to reach here, but add company_id for uniqueness
    return strtoupper(substr(md5(uniqid($company_id, true)), 0, 8));
}
*/

/**
 * Track a referral click
 * 
 * Records when someone clicks a referral link and the referrer/referee info.
 *
 * @global mysqli $conn Database connection object
 * @param string $referral_code Code from /referral?code=ABC123
 * @param string $ip_address IP address of visitor
 * @return array Result array with success status and referral ID
 */
/** @deprecated — dead code, zero callers */
/*
function trackReferralClick($referral_code, $ip_address = null) {
    global $conn;
    
    if (!$ip_address) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    // Get company that owns this code
    $code = $conn->real_escape_string($referral_code);
    $getCompanyQuery = "SELECT id FROM " . DB::COMPANIES . " WHERE referral_code = '{$code}' LIMIT 1";
    $result = $conn->query($getCompanyQuery);
    
    if (!$result || $result->num_rows == 0) {
        return ['success' => false, 'message' => 'Invalid referral code'];
    }
    
    $referrer = $result->fetch_assoc();
    
    // erp_referral_tracking table decommissioned
    return ['success' => false, 'message' => 'Referral tracking is not available'];
}
*/

/**
 * Get referral statistics for a company
 * 
 * Returns comprehensive referral metrics including total clicks, conversions,
 * and earned rewards.
 *
 * @global mysqli $conn Database connection object
 * @param int $company_id Company ID to get stats for
 * @return array Referral stats with clicks, conversions, rewards
 */
/** @deprecated — dead code, zero callers */
/*
function getReferralStats($company_id) {
    global $conn;
    
    $company_id = (int)$company_id;
    
    // erp_referral_tracking table decommissioned
    return [
        'clicks' => 0,
        'conversions' => 0,
        'conversion_rate' => 0,
        'rewards_earned' => 0.0,
        'rewards_pending' => 0.0,
        'total_rewards' => 0.0
    ];
}
*/



/**
 * Send email immediately (for critical emails)
 * 
 * Bypasses queue and sends email directly.
 *
 * @param string $to_email Recipient email
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $from_email From address
 * @param string $from_name From name
 * @return bool Success
 */
/** @deprecated — dead code, zero callers */
/*
function sendEmailDirect($to_email, $subject, $body, $from_email = 'noreply@uaebusinessdirectory.ae', $from_name = 'UAE Business Directory') {
    // Validate email
    if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Prepare headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . $from_name . " <" . $from_email . ">\r\n";
    $headers .= "Reply-To: " . $from_email . "\r\n";
    
    // Send
    // return mail($to_email, $subject, $body, $headers); // DISABLED: SMTP only
    return false;
}
*/

/**
 * Get email queue statistics
 * 
 * Returns queue status and metrics.
 *
 * @global mysqli $conn Database connection
 * @return array Queue statistics
 */
/** @deprecated — dead code, zero callers */
/*
function getEmailQueueStats() {
    global $conn;
    
    // Pending count
    $pendingQuery = "SELECT COUNT(*) as count FROM " . DB::EMAIL_QUEUE . " WHERE status = 'pending'";
    $pendingResult = $conn->query($pendingQuery);
    $pending = $pendingResult->fetch_assoc()['count'] ?? 0;
    
    // Sent count
    $sentQuery = "SELECT COUNT(*) as count FROM " . DB::EMAIL_QUEUE . " WHERE status = 'sent'";
    $sentResult = $conn->query($sentQuery);
    $sent = $sentResult->fetch_assoc()['count'] ?? 0;
    
    // Failed count
    $failedQuery = "SELECT COUNT(*) as count FROM " . DB::EMAIL_QUEUE . " WHERE status = 'error'";
    $failedResult = $conn->query($failedQuery);
    $failed = $failedResult->fetch_assoc()['count'] ?? 0;
    
    // Today's sent
    $todayQuery = "SELECT COUNT(*) as count FROM " . DB::EMAIL_HISTORY . " 
                   WHERE DATE(sent_at) = CURDATE() AND status = 'sent'";
    $todayResult = $conn->query($todayQuery);
    $today = $todayResult->fetch_assoc()['count'] ?? 0;
    
    return [
        'pending' => (int)$pending,
        'sent' => (int)$sent,
        'failed' => (int)$failed,
        'today' => (int)$today,
        'total_queued' => (int)($pending + $sent + $failed)
    ];
}
*/

/**
 * CSRF TOKEN MANAGEMENT
 * =====================
 * Secure CSRF token handling for form submissions
 */

/**
 * Generate or retrieve CSRF token for session
 * Call this function in forms to output the token
 * 
 * @return string The CSRF token
 */
if (!function_exists('csrf_token')) {
    function csrf_token() {
        $project_pre = $GLOBALS['project_pre'] ?? 'flasherpsystem';
        
        if (!isset($_SESSION[$project_pre]['DASHBOARD']['csrf_token'])) {
            $_SESSION[$project_pre]['DASHBOARD']['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION[$project_pre]['DASHBOARD']['csrf_token'];
    }
}
    /**
     * Generate frontend CSRF token (for public pages like register, login, contact form)
     * Stores in session key: $_SESSION[$project_pre]['FRONTEND']['csrf_token']
     * 
     * @return string The CSRF token
     */
    if (!function_exists('csrf_token_frontend')) {
      function csrf_token_frontend() {
        $project_pre = $GLOBALS['project_pre'] ?? 'flasherpsystem';
        
        if (!isset($_SESSION[$project_pre]['FRONTEND']['csrf_token'])) {
          $_SESSION[$project_pre]['FRONTEND']['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION[$project_pre]['FRONTEND']['csrf_token'];
      }
    }


/**
 * Output CSRF token as hidden input field
 * Use this in all forms: echo csrf_field();
 * 
 * @return string HTML hidden input with CSRF token
 */
if (!function_exists('csrf_field')) {
    function csrf_field() {
        $token = csrf_token();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}
    /**
     * Generate frontend CSRF field HTML (for public pages like register, login, contact form)
     * Uses csrf_token_frontend() to generate and return hidden input field
     * 
     * @return string HTML hidden input with CSRF token
     */
/** @deprecated — dead code, zero callers */
/*
    if (!function_exists('csrf_field_frontend')) {
      function csrf_field_frontend() {
        $token = csrf_token_frontend();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
      }
    }
*/


/**
 * Validate CSRF token from POST request
 * Use this at the beginning of action handlers
 * 
 * Example:
 *   if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
 *       die('Invalid security token');
 *   }
 * 
 * @param string $token The CSRF token to validate
 * @return bool True if token is valid, false otherwise
 */
if (!function_exists('validate_csrf_token')) {
    function validate_csrf_token($token = '') {
        $project_pre = $GLOBALS['project_pre'] ?? 'flasherpsystem';
        
        // Check if token is provided
        if (empty($token)) {
            return false;
        }
        
        // Check if session token exists
        if (!isset($_SESSION[$project_pre]['DASHBOARD']['csrf_token'])) {
            return false;
        }
        
        // Validate token matches
        if ($token !== $_SESSION[$project_pre]['DASHBOARD']['csrf_token']) {
            return false;
        }
        
        return true;
    }
}
    /**
     * Validate frontend CSRF token (for public pages like register, login, contact form)
     * Checks against $_SESSION[$project_pre]['FRONTEND']['csrf_token']
     * 
     * Example:
     *   if (!validate_csrf_token_frontend($_POST['csrf_token'] ?? '')) {
     *       die('Invalid security token');
     *   }
     * 
     * @param string $token The CSRF token to validate
     * @return bool True if token is valid, false otherwise
     */
/** @deprecated — dead code, zero callers */
/*
    if (!function_exists('validate_csrf_token_frontend')) {
      function validate_csrf_token_frontend($token = '') {
        $project_pre = $GLOBALS['project_pre'] ?? 'flasherpsystem';
        
        // Check if token is provided
        if (empty($token)) {
          return false;
        }
        
        // Check if session token exists
        if (!isset($_SESSION[$project_pre]['FRONTEND']['csrf_token'])) {
          return false;
        }
        
        // Validate token matches
        if ($token !== $_SESSION[$project_pre]['FRONTEND']['csrf_token']) {
          return false;
        }
        
        return true;
      }
    }
*/


/**
 * Regenerate CSRF token (call after using a token once)
 * Optional: Some implementations rotate tokens after each use
 */
/** @deprecated — dead code, zero callers */
/*
if (!function_exists('regenerate_csrf_token')) {
    function regenerate_csrf_token() {
        global $project_pre;
        $_SESSION[$project_pre]['DASHBOARD']['csrf_token'] = bin2hex(random_bytes(32));
    }
}
*/

/**
 * IS_ACTIVE COLUMN HELPERS
 * =========================
 * Professional status management using is_active column
 * Provides consistent query helpers for filtering active records
 */

/**
 * Get WHERE clause for active records
 * Usage: $query = "SELECT * FROM " . DB::BLOGS . " WHERE " . is_active_where();
 * 
 * @param string $alias Optional table alias (e.g., 'b' for 'blogs as b')
 * @return string WHERE clause condition
 */
/** @deprecated — dead code, zero callers */
/*
if (!function_exists('is_active_where')) {
    function is_active_where(string $alias = ''): string {
        $field = $alias ? "$alias.is_active" : 'is_active';
        return "$field = 1";
    }
}
*/

/**
 * Get WHERE clause for inactive records
 * Usage: Archive query to find soft-deleted/inactive records
 * 
 * @param string $alias Optional table alias
 * @return string WHERE clause condition
 */
/** @deprecated — dead code, zero callers */
/*
if (!function_exists('is_inactive_where')) {
    function is_inactive_where(string $alias = ''): string {
        $field = $alias ? "$alias.is_active" : 'is_active';
        return "$field = 0";
    }
}
*/

/**
 * Get WHERE clause for all records (active and inactive)
 * Used when you need to include both states
 * 
 * @param string $alias Optional table alias
 * @return string WHERE clause condition (returns '1=1' - always true)
 */
/** @deprecated — dead code, zero callers */
/*
if (!function_exists('is_active_any')) {
    function is_active_any(string $alias = ''): string {
        return '1=1';
    }
}
*/

/**
 * Set is_active status for a record
 * 
 * Usage: set_is_active($conn, DB::BLOGS, $blog_id, true);
 * 
 * @param mysqli $conn Database connection
 * @param string $table Table name (use DB:: constants)
 * @param int $id Record ID
 * @param bool $status True for active, false for inactive
 * @return bool True if successful, false otherwise
 */
/** @deprecated — dead code, zero callers */
/*
if (!function_exists('set_is_active')) {
    function set_is_active($conn, $table, $id, $status) {
        $id = intval($id);
        $status = $status ? 1 : 0;
        $query = "UPDATE `$table` SET is_active = $status WHERE id = $id";
        return $conn->query($query);
    }
}
*/

/**
 * Set a success message to be flashed on the next page load.
 */
if (!function_exists('flash_success')) {
    function flash_success(string $message): void {
        \App\Core\FlashMessage::success($message);
    }
}

/**
 * Set an error message to be flashed on the next page load.
 */
if (!function_exists('flash_error')) {
    function flash_error(string $message): void {
        \App\Core\FlashMessage::error($message);
    }
}

/**
 * Set an info message to be flashed on the next page load.
 */
/** @deprecated — dead code, zero callers */
/*
if (!function_exists('flash_info')) {
    function flash_info(string $message): void {
        \App\Core\FlashMessage::info($message);
    }
}
*/

/**
 * Set a warning message to be flashed on the next page load.
 */
/** @deprecated — dead code, zero callers */
/*
if (!function_exists('flash_warning')) {
    function flash_warning(string $message): void {
        \App\Core\FlashMessage::warning($message);
    }
}
*/

