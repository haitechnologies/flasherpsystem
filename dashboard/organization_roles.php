<?php

use App\Core\DB;
use App\Core\Session;
use App\Security\Roles;
/**
 * Organization Roles Management
 * 
 * Create, edit, and delete roles specific to the active organization.
 * Roles are used to control permissions for organization members.
 */

include('admin_elements/admin_header.php');

$module = 'organization_roles';
$module_caption = 'Organization Role';

$error_message = '';
$success_message = '';

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
*/
include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

/*
|--------------------------------------------------------------------------
| AUTHORIZATION CHECK
|--------------------------------------------------------------------------
| Only org owners and admins can manage roles for their organization
*/
$canManageRoles = dashboardUserIsOrganizationOwner($activeOrganizationId, (int)Session::userId()) || 
                  Roles::currentUserHasFullAccess();

if (!$canManageRoles) {
    $error_message = 'You do not have permission to manage roles for this organization.';
}

// Get organization name
$orgName = 'Organization';
$orgStmt = $mysqli->prepare("SELECT warehouse_name FROM `" . DB::ORGANIZATIONS . "` WHERE id = ? LIMIT 1");
if ($orgStmt) {
    $orgStmt->bind_param('i', $activeOrganizationId);
    $orgStmt->execute();
    $orgRow = $orgStmt->get_result()->fetch_assoc();
    $orgStmt->close();
    if (!empty($orgRow['warehouse_name'])) {
        $orgName = (string)$orgRow['warehouse_name'];
    }
}

$action = isset($_GET['action']) ? e_s__($_GET['action']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : '';
$role_name = '';
$role_description = '';
$is_active = 0;

/*
|--------------------------------------------------------------------------
| CSRF TOKEN VALIDATION
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in organization_roles.php', 'WARNING', __FILE__, __LINE__);
    }
}

/*
|--------------------------------------------------------------------------
| GET FORM DATA
|--------------------------------------------------------------------------
*/
if ($action == "update_$module" || $action == "add_$module") {
    if ($error_message === '') {
        $role_name = e_s__($_POST['role_name'] ?? '');
        $role_description = e_s__($_POST['role_description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
    }
}

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/
if ($action == "delete_$module" && !empty($id) && !$error_message) {
    if (!$canManageRoles) {
        $error_message = 'You do not have permission to delete roles.';
    } else {
        // Verify role belongs to this organization
        $roleStmt = $mysqli->prepare("SELECT id FROM `" . DB::ORGANIZATION_ROLES . "` WHERE id = ? AND organization_id = ? LIMIT 1");
        if ($roleStmt) {
            $roleStmt->bind_param('ii', $id, $activeOrganizationId);
            $roleStmt->execute();
            $roleExists = $roleStmt->get_result()->fetch_assoc();
            $roleStmt->close();

            if ($roleExists) {
                // Cannot delete if members have this role
                $memberCountStmt = $mysqli->prepare(
                    "SELECT COUNT(*) as cnt FROM `" . DB::ORGANIZATION_MEMBER_ROLES . "` WHERE role_id = ?"
                );
                if ($memberCountStmt) {
                    $memberCountStmt->bind_param('i', $id);
                    $memberCountStmt->execute();
                    $memberCountRow = $memberCountStmt->get_result()->fetch_assoc();
                    $memberCountStmt->close();

                    $memberCount = (int)($memberCountRow['cnt'] ?? 0);
                    if ($memberCount > 0) {
                        $error_message = 'Cannot delete this role because ' . $memberCount . ' member(s) have this role assigned.';
                    } else {
                        $deleteStmt = $mysqli->prepare("DELETE FROM `" . DB::ORGANIZATION_ROLES . "` WHERE id = ? AND organization_id = ?");
                        if ($deleteStmt) {
                            $deleteStmt->bind_param('ii', $id, $activeOrganizationId);
                            if ($deleteStmt->execute()) {
                                $success_message = "Role deleted successfully.";
                                flash_success($success_message);
                                header("Location:listing_organization_roles.php");
                                exit;
                            } else {
                                $error_message = "Failed to delete role. Please try again.";
                            }
                            $deleteStmt->close();
                        }
                    }
                }
            } else {
                $error_message = 'Role not found or does not belong to this organization.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| UPDATE
|--------------------------------------------------------------------------
*/
if ($action == "update_$module" && !empty($id) && !$error_message) {
    if (!$canManageRoles) {
        $error_message = 'You do not have permission to update roles.';
    } else if (empty($role_name)) {
        $error_message = 'Role name is required.';
    } else {
        // Verify role belongs to this organization
        $roleStmt = $mysqli->prepare("SELECT id FROM `" . DB::ORGANIZATION_ROLES . "` WHERE id = ? AND organization_id = ? LIMIT 1");
        if ($roleStmt) {
            $roleStmt->bind_param('ii', $id, $activeOrganizationId);
            $roleStmt->execute();
            $roleExists = $roleStmt->get_result()->fetch_assoc();
            $roleStmt->close();

            if ($roleExists) {
                $updateStmt = $mysqli->prepare(
                    "UPDATE `" . DB::ORGANIZATION_ROLES . "` 
                     SET role_name = ?, description = ?, is_active = ?, updated_at = NOW(), updated_by = ? 
                     WHERE id = ? AND organization_id = ?"
                );
                if ($updateStmt) {
                    $updateStmt->bind_param('ssiiii', $role_name, $role_description, $is_active, Session::userId(), $id, $activeOrganizationId);
                        if ($updateStmt->execute()) {
                            $success_message = "Role updated successfully.";
                            flash_success($success_message);
                            header("Location:listing_organization_roles.php");
                            exit;
                    } else {
                        $error_message = "Failed to update role. Please try again.";
                    }
                    $updateStmt->close();
                }
            } else {
                $error_message = 'Role not found or does not belong to this organization.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| CREATE
|--------------------------------------------------------------------------
*/
if ($action == "add_$module" && !$error_message) {
    if (!$canManageRoles) {
        $error_message = 'You do not have permission to create roles.';
    } else if (empty($role_name)) {
        $error_message = 'Role name is required.';
    } else {
        // Check for duplicate role name within organization
        $dupStmt = $mysqli->prepare(
            "SELECT id FROM `" . DB::ORGANIZATION_ROLES . "` 
             WHERE organization_id = ? AND LOWER(role_name) = LOWER(?) LIMIT 1"
        );
        if ($dupStmt) {
            $dupStmt->bind_param('is', $activeOrganizationId, $role_name);
            $dupStmt->execute();
            $dupExists = $dupStmt->get_result()->fetch_assoc();
            $dupStmt->close();

            if ($dupExists) {
                $error_message = 'A role with this name already exists in this organization.';
            } else {
                $insertStmt = $mysqli->prepare(
                    "INSERT INTO `" . DB::ORGANIZATION_ROLES . "` 
                     (organization_id, role_name, description, is_active, created_by, created_at) 
                     VALUES (?, ?, ?, ?, ?, NOW())"
                );
                if ($insertStmt) {
                    $insertStmt->bind_param('issii', $activeOrganizationId, $role_name, $role_description, $is_active, Session::userId());
                        if ($insertStmt->execute()) {
                            $success_message = "Role created successfully.";
                            flash_success($success_message);
                            header("Location:listing_organization_roles.php");
                            exit;
                    } else {
                        $error_message = "Failed to create role. Please try again.";
                    }
                    $insertStmt->close();
                }
            }
        }
    }
}

// Load existing role if editing
if ($action == "edit_$module" && !empty($id) && empty($_POST)) {
    $roleStmt = $mysqli->prepare(
        "SELECT role_name, description, is_active FROM `" . DB::ORGANIZATION_ROLES . "` 
         WHERE id = ? AND organization_id = ? LIMIT 1"
    );
    if ($roleStmt) {
        $roleStmt->bind_param('ii', $id, $activeOrganizationId);
        $roleStmt->execute();
        $roleRow = $roleStmt->get_result()->fetch_assoc();
        $roleStmt->close();

        if ($roleRow) {
            $role_name = $roleRow['role_name'];
            $role_description = $roleRow['description'];
            $is_active = (int)$roleRow['is_active'];
        } else {
            $error_message = 'Role not found or does not belong to this organization.';
            $action = '';
        }
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

    <div class="content-inner">
        <div class="content">
            <?php include('admin_elements/breadcrumb.php'); ?>

            <?php if (!empty($error_message)) { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php } ?>

            <?php if (!empty($success_message)) { ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Success:</strong> <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php } ?>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <?php 
                        if ($action == "edit_$module") {
                            echo 'Edit ' . htmlspecialchars($module_caption) . ' - ' . htmlspecialchars($orgName);
                        } else {
                            echo 'Create ' . htmlspecialchars($module_caption) . ' - ' . htmlspecialchars($orgName);
                        }
                        ?>
                    </h5>
                    <a href="listing_organization_roles.php" class="btn btn-secondary btn-sm">
                        <i class="ph-arrow-left me-2"></i>Back to Roles
                    </a>
                </div>

                <div class="card-body">
                    <form method="POST" action="organization_roles.php<?php echo (!empty($id) ? '?action=edit_' . $module . '&id=' . $id : ''); ?>">
                        <?php echo csrf_field(); ?>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="role_name" class="form-label">Role Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="role_name" name="role_name" 
                                           value="<?php echo htmlspecialchars($role_name); ?>" 
                                           placeholder="e.g., Manager, Supervisor, Member" required>
                                    <small class="form-text text-muted">Enter a descriptive role name for your organization.</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                               value="1" <?php echo ($is_active ? 'checked' : ''); ?>>
                                        <label class="form-check-label" for="is_active">
                                            Active
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="form-group mb-3">
                                    <label for="role_description" class="form-label">Description</label>
                                    <textarea class="form-control" id="role_description" name="role_description" 
                                              rows="4" placeholder="Describe the purpose and permissions of this role..."><?php echo htmlspecialchars($role_description); ?></textarea>
                                    <small class="form-text text-muted">Optional: Help team members understand what this role is for.</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <?php if ($action == "edit_$module") { ?>
                                <button type="submit" name="action" value="update_<?php echo $module; ?>" class="btn btn-primary">
                                    <i class="ph-check me-2"></i>Update Role
                                </button>
                            <?php } else { ?>
                                <button type="submit" name="action" value="add_<?php echo $module; ?>" class="btn btn-success">
                                    <i class="ph-plus me-2"></i>Create Role
                                </button>
                            <?php } ?>
                            <a href="listing_organization_roles.php" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($action == "edit_$module" && !empty($id)) { ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">Members with this Role</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $membersStmt = $mysqli->prepare(
                            "SELECT om.id, u.full_name, u.email 
                             FROM `" . DB::ORGANIZATION_MEMBER_ROLES . "` omr
                             INNER JOIN `" . DB::ORGANIZATION_MEMBERSHIPS . "` om ON om.id = omr.membership_id
                             INNER JOIN `" . DB::USERS . "` u ON u.id = om.user_id
                             WHERE omr.role_id = ? AND om.organization_id = ?
                             ORDER BY u.full_name ASC"
                        );

                        $members = [];
                        if ($membersStmt) {
                            $membersStmt->bind_param('ii', $id, $activeOrganizationId);
                            $membersStmt->execute();
                            $membersResult = $membersStmt->get_result();
                            while ($member = $membersResult->fetch_assoc()) {
                                $members[] = $member;
                            }
                            $membersStmt->close();
                        }

                        if (empty($members)) {
                            echo '<p class="text-muted mb-0">No members currently have this role.</p>';
                        } else {
                            ?>
                            <div class="table-responsive">
<table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Member Name</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member) { ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
</div>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            <?php } ?>
        </div>

        <?php include('admin_elements/copyright.php'); ?>
    </div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>
