<?php

use App\Core\DB;
use App\Core\Session;
/**
 * UI Design Settings Management Page
 * 
 * Allows administrators to customize colors for:
 * - Admin header/topbar
 * - Sidebar
 * - Login page
 * - Button colors
 * - Alert colors
 */

include('admin_elements/admin_header.php');

$module = 'ui_design';
$module_caption = 'UI Design Settings';
$error_message = '';
$success_message = '';

// Check permission to edit settings
if (!granted_('edit', 'settings')) {
    header('Location: dashboard.php?error=Access%20Denied');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION[$project_pre]['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }

        // Color categories to save
        $colorSlugs = [
            // Admin Header Colors
            'admin_header_bg_color',
            'admin_header_text_color',
            'admin_header_accent_color',
            
            // Sidebar Colors
            'sidebar_bg_color',
            'sidebar_text_color',
            'sidebar_active_bg_color',
            'sidebar_active_text_color',
            'sidebar_hover_bg_color',
            
            // Login Page Colors
            'login_header_bg_color',
            'login_header_text_color',
            'login_form_bg_color',
            'login_button_bg_color',
            'login_button_text_color',
            'login_button_hover_color',
        ];

        $updated_count = 0;
        
        foreach ($colorSlugs as $slug) {
            if (isset($_POST[$slug])) {
                // Validate hex color format
                $color = sanitizeColorInput($_POST[$slug]);
                
                if (!isValidHexColor($color)) {
                    throw new Exception("Invalid color format for {$slug}");
                }

                // Update or insert setting
                if (updateSystemSetting($mysqli, $slug, $color)) {
                    $updated_count++;
                    // Clear cache for this setting
                    unset($GLOBALS['SYSTEM_SETTINGS'][$slug]);
                }
            }
        }

        $success_message = "Successfully updated {$updated_count} color settings!";
        
    } catch (Exception $e) {
        log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'ui_design', 'action' => 'save_settings']);
        $error_message = "Error: " . htmlspecialchars($e->getMessage());
    }
}

// Generate CSRF token if not exists
if (empty($_SESSION[$project_pre]['csrf_token'])) {
    $_SESSION[$project_pre]['csrf_token'] = bin2hex(random_bytes(32));
}

// Get current color settings
$headerColors = getAdminHeaderColors();
$sidebarColors = getSidebarColors();
$loginColors = getLoginPageColors();

/**
 * Sanitize color input
 */
function sanitizeColorInput($color) {
    // Remove any whitespace
    $color = trim($color);
    
    // If starts with #, remove it for validation
    if (substr($color, 0, 1) === '#') {
        $color = substr($color, 1);
    }
    
    // Add # back
    return '#' . strtoupper($color);
}

/**
 * Validate hex color format
 */
function isValidHexColor($color) {
    $color = sanitizeColorInput($color);
    return preg_match('/^#[0-9A-F]{6}$/i', $color) === 1;
}

/**
 * Update system setting in database
 */
function updateSystemSetting(&$mysqli, $slug, $value) {
    global $project_pre;
    
    $slug = $mysqli->real_escape_string($slug);
    $value = $mysqli->real_escape_string($value);
    
    // Check if record exists
    $checkQuery = "SELECT id FROM " . DB::SYSTEM_SETTINGS . " WHERE setting_slug = '{$slug}' LIMIT 1";
    $result = $mysqli->query($checkQuery);
    
    if ($result && $result->num_rows > 0) {
        // Record exists - UPDATE
        $query = "UPDATE " . DB::SYSTEM_SETTINGS . " 
                  SET setting_value = '{$value}' 
                  WHERE setting_slug = '{$slug}'";
        return $mysqli->query($query);
    } else {
        // Record doesn't exist - INSERT
        // Table has: setting_slug, setting_name, setting_value, hint
        $setting_name = ucwords(str_replace('_', ' ', $slug));
        $query = "INSERT INTO " . DB::SYSTEM_SETTINGS . " 
                  (setting_slug, setting_name, setting_value, hint) 
                  VALUES ('{$slug}', '{$setting_name}', '{$value}', 'UI color setting')";
        return $mysqli->query($query);
    }
}
?>

<div class="content-wrapper">
        <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1">
                <h1 class="h5 mb-0 d-inline-flex align-items-center gap-2">
                    <a href="listing_<?php echo $module; ?>.php" class="text-dark">All <?php echo ucwords(str_ireplace('_', " ", $module)); ?></a>
                </h1>
            </div>

            <div class="my-1">
                <?php if (empty($hide_add_button) && isset($module_id) && isset($module) && granted('create', $module_id)) { ?>
                    <a href="<?php echo $module; ?>.php" class="btn btn-primary btn-sm d-inline-flex align-items-center">
                        <i class="ph-plus ph-sm me-2 opacity-75"></i>New
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>
    <!-- /page header -->
    
    <div class="content">
        <?php include('admin_elements/breadcrumb.php'); ?>

        <form method="POST" class="form-horizontal" id="colorSettingsForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[$project_pre]['csrf_token']; ?>">
            
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fa fa-palette"></i> UI Color Settings</h5>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa fa-save"></i> Save All Changes
                    </button>
                </div>
                
                <div class="card-body p-0">
                    <!-- Tabs Navigation -->
                    <ul class="nav nav-tabs px-3 pt-2" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#tab-interface" role="tab">
                                <i class="fa fa-desktop"></i> Interface
                            </a>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content p-3">
                        
                        <!-- Interface Tab -->
                        <div class="tab-pane fade show active" id="tab-interface" role="tabpanel">
                            <div class="row">
                                
                                <!-- Admin Header -->
                                <div class="col-md-4 mb-3">
                                    <div class="color-group">
                                        <h6 class="text-muted mb-2"><i class="fa fa-bars"></i> Admin Header</h6>
                                        <div class="color-item mb-2">
                                            <label class="form-label-sm">Background</label>
                                            <div class="input-group input-group-sm">
                                                <input type="color" name="admin_header_bg_color" class="form-control-color-sm" value="<?php echo htmlspecialchars($headerColors['background']); ?>">
                                                <span class="input-group-text-sm"><?php echo htmlspecialchars($headerColors['background']); ?></span>
                                            </div>
                                        </div>
                                        <div class="color-item mb-2">
                                            <label class="form-label-sm">Text</label>
                                            <div class="input-group input-group-sm">
                                                <input type="color" name="admin_header_text_color" class="form-control-color-sm" value="<?php echo htmlspecialchars($headerColors['text']); ?>">
                                                <span class="input-group-text-sm"><?php echo htmlspecialchars($headerColors['text']); ?></span>
                                            </div>
                                        </div>
                                        <div class="color-item">
                                            <label class="form-label-sm">Accent</label>
                                            <div class="input-group input-group-sm">
                                                <input type="color" name="admin_header_accent_color" class="form-control-color-sm" value="<?php echo htmlspecialchars($headerColors['accent']); ?>">
                                                <span class="input-group-text-sm"><?php echo htmlspecialchars($headerColors['accent']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sidebar -->
                                <div class="col-md-4 mb-3">
                                    <div class="color-group">
                                        <h6 class="text-muted mb-2"><i class="fa fa-list"></i> Sidebar</h6>
                                        <div class="color-item mb-2">
                                            <label class="form-label-sm">Background</label>
                                            <div class="input-group input-group-sm">
                                                <input type="color" name="sidebar_bg_color" class="form-control-color-sm" value="<?php echo htmlspecialchars($sidebarColors['background']); ?>">
                                                <span class="input-group-text-sm"><?php echo htmlspecialchars($sidebarColors['background']); ?></span>
                                            </div>
                                        </div>
                                        <div class="color-item mb-2">
                                            <label class="form-label-sm">Text</label>
                                            <div class="input-group input-group-sm">
                                                <input type="color" name="sidebar_text_color" class="form-control-color-sm" value="<?php echo htmlspecialchars($sidebarColors['text']); ?>">
                                                <span class="input-group-text-sm"><?php echo htmlspecialchars($sidebarColors['text']); ?></span>
                                            </div>
                                        </div>
                                        <div class="color-item mb-2">
                                            <label class="form-label-sm">Active BG</label>
                                            <div class="input-group input-group-sm">
                                                <input type="color" name="sidebar_active_bg_color" class="form-control-color-sm" value="<?php echo htmlspecialchars($sidebarColors['active_bg']); ?>">
                                                <span class="input-group-text-sm"><?php echo htmlspecialchars($sidebarColors['active_bg']); ?></span>
                                            </div>
                                        </div>
                                        <div class="color-item mb-2">
                                            <label class="form-label-sm">Active Text</label>
                                            <div class="input-group input-group-sm">
                                                <input type="color" name="sidebar_active_text_color" class="form-control-color-sm" value="<?php echo htmlspecialchars($sidebarColors['active_text']); ?>">
                                                <span class="input-group-text-sm"><?php echo htmlspecialchars($sidebarColors['active_text']); ?></span>
                                            </div>
                                        </div>
                                        <div class="color-item">
                                            <label class="form-label-sm">Hover BG</label>
                                            <div class="input-group input-group-sm">
                                                <input type="color" name="sidebar_hover_bg_color" class="form-control-color-sm" value="<?php echo htmlspecialchars($sidebarColors['hover_bg']); ?>">
                                                <span class="input-group-text-sm"><?php echo htmlspecialchars($sidebarColors['hover_bg']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Login Page -->
                                <div class="col-md-4 mb-3">
                                    <div class="color-group">
                                        <h6 class="text-muted mb-2"><i class="fa fa-sign-in-alt"></i> Login Page</h6>
                                        <div class="color-item mb-2">
                                            <label class="form-label-sm">Header BG</label>
                                            <div class="input-group input-group-sm">
                                                <input type="color" name="login_header_bg_color" class="form-control-color-sm" value="<?php echo htmlspecialchars($loginColors['header_bg']); ?>">
                                                <span class="input-group-text-sm"><?php echo htmlspecialchars($loginColors['header_bg']); ?></span>
                                            </div>
                                        </div>
                                        <div class="color-item mb-2">
                                            <label class="form-label-sm">Header Text</label>
                                            <div class="input-group input-group-sm">
                                                <input type="color" name="login_header_text_color" class="form-control-color-sm" value="<?php echo htmlspecialchars($loginColors['header_text']); ?>">
                                                <span class="input-group-text-sm"><?php echo htmlspecialchars($loginColors['header_text']); ?></span>
                                            </div>
                                        </div>
                                        <div class="color-item mb-2">
                                            <label class="form-label-sm">Form BG</label>
                                            <div class="input-group input-group-sm">
                                                <input type="color" name="login_form_bg_color" class="form-control-color-sm" value="<?php echo htmlspecialchars($loginColors['form_bg']); ?>">
                                                <span class="input-group-text-sm"><?php echo htmlspecialchars($loginColors['form_bg']); ?></span>
                                            </div>
                                        </div>
                                        <div class="color-item mb-2">
                                            <label class="form-label-sm">Button BG</label>
                                            <div class="input-group input-group-sm">
                                                <input type="color" name="login_button_bg_color" class="form-control-color-sm" value="<?php echo htmlspecialchars($loginColors['button_bg']); ?>">
                                                <span class="input-group-text-sm"><?php echo htmlspecialchars($loginColors['button_bg']); ?></span>
                                            </div>
                                        </div>
                                        <div class="color-item mb-2">
                                            <label class="form-label-sm">Button Text</label>
                                            <div class="input-group input-group-sm">
                                                <input type="color" name="login_button_text_color" class="form-control-color-sm" value="<?php echo htmlspecialchars($loginColors['button_text']); ?>">
                                                <span class="input-group-text-sm"><?php echo htmlspecialchars($loginColors['button_text']); ?></span>
                                            </div>
                                        </div>
                                        <div class="color-item">
                                            <label class="form-label-sm">Button Hover</label>
                                            <div class="input-group input-group-sm">
                                                <input type="color" name="login_button_hover_color" class="form-control-color-sm" value="<?php echo htmlspecialchars($loginColors['button_hover']); ?>">
                                                <span class="input-group-text-sm"><?php echo htmlspecialchars($loginColors['button_hover']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>

                <!-- Sticky Footer -->
                <div class="card-footer bg-light border-top sticky-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            <i class="fa fa-info-circle"></i> Changes will apply immediately after saving
                        </div>
                        <div>
                            <button type="reset" class="btn btn-secondary btn-sm me-2">
                                <i class="fa fa-undo"></i> Reset
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-save"></i> Save All Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
/* Compact Color Picker Styles */
.form-control-color-sm {
    width: 45px;
    height: 32px;
    padding: 2px;
    border-radius: 4px;
    cursor: pointer;
}

.input-group-sm {
    display: flex;
    align-items: center;
}

.input-group-text-sm {
    font-family: 'Courier New', monospace;
    font-size: 11px;
    font-weight: 600;
    padding: 4px 8px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-left: none;
    border-radius: 0 4px 4px 0;
    min-width: 75px;
    text-align: center;
}

.form-label-sm {
    font-size: 12px;
    font-weight: 500;
    margin-bottom: 4px;
    color: #495057;
}

.color-group {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

.color-group h6 {
    font-size: 13px;
    font-weight: 600;
    padding-bottom: 8px;
    border-bottom: 2px solid #dee2e6;
    margin-bottom: 12px;
}

.color-item {
    margin-bottom: 0;
}

/* Tabs Styling */
.nav-tabs {
    border-bottom: 2px solid #dee2e6;
    margin-bottom: 0;
}

.nav-tabs .nav-link {
    color: #6c757d;
    border: none;
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
}

.nav-tabs .nav-link:hover {
    color: #3ba1ff;
    background-color: #f8f9fa;
}

.nav-tabs .nav-link.active {
    color: #3ba1ff;
    background-color: #fff;
    border-bottom: 2px solid #3ba1ff;
    font-weight: 600;
}

.nav-tabs .nav-link i {
    margin-right: 6px;
}

/* Sticky Footer */
.sticky-footer {
    position: sticky;
    bottom: 0;
    background: #fff;
    border-top: 2px solid #dee2e6;
    z-index: 100;
    box-shadow: 0 -2px 8px rgba(0,0,0,0.05);
}

/* Card Adjustments */
.card-body.p-0 {
    overflow: hidden;
}

.tab-content {
    min-height: 300px;
    max-height: calc(100vh - 350px);
    overflow-y: auto;
}

/* Compact Alert Messages */
.alert {
    padding: 10px 15px;
    margin-bottom: 15px;
    font-size: 13px;
}

/* Better color preview on hover */
.form-control-color-sm:hover {
    transform: scale(1.1);
    transition: transform 0.2s;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .color-group {
        padding: 10px;
    }
    
    .nav-tabs .nav-link {
        padding: 8px 12px;
        font-size: 12px;
    }
    
    .tab-content {
        max-height: calc(100vh - 300px);
    }
}
</style>

    <?php include('admin_elements/copyright.php'); ?>
</div>
<?php include('admin_elements/admin_footer.php'); ?>

<script>
$(document).ready(function() {
    // Update the text display when color picker value changes
    $('input[type="color"]').on('change input', function() {
        const colorValue = $(this).val().toUpperCase();
        $(this).siblings('.input-group-text-sm').text(colorValue);
    });

    // Form submission confirmation
    $('#colorSettingsForm').on('submit', function(e) {
        // Show loading indicator on all submit buttons
        const submitBtns = $(this).find('button[type="submit"]');
        const originalText = submitBtns.first().html();
        
        submitBtns.html('<i class="fa fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
        
        // Re-enable after a delay (form will actually submit)
        setTimeout(function() {
            submitBtns.html(originalText).prop('disabled', false);
        }, 3000);
    });

    // Smooth scroll on tab change
    $('.nav-tabs a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = $(e.target).attr('href');
        $(target).fadeIn(200);
    });

    // Add keyboard shortcut Ctrl+S to save
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            $('#colorSettingsForm').submit();
        }
    });

    // Show unsaved changes warning
    let formChanged = false;
    $('input[type="color"]').on('change', function() {
        formChanged = true;
    });

    $(window).on('beforeunload', function() {
        if (formChanged) {
            return 'You have unsaved changes. Are you sure you want to leave?';
        }
    });

    $('#colorSettingsForm').on('submit', function() {
        formChanged = false;
    });

    // Add tooltip showing color name on hover (optional enhancement)
    $('input[type="color"]').attr('title', 'Click to change color');
