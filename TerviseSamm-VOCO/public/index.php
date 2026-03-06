<?php
declare(strict_types=1);

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $rel = substr($class, strlen($prefix));
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', $rel) . '.php';
    if (file_exists($path)) require $path;
});

use App\Utils\Env;
use App\Utils\Response;
use App\Utils\Security;
use App\Repositories\Db;
use App\Repositories\UserRepo;
use App\Repositories\EntryRepo;
use App\Repositories\GroupRepo;
use App\Repositories\AccessRepo;
use App\Repositories\AuditRepo;
use App\Repositories\FeedbackRepo;
use App\Repositories\LoginAttemptRepo;
use App\Controllers\AuthController;
use App\Controllers\StudentController;
use App\Controllers\TeacherController;
use App\Controllers\AdminController;
use App\Services\AiFeedbackService;
use App\Services\TrendService;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

Env::load(__DIR__ . '/../config/.env');
Security::startSession();

$pdo = Db::pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = rtrim($uri, '/') ?: '/';

$needsCsrf = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && $path !== '/api/auth/login';
if ($needsCsrf) Security::requireCsrf();

$userRepo = new UserRepo($pdo);
$auditRepo = new AuditRepo($pdo);
$entryRepo = new EntryRepo($pdo);
$groupRepo = new GroupRepo($pdo);
$accessRepo = new AccessRepo($pdo);
$feedbackRepo = new FeedbackRepo($pdo);
$loginAttemptRepo = new LoginAttemptRepo($pdo);

$authController = new AuthController($userRepo, $auditRepo, $accessRepo, $loginAttemptRepo);
$studentController = new StudentController($entryRepo, $feedbackRepo, $auditRepo, new AiFeedbackService());
$teacherController = new TeacherController($accessRepo, $groupRepo, $entryRepo, $userRepo, new TrendService());
$adminController = new AdminController($userRepo, $groupRepo, $accessRepo, $auditRepo, $pdo);

$loginAttemptRepo->clearOldAttempts();

try {
    if ($method === 'POST' && $path === '/api/auth/login') {
        $authController->login();
    }
    if ($method === 'GET' && $path === '/api/auth/me') {
        $authController->me();
    }
    if ($method === 'POST' && $path === '/api/auth/logout') {
        AuthMiddleware::requireLogin();
        $authController->logout();
    }

    if ($method === 'GET' && $path === '/api/student/entries') {
        AuthMiddleware::requireLogin();
        RoleMiddleware::requireRole(['STUDENT']);
        $studentController->listEntries();
    }
    if ($method === 'POST' && $path === '/api/student/entries') {
        AuthMiddleware::requireLogin();
        RoleMiddleware::requireRole(['STUDENT']);
        $studentController->createEntry();
    }
    if (preg_match('#^/api/student/entries/(\d+)$#', $path, $m)) {
        $id = (int)$m[1];
        if ($method === 'PUT') {
            AuthMiddleware::requireLogin();
            RoleMiddleware::requireRole(['STUDENT']);
            $studentController->updateEntry($id);
        }
        if ($method === 'DELETE') {
            AuthMiddleware::requireLogin();
            RoleMiddleware::requireRole(['STUDENT']);
            $studentController->deleteEntry($id);
        }
    }
    if (preg_match('#^/api/student/entries/(\d+)/feedback$#', $path, $m)) {
        if ($method === 'GET') {
            AuthMiddleware::requireLogin();
            RoleMiddleware::requireRole(['STUDENT']);
            $studentController->getFeedback((int)$m[1]);
        }
    }

    if ($method === 'GET' && $path === '/api/teacher/groups') {
        AuthMiddleware::requireLogin();
        RoleMiddleware::requireRole(['TEACHER', 'ADMIN_TEACHER']);
        $teacherController->getGroups();
    }
    if (preg_match('#^/api/teacher/groups/([A-Za-z0-9]+)/summary$#', $path, $m)) {
        if ($method === 'GET') {
            AuthMiddleware::requireLogin();
            RoleMiddleware::requireRole(['TEACHER', 'ADMIN_TEACHER']);
            $teacherController->getGroupSummary($m[1]);
        }
    }
    if (preg_match('#^/api/teacher/groups/([A-Za-z0-9]+)/students$#', $path, $m)) {
        if ($method === 'GET') {
            AuthMiddleware::requireLogin();
            RoleMiddleware::requireRole(['TEACHER', 'ADMIN_TEACHER']);
            $teacherController->getGroupStudents($m[1]);
        }
    }
    if (preg_match('#^/api/teacher/students/(\d+)/recent-entries$#', $path, $m)) {
        if ($method === 'GET') {
            AuthMiddleware::requireLogin();
            RoleMiddleware::requireRole(['TEACHER', 'ADMIN_TEACHER']);
            $teacherController->getStudentRecentEntries((int)$m[1]);
        }
    }

    if ($method === 'GET' && $path === '/api/admin/teachers') {
        AuthMiddleware::requireLogin();
        RoleMiddleware::requireRole(['ADMIN_TEACHER']);
        $adminController->listTeachers();
    }
    if ($method === 'POST' && $path === '/api/admin/teachers') {
        AuthMiddleware::requireLogin();
        RoleMiddleware::requireRole(['ADMIN_TEACHER']);
        $adminController->createTeacher();
    }
    if (preg_match('#^/api/admin/teachers/(\d+)$#', $path, $m)) {
        if ($method === 'PATCH') {
            AuthMiddleware::requireLogin();
            RoleMiddleware::requireRole(['ADMIN_TEACHER']);
            $adminController->patchTeacher((int)$m[1]);
        }
    }
    if (preg_match('#^/api/admin/teachers/(\d+)/reset-password$#', $path, $m)) {
        if ($method === 'POST') {
            AuthMiddleware::requireLogin();
            RoleMiddleware::requireRole(['ADMIN_TEACHER']);
            $adminController->resetTeacherPassword((int)$m[1]);
        }
    }
    if ($method === 'GET' && $path === '/api/admin/groups') {
        AuthMiddleware::requireLogin();
        RoleMiddleware::requireRole(['ADMIN_TEACHER']);
        $adminController->listGroups();
    }
    if ($method === 'POST' && $path === '/api/admin/groups') {
        AuthMiddleware::requireLogin();
        RoleMiddleware::requireRole(['ADMIN_TEACHER']);
        $adminController->createGroup();
    }
    if (preg_match('#^/api/admin/groups/(\d+)$#', $path, $m)) {
        if ($method === 'PATCH') {
            AuthMiddleware::requireLogin();
            RoleMiddleware::requireRole(['ADMIN_TEACHER']);
            $adminController->patchGroup((int)$m[1]);
        }
    }
    if ($method === 'GET' && isset($_GET['teacher_id']) && $path === '/api/admin/access') {
        AuthMiddleware::requireLogin();
        RoleMiddleware::requireRole(['ADMIN_TEACHER']);
        $adminController->getAccess((int)$_GET['teacher_id']);
    }
    if ($method === 'POST' && $path === '/api/admin/access/grant') {
        AuthMiddleware::requireLogin();
        RoleMiddleware::requireRole(['ADMIN_TEACHER']);
        $adminController->grantAccess();
    }
    if ($method === 'POST' && $path === '/api/admin/access/revoke') {
        AuthMiddleware::requireLogin();
        RoleMiddleware::requireRole(['ADMIN_TEACHER']);
        $adminController->revokeAccess();
    }
    if (preg_match('#^/api/admin/groups/(\d+)/students/add$#', $path, $m)) {
        if ($method === 'POST') {
            AuthMiddleware::requireLogin();
            RoleMiddleware::requireRole(['ADMIN_TEACHER']);
            $adminController->addStudentToGroup((int)$m[1]);
        }
    }
    if (preg_match('#^/api/admin/groups/(\d+)/students/remove$#', $path, $m)) {
        if ($method === 'POST') {
            AuthMiddleware::requireLogin();
            RoleMiddleware::requireRole(['ADMIN_TEACHER']);
            $adminController->removeStudentFromGroup((int)$m[1]);
        }
    }
    if ($method === 'GET' && $path === '/api/admin/students') {
        AuthMiddleware::requireLogin();
        RoleMiddleware::requireRole(['ADMIN_TEACHER']);
        $adminController->listStudents();
    }

    Response::error('NOT_FOUND', 'Endpointi ei leitud', 404);
} catch (Throwable $e) {
    Response::error('SERVER_ERROR', 'Midagi läks valesti', 500);
}
