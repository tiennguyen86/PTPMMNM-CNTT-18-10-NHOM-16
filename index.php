<?php
/**
 * Front controller: mọi trang đều đi qua index.php?page=...
 */
define('APP', true);
require __DIR__ . '/app/bootstrap.php';

$routes = [
    'login'            => 'login.php',
    'register'         => 'register.php',
    'logout'           => 'logout.php',
    'dashboard'        => 'dashboard.php',
    'projects'         => 'projects.php',
    'project'          => 'project.php',
    'project_form'     => 'project_form.php',
    'endpoint'         => 'endpoint.php',
    'endpoint_form'    => 'endpoint_form.php',
    'version'          => 'version.php',
    'version_form'     => 'version_form.php',
    'version_download' => 'version_download.php',
    'compare'          => 'compare.php',
    'validate'         => 'validate.php',
    'runs'             => 'runs.php',
    'run'              => 'run.php',
    'errors'           => 'errors.php',
    'rules'            => 'rules.php',
    'users'            => 'users.php',
    'profile'          => 'profile.php',
];

$page = $_GET['page'] ?? 'dashboard';
if (!is_string($page) || !isset($routes[$page])) {
    abort(404, 'Không tìm thấy trang yêu cầu.');
}

verify_csrf();
require __DIR__ . '/pages/' . $routes[$page];
