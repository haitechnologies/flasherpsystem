<?php

use App\Core\DB;
use App\Core\Session;
use App\Security\Roles;
/**
 * Organization Roles Listing
 * 
 * Lists all roles for the active organization with bulk actions and quick-delete.
 */

include('admin_elements/admin_header.php');

$module = 'organization_roles';
$module_caption = 'Organization Roles';

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
*/
$canManageRoles = dashboardUserIsOrganizationOwner($activeOrganizationId, (int)Session::userId()) || 
                  Roles::currentUserHasFullAccess();

if (!$canManageRoles) {
    $error_message = 'You do not have permission to manage roles for this organization.';
}

// Get organization name for display
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

/*
|--------------------------------------------------------------------------
| CSRF TOKEN VALIDATION
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in listing_organization_roles.php', 'WARNING', __FILE__, __LINE__);
    }
}

/*
|--------------------------------------------------------------------------
| HANDLE DELETE ACTION
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
                // Check if members have this role
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
                                header("Location:listing_$module.php");
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
                    <div>
                        <h5 class="mb-1">Organization Roles</h5>
                        <small class="text-muted">Manage roles for: <strong><?php echo htmlspecialchars($orgName); ?></strong></small>
                    </div>
                    <?php if ($canManageRoles) { ?>
                        <a href="organization_roles.php" class="btn btn-primary btn-sm">
                            <i class="ph-plus me-2"></i>Create Role
                        </a>
                    <?php } ?>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
<table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="50">ID</th>
                                <th>Role Name</th>
                                <th>Description</th>
                                <th width="80">Members</th>
                                <th width="80">Status</th>
                                <th width="150">Created</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rolesStmt = $mysqli->prepare(
                                "SELECT 
                                    r.id, 
                                    r.role_name, 
                                    r.description, 
                                    r.is_active, 
                                    r.created_at,
                                    COUNT(DISTINCT mr.membership_id) as member_count
                                 FROM `" . DB::ORGANIZATION_ROLES . "` r
                                 LEFT JOIN `" . DB::ORGANIZATION_MEMBER_ROLES . "` mr ON r.id = mr.role_id
                                 WHERE r.organization_id = ?
                                 GROUP BY r.id
                                 ORDER BY r.role_name ASC"
                            );

                            $roles = [];
                            if ($rolesStmt) {
                                $rolesStmt->bind_param('i', $activeOrganizationId);
                                $rolesStmt->execute();
                                $rolesResult = $rolesStmt->get_result();
                                while ($role = $rolesResult->fetch_assoc()) {
                                    $roles[] = $role;
                                }
                                $rolesStmt->close();
                            }

                            if (empty($roles)) {
                                echo '<tr><td colspan="7" class="text-center text-muted py-4">No roles found. Create your first role to get started.</td></tr>';
                            } else {
                                foreach ($roles as $role) {
                                    $roleId = (int)$role['id'];
                                    $roleName = htmlspecialchars($role['role_name'] ?? '');
                                    $roleDesc = $role['description'] ?? '';
                                    $isActive = (int)($role['is_active'] ?? 0);
                                    $memberCount = (int)($role['member_count'] ?? 0);
                                    $createdAt = !empty($role['created_at']) ? timeAgo($role['created_at']) : 'Unknown';
                                    
                                    // Truncate description
                                    if (strlen($roleDesc) > 60) {
                                        $roleDesc = substr($roleDesc, 0, 60) . '...';
                                    }
                                    $roleDesc = htmlspecialchars($roleDesc);
                                    ?>
                                    <tr>
                                        <td><?php echo $roleId; ?></td>
                                        <td><strong><?php echo $roleName; ?></strong></td>
                                        <td><small><?php echo $roleDesc; ?></small></td>
                                        <td><span class="badge bg-info"><?php echo $memberCount; ?></span></td>
                                        <td>
                                            <?php if ($isActive) { ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php } else { ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php } ?>
                                        </td>
                                        <td><small><?php echo $createdAt; ?></small></td>
                                        <td>
                                            <?php if ($canManageRoles) { ?>
                                                <a href="organization_roles.php?action=edit_<?php echo $module; ?>&id=<?php echo $roleId; ?>" 
                                                   class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                                    <i class="ph-pencil"></i>
                                                </a>
                                                <form method="GET" action="listing_<?php echo $module; ?>.php" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete_<?php echo $module; ?>">
                                                    <input type="hidden" name="id" value="<?php echo $roleId; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                            title="Delete" 
                                                            onclick="return confirm('Are you sure you want to delete this role?');">
                                                        <i class="ph-trash"></i>
                                                    </button>
                                                </form>
                                            <?php } else { ?>
                                                <span class="text-muted small">No permissions</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            }
                            ?>
                        </tbody>
                    </table>
</div>
                </div>
            </div>

            <div class="alert alert-info mt-3" role="alert">
                <strong>Tip:</strong> Roles are used to organize permissions for organization members. Create roles that match your organizational structure, then assign members to roles during the invite or membership management process.
            </div>
        </div>

        <?php include('admin_elements/copyright.php'); ?>
    </div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>
