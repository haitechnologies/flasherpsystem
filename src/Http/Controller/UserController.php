<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\UserService;
use App\Service\UserDocumentService;
use App\Service\AirTicketService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class UserController extends BaseController
{
    private UserService $userService;
    private UserDocumentService $documentService;
    private AirTicketService $airTicketService;
    private string $projectPre;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        UserService $userService,
        UserDocumentService $documentService,
        AirTicketService $airTicketService,
        string $projectPre = '',
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->userService = $userService;
        $this->documentService = $documentService;
        $this->airTicketService = $airTicketService;
        $this->projectPre = $projectPre;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('users', 'Employee');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_users' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_users' && $this->canCreate()
                => $this->handleCreate($request),
            $request->isPost() && $action === 'list_user_docs' && $id > 0
                => $this->handleListUserDocs($id),
            $request->isPost() && $action === 'upload_user_doc' && $this->canEdit()
                => $this->handleUploadUserDoc($request),
            $request->isPost() && $action === 'update_user_doc_dates' && $id > 0 && $this->canEdit()
                => $this->handleUpdateUserDocDates($request, $id),
            $request->isPost() && $action === 'delete_user_doc' && $id > 0 && $this->canEdit()
                => $this->handleDeleteUserDoc($request, $id),
            $request->isPost() && $action === 'list_user_air_tickets' && $id > 0
                => $this->handleListUserAirTickets($id),
            $request->isPost() && $action === 'add_user_air_ticket' && $this->canEdit()
                => $this->handleAddUserAirTicket($request),
            $request->isPost() && $action === 'update_user_air_ticket' && $id > 0 && $this->canEdit()
                => $this->handleUpdateUserAirTicket($request, $id),
            $request->isPost() && $action === 'delete_user_air_ticket' && $id > 0 && $this->canEdit()
                => $this->handleDeleteUserAirTicket($request, $id),
            default => $this->showForm($request, $id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $data = [
            'role_id' => $request->getString('role_id'),
            'first_name' => $request->getString('first_name'),
            'last_name' => $request->getString('last_name'),
            'email' => $request->getString('email'),
            'password' => $request->getString('password'),
            'contact1' => $this->prependCountryCode($request->getString('contact1'), '+971'),
            'contact2' => $this->prependCountryCode($request->getString('contact2'), '+92'),
            'address' => $request->getString('address'),
            'dob' => $request->getString('dob'),
            'date_of_joining' => $this->convertDateToDb($request->getString('date_of_joining')),
            'department_id' => $request->getString('department_id'),
            'designation_id' => $request->getString('designation_id'),
            'can_access_system' => $request->get('can_access_system') ? true : false,
            'is_active' => $request->get('is_active') ? true : false,
        ];

        $isFullAccess = function_exists('has_full_access') && has_full_access();
        $canManageSystemAccess = $isFullAccess || in_array($this->roleId, [\App\Security\Roles::ACCOUNTS], true);
        if (!$isFullAccess) {
            unset($data['password']);
        }
        if (!$canManageSystemAccess) {
            unset($data['role_id'], $data['can_access_system'], $data['is_active']);
        }

        try {
            $this->userService->update($id, $data);
            flash_success('Employee profile updated successfully.');
            return Response::redirect('listing_users.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("users.php?id=$id&action=edit_users");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("users.php?id=$id&action=edit_users");
        } catch (\Throwable $e) {
            $this->logError("UserController::handleUpdate error: " . $e->getMessage());
            flash_error('Unable to update employee profile: ' . get_class($e) . ': ' . $e->getMessage());
            return Response::redirect("users.php?id=$id&action=edit_users");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $data = [
            'role_id' => $request->getString('role_id'),
            'first_name' => $request->getString('first_name'),
            'last_name' => $request->getString('last_name'),
            'email' => $request->getString('email'),
            'password' => $request->getString('password'),
            'contact1' => $this->prependCountryCode($request->getString('contact1'), '+971'),
            'contact2' => $this->prependCountryCode($request->getString('contact2'), '+92'),
            'address' => $request->getString('address'),
            'dob' => $request->getString('dob'),
            'date_of_joining' => $this->convertDateToDb($request->getString('date_of_joining')),
            'department_id' => $request->getString('department_id'),
            'designation_id' => $request->getString('designation_id'),
            'can_access_system' => $request->get('can_access_system') ? true : false,
            'is_active' => $request->get('is_active') ? true : false,
        ];

        $isFullAccess = function_exists('has_full_access') && has_full_access();
        $canManageSystemAccess = $isFullAccess || in_array($this->roleId, [\App\Security\Roles::ACCOUNTS], true);
        if (!$canManageSystemAccess) {
            $data['role_id'] = (string)$this->roleId;
            unset($data['can_access_system'], $data['is_active']);
        }

        try {
            $newUser = $this->userService->create($data, $this->userId);
            flash_success('Employee account created successfully.');
            return Response::redirect('listing_users.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("users.php");
        } catch (\Throwable $e) {
            $this->logError("UserController::handleCreate error: " . $e->getMessage());
            flash_error('Unable to create employee account.');
            return Response::redirect("users.php");
        }
    }

    private function showForm(Request $request, int $id): Response
    {
        $roleId = '';
        $firstName = '';
        $lastName = '';
        $email = '';
        $password = '';
        $contact1 = '';
        $contact2 = '';
        $address = '';
        $dob = '';
        $dateOfJoining = '';
        $departmentId = '';
        $designationId = '';
        $canAccessSystem = 1;
        $isActive = 0;
        $error_message = '';
        $moduleCaption = $this->moduleCaption;
        $module = 'users';
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;

        $userDocuments = [];
        $documentCategories = [];

        if ($id > 0) {
            try {
                $user = $this->userService->getById($id);
                $roleId = (string)$user->roleId;
                $firstName = $user->firstName ?? '';
                $lastName = $user->lastName ?? '';
                $email = $user->email;
                $password = $user->password ?? '';
                $contact1 = $user->contact1 !== null ? ltrim(str_replace('+971', '', $user->contact1), ' :') : '';
                $contact2 = $user->contact2 !== null ? ltrim(str_replace('+92', '', $user->contact2), ' :') : '';
                $address = $user->address ?? '';
                $dob = $user->dob && $user->dob !== '1970-01-01' ? date('d-m-Y', strtotime($user->dob)) : '';
                $dateOfJoining = $user->dateOfJoining && $user->dateOfJoining !== '1970-01-01' ? date('d-m-Y', strtotime($user->dateOfJoining)) : '';
                $departmentId = (string)($user->departmentId ?? '');
                $designationId = (string)($user->designationId ?? '');
                $canAccessSystem = $user->canAccessSystem ? 1 : 0;
                $isActive = $user->isActive ? 1 : 0;
                $userDocuments = $this->documentService->getDocumentsByUser($id, $this->orgId);
                $docCats = $this->db->fetchAll("SELECT id, document_category, is_mandatory FROM `" . DB::DOCUMENT_CATEGORIES . "` WHERE is_active=1 AND document_category_type='employees' ORDER BY is_mandatory DESC, document_category ASC");
                foreach ($docCats as $dc) {
                    $documentCategories[] = $dc;
                }
                $uploadedCatIds = array_map(fn($d) => $d->documentType, $userDocuments);
                $missingMandatoryDocs = [];
                foreach ($documentCategories as $dc) {
                    if (!empty($dc['is_mandatory']) && !in_array((int)$dc['id'], $uploadedCatIds)) {
                        $missingMandatoryDocs[] = htmlspecialchars($dc['document_category']);
                    }
                }
            } catch (NotFoundException $e) {
                $error_message = $e->getMessage();
            }
        }

        $uploadPath = '../uploads/user_documents/';

        $isFullAccess = function_exists('has_full_access') && has_full_access();
        $canManageSystemAccess = $isFullAccess || in_array($this->roleId, [\App\Security\Roles::ACCOUNTS], true);

        $rolesHtml = '';
        $currentRoleName = '';
        $roleRows = $this->db->fetchAll("SELECT * FROM DB::ROLES WHERE is_active = 1 AND id > 2 ORDER BY role_name ASC");
        foreach ($roleRows as $rows_roles) {
            if ((string)$rows_roles['id'] === $roleId) {
                $currentRoleName = $rows_roles['role_name'];
            }
            $sel = $rows_roles['id'] == $roleId ? 'selected' : '';
            $rolesHtml .= '<option value="' . $rows_roles['id'] . '" ' . $sel . '>' . htmlspecialchars($rows_roles['role_name']) . '</option>';
        }

        $departmentsHtml = '';
        $deptRows = $this->db->fetchAll("SELECT id, department FROM DB::DEPARTMENTS WHERE publish = 1 ORDER BY department ASC");
        foreach ($deptRows as $dept) {
            $sel = (string)$dept['id'] === $departmentId ? 'selected' : '';
            $departmentsHtml .= '<option value="' . $dept['id'] . '" ' . $sel . '>' . htmlspecialchars($dept['department']) . '</option>';
        }

        $designationsHtml = '';
        $desigRows = $this->db->fetchAll("SELECT id, designation FROM DB::DESIGNATIONS WHERE publish = 1 ORDER BY designation ASC");
        foreach ($desigRows as $desig) {
            $sel = (string)$desig['id'] === $designationId ? 'selected' : '';
            $designationsHtml .= '<option value="' . $desig['id'] . '" ' . $sel . '>' . htmlspecialchars($desig['designation']) . '</option>';
        }

        return Response::html($this->view->render('users/form.php', [
            'id' => $id,
            'roleId' => $roleId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'password' => $password,
            'contact1' => $contact1,
            'contact2' => $contact2,
            'address' => $address,
            'dob' => $dob,
            'dateOfJoining' => $dateOfJoining,
            'canAccessSystem' => $canAccessSystem,
            'isActive' => $isActive,
            'error_message' => $error_message,
            'moduleCaption' => $moduleCaption,
            'module' => $module,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'sessionRoleId' => $this->roleId,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
            'canManageSystemAccess' => $canManageSystemAccess,
            'rolesHtml' => $rolesHtml,
            'currentRoleName' => $currentRoleName,
            'departmentId' => $departmentId,
            'designationId' => $designationId,
            'departmentsHtml' => $departmentsHtml,
            'designationsHtml' => $designationsHtml,
            'userDocuments' => $userDocuments,
            'documentCategories' => $documentCategories,
            'missingMandatoryDocs' => $missingMandatoryDocs ?? [],
            'uploadPath' => $uploadPath,
        ]));
    }

    private function prependCountryCode(string $value, string $code): string
    {
        $value = trim($value);
        if ($value === '' || str_starts_with($value, '+')) {
            return $value;
        }
        return $code . ' ' . $value;
    }

    private function convertDateToDb(string $dateStr): ?string
    {
        $dateStr = trim($dateStr);
        if ($dateStr === '') {
            return null;
        }
        try {
            $dt = \DateTime::createFromFormat('d-m-Y', $dateStr);
            if ($dt instanceof \DateTime) {
                return $dt->format('Y-m-d');
            }
        } catch (\Throwable $e) {
            $this->logError("UserController::convertDateToDb error: " . $e->getMessage());
        }
        $parts = explode('-', $dateStr);
        if (count($parts) === 3) {
            return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
        return null;
    }

    private function handleListUserDocs(int $userId): Response
    {
        try {
            $docs = $this->documentService->getDocumentsByUser($userId, $this->orgId);
            $categories = [];
            $catRows = $this->db->fetchAll("SELECT id, document_category, is_mandatory FROM `" . DB::DOCUMENT_CATEGORIES . "` WHERE is_active=1 AND document_category_type='employees'");
            foreach ($catRows as $cr) {
                $categories[$cr['id']] = $cr['document_category'];
                $isMandatoryMap[(int)$cr['id']] = !empty($cr['is_mandatory']);
            }
            $isMandatoryMap ??= [];
            $uploadedCatIds = array_map(fn($d) => $d->documentType, $docs);
            $missingMandatoryDocs = [];
            foreach ($catRows as $cr) {
                if (!empty($cr['is_mandatory']) && !in_array((int)$cr['id'], $uploadedCatIds)) {
                    $missingMandatoryDocs[] = htmlspecialchars($cr['document_category']);
                }
            }
            $uploadPath = '../uploads/user_documents/';
            $rows = [];
            foreach ($docs as $doc) {
                $rows[] = [
                    'id' => $doc->id,
                    'category' => $categories[$doc->documentType] ?? 'Uncategorized',
                    'is_mandatory' => $isMandatoryMap[$doc->documentType] ?? false,
                    'filename' => $doc->filename,
                    'original_filename' => $doc->originalFilename,
                    'file_url' => $uploadPath . $doc->filename,
                    'description' => $doc->description,
                    'issued_date' => $doc->issuedDate ? date('d-m-Y', strtotime($doc->issuedDate)) : '',
                    'expiry_date' => $doc->expiryDate ? date('d-m-Y', strtotime($doc->expiryDate)) : '',
                ];
            }
            return Response::json(['success' => true, 'data' => $rows, 'missing_mandatory' => $missingMandatoryDocs]);
        } catch (\Throwable $e) {
            $this->logError("UserController::handleListUserDocs error: " . $e->getMessage());
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function handleUpdateUserDocDates(Request $request, int $id): Response
    {
        try {
            $this->documentService->updateDocumentDates($id, [
                'issued_date' => $request->post('issued_date', ''),
                'expiry_date' => $request->post('expiry_date', ''),
            ], $this->orgId);
            return Response::json(['success' => true]);
        } catch (ValidationException $e) {
            return Response::json(['success' => false, 'error' => current($e->getErrors())], 400);
        } catch (\Throwable $e) {
            $this->logError("UserController::handleUpdateUserDocDates error: " . $e->getMessage());
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function handleUploadUserDoc(Request $request): Response
    {
        $userId = $request->getInt('user_id');
        if ($userId <= 0) {
            return Response::json(['success' => false, 'error' => 'Invalid user ID.'], 400);
        }
        $data = [
            'user_id' => (string)$userId,
            'document_type' => $request->post('document_type', ''),
            'description' => $request->post('description', ''),
            'issued_date' => $request->post('issued_date', ''),
            'expiry_date' => $request->post('expiry_date', ''),
        ];
        $file = $request->getFile('document');
        try {
            $this->documentService->createDocument($data, $file, $this->orgId, $this->userId);
            return Response::json(['success' => true]);
        } catch (\App\Exception\ValidationException $e) {
            return Response::json(['success' => false, 'error' => current($e->getErrors())], 400);
        } catch (\Throwable $e) {
            $this->logError("UserController::handleUploadUserDoc error: " . $e->getMessage());
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function handleDeleteUserDoc(Request $request, int $id): Response
    {
        $isSuperAdmin = Roles::hasFullAccess($this->roleId);
        try {
            $this->documentService->deleteDocument($id, $this->orgId, $this->userId, $isSuperAdmin);
            return Response::json(['success' => true]);
        } catch (\Throwable $e) {
            $this->logError("UserController::handleDeleteUserDoc error: " . $e->getMessage());
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function handleListUserAirTickets(int $userId): Response
    {
        try {
            $tickets = $this->airTicketService->getByEmployee($userId, $this->orgId);
            $rows = [];
            foreach ($tickets as $t) {
                $rows[] = [
                    'id' => $t->id,
                    'entitlement_amount' => $t->entitlementAmount,
                    'status' => $t->status,
                    'eligibility_date' => $t->eligibilityDate ? date('d-m-Y', strtotime($t->eligibilityDate)) : '',
                    'paid_date' => $t->paidDate ? date('d-m-Y', strtotime($t->paidDate)) : '',
                    'departure_date' => $t->departureDate ? date('d-m-Y', strtotime($t->departureDate)) : '',
                    'arrival_date' => $t->arrivalDate ? date('d-m-Y', strtotime($t->arrivalDate)) : '',
                    'ticket_file' => $t->ticketFile,
                    'file_url' => $t->ticketFile ? '../uploads/air_tickets/' . $t->ticketFile : '',
                    'notes' => $t->notes,
                ];
            }
            return Response::json(['success' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            $this->logError("UserController::handleListUserAirTickets error: " . $e->getMessage());
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function handleAddUserAirTicket(Request $request): Response
    {
        $employeeId = $request->getInt('employee_id');
        if ($employeeId <= 0) {
            return Response::json(['success' => false, 'error' => 'Invalid employee.'], 400);
        }

        $data = [
            'employee_id' => $employeeId,
            'entitlement_amount' => (float)($request->get('entitlement_amount', 1250.00)),
            'status' => $request->getString('status', 'pending'),
            'eligibility_date' => $this->convertDateToDb($request->getString('eligibility_date')),
            'departure_date' => $this->convertDateToDb($request->getString('departure_date')),
            'arrival_date' => $this->convertDateToDb($request->getString('arrival_date')),
            'notes' => $request->getString('notes', ''),
        ];

        $file = $request->getFile('ticket_file');
        if ($file !== null && $file['error'] === UPLOAD_ERR_OK) {
            $data['ticket_file'] = $this->handleAirTicketFileUpload($file);
        }

        try {
            $this->airTicketService->create($data, $this->userId, $this->orgId);
            return Response::json(['success' => true]);
        } catch (ValidationException $e) {
            return Response::json(['success' => false, 'error' => current($e->getErrors())], 400);
        } catch (\Throwable $e) {
            $this->logError("UserController::handleAddUserAirTicket error: " . $e->getMessage());
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function handleUpdateUserAirTicket(Request $request, int $id): Response
    {
        $data = [
            'status' => $request->getString('status', ''),
            'departure_date' => $this->convertDateToDb($request->getString('departure_date')),
            'arrival_date' => $this->convertDateToDb($request->getString('arrival_date')),
            'notes' => $request->getString('notes', ''),
        ];
        $data = array_filter($data, fn($v) => $v !== '' && $v !== null);

        $file = $request->getFile('ticket_file');
        if ($file !== null && $file['error'] === UPLOAD_ERR_OK) {
            $data['ticket_file'] = $this->handleAirTicketFileUpload($file);
        }

        try {
            $this->airTicketService->update($id, $data, $this->userId, $this->orgId);
            return Response::json(['success' => true]);
        } catch (ValidationException $e) {
            return Response::json(['success' => false, 'error' => current($e->getErrors())], 400);
        } catch (NotFoundException $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            $this->logError("UserController::handleUpdateUserAirTicket error: " . $e->getMessage());
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function handleDeleteUserAirTicket(Request $request, int $id): Response
    {
        try {
            $this->airTicketService->delete($id, $this->orgId);
            return Response::json(['success' => true]);
        } catch (NotFoundException $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            $this->logError("UserController::handleDeleteUserAirTicket error: " . $e->getMessage());
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function handleAirTicketFileUpload(array $file): string
    {
        $uploadDir = __DIR__ . '/../../../uploads/air_tickets/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        if (!in_array($ext, $allowed, true)) {
            throw new ValidationException(['ticket_file' => 'Only PDF, DOC, DOCX, JPG, PNG files are allowed.']);
        }

        $filename = 'air_ticket_' . uniqid() . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException('Failed to upload ticket file.');
        }

        return $filename;
    }
}
