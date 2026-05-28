<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$configFile = $basePath . '/config/config.php';

if (!is_file($configFile)) {
    http_response_code(500);
    exit('Config fehlt. Bitte config/config.example.php nach config/config.php kopieren.');
}

$config = require $configFile;

$earlyLogPath = (string)($config['app']['log_path'] ?? ($basePath . '/logs/useradmin.log'));

if (!is_dir(dirname($earlyLogPath))) {
    @mkdir(dirname($earlyLogPath), 0750, true);
}


set_error_handler(static function ($severity, $message, $file, $line) use ($earlyLogPath): bool {
    @file_put_contents(
        $earlyLogPath,
        json_encode([
            'time' => date('c'),
            'level' => 'PHP_ERROR',
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'severity' => $severity,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );

    return false;
});

set_exception_handler(static function (Throwable $e) use ($earlyLogPath): void {
    @file_put_contents(
        $earlyLogPath,
        json_encode([
            'time' => date('c'),
            'level' => 'FATAL',
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => '[hidden]',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );

    http_response_code(500);
    echo 'Interner Fehler. Details wurden in die Logdatei geschrieben.';
    exit;
});

register_shutdown_function(static function () use ($earlyLogPath): void {
    $error = error_get_last();

    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        @file_put_contents(
            $earlyLogPath,
            json_encode([
                'time' => date('c'),
                'level' => 'SHUTDOWN_FATAL',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
});


header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:; base-uri 'self'; form-action 'self'; frame-ancestors 'none';");

require $basePath . '/src/helpers.php';
require $basePath . '/src/Logger.php';
require $basePath . '/src/Database.php';
require $basePath . '/src/Csrf.php';
require $basePath . '/src/Auth.php';
require $basePath . '/src/View.php';
require $basePath . '/src/UserRepository.php';
require $basePath . '/src/GroupRepository.php';
require $basePath . '/src/KasApiClient.php';
require $basePath . '/src/UserVisibleException.php';
require $basePath . '/src/BruteForce.php';
require $basePath . '/src/PasswordResetRequestRepository.php';
require $basePath . '/src/Mailer.php';
require $basePath . '/src/PasswordCrypto.php';

session_name((string)($config['security']['session_name'] ?? 'KERWE_USERADMIN'));

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);

session_start();

$route = (string)($_GET['r'] ?? 'users');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

Logger::info($config, 'Request started', [
    'route' => $route,
    'method' => $method,
]);


function deriveUserPostFields(array $post, array $config): array
{
    $uid = strtolower(trim((string)($post['uid'] ?? '')));
    $mailSuffix = (string)($config['app']['mail_domain_suffix'] ?? '@die-kerwe.de');

    if ($uid === '') {
        return $post;
    }

    $parts = array_values(array_filter(explode('.', $uid), static fn($part) => $part !== ''));

    $firstName = '';
    $lastName = '';

    if (isset($parts[0])) {
        $firstName = ucfirst(strtolower($parts[0]));
    }

    if (count($parts) > 1) {
        $lastName = implode(' ', array_map(
            static fn($part) => ucfirst(strtolower($part)),
            array_slice($parts, 1)
        ));
    }

    if (trim((string)($post['given_name'] ?? '')) === '') {
        $post['given_name'] = $firstName;
    }

    if (trim((string)($post['family_name'] ?? '')) === '') {
        $post['family_name'] = $lastName;
    }

    if (trim((string)($post['display_name'] ?? '')) === '') {
        $post['display_name'] = trim($post['given_name'] . ' ' . $post['family_name']);
    }

    if (trim((string)($post['mail'] ?? '')) === '') {
        $post['mail'] = $uid . $mailSuffix;
    }

    if (trim((string)($post['imap_user'] ?? '')) === '') {
        $post['imap_user'] = $uid . $mailSuffix;
    }

    return $post;
}

/**
 * Prüft Passwort und Wiederholung. Wenn $required = true ist, müssen beide Felder
 * gesetzt sein. Wenn $required = false ist, wird nur geprüft, sobald eines der
 * beiden Felder befüllt wurde.
 */
function validate_posted_password(bool $required): ?string
{
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if (!$required && $password === '' && $passwordConfirm === '') {
        return null;
    }

    if ($password === '' || $passwordConfirm === '') {
        throw new UserVisibleException('Bitte gib das Passwort in beiden Passwortfeldern ein.');
    }

    if ($password !== $passwordConfirm) {
        throw new UserVisibleException('Die beiden eingegebenen Passwörter stimmen nicht überein.');
    }

    if (strlen($password) < 10) {
        throw new UserVisibleException('Das Passwort muss mindestens 10 Zeichen lang sein.');
    }

    return $password;
}

/**
 * Bereitet gepostete Benutzerdaten für erneutes Rendern des Formulars auf,
 * ohne sensible Eingaben wieder auszugeben.
 */
function posted_user_for_form(?int $id = null): array
{
    $postedUser = $_POST;

    unset($postedUser['password'], $postedUser['password_confirm'], $postedUser['kas_2fa'], $postedUser['_csrf']);

    if ($id !== null) {
        $postedUser['id'] = $id;
    }

    $postedUser['enabled'] = isset($_POST['enabled']) ? 1 : 0;
    $postedUser['group_ids'] = array_map('intval', $_POST['group_ids'] ?? []);

    return $postedUser;
}

try {
    if ($route === 'login') {
        if ($method === 'POST') {
            Csrf::verify($config);

            $username = (string)($_POST['username'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $ipAddress = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');

            $db = Database::connect($config);
            $bruteForce = new BruteForce($db, $config);

            if ($bruteForce->isLocked($username, $ipAddress)) {
                $remainingSeconds = $bruteForce->remainingLockSeconds($username, $ipAddress);
                $remainingMinutes = max(1, (int)ceil($remainingSeconds / 60));

                Logger::warning($config, 'Admin login blocked by brute-force protection', [
                    'username' => $username,
                    'ip_address' => $ipAddress,
                    'remaining_minutes' => $remainingMinutes,
                ]);

                View::render($config, 'login', [
                    'error' => 'Zu viele fehlgeschlagene Anmeldeversuche. Bitte warte ca. ' . $remainingMinutes . ' Minuten und versuche es erneut.',
                ]);
                exit;
            }

            $ok = Auth::login($config, $username, $password);
            $bruteForce->record($username, $ipAddress, $ok);

            if ($ok) {
                Logger::info($config, 'Admin login successful', [
                    'username' => $username,
                    'ip_address' => $ipAddress,
                ]);

                $returnTo = (string)($_POST['return'] ?? $_GET['return'] ?? $_SESSION['after_login_redirect'] ?? '');
                unset($_SESSION['after_login_redirect']);

                if ($returnTo !== '' && strpos($returnTo, '/') === 0 && strpos($returnTo, '//') !== 0) {
                    header('Location: ' . $returnTo);
                    exit;
                }

                redirect_to($config, '/?r=users');
            }

            Logger::warning($config, 'Admin login failed', [
                'username' => $username,
                'ip_address' => $ipAddress,
            ]);

            View::render($config, 'login', [
                'error' => 'Benutzername oder Passwort ist falsch.',
            ]);
            exit;
        }

        View::render($config, 'login');
        exit;
    }

    if ($route === 'logout') {
        Logger::info($config, 'Admin logout', [
            'username' => Auth::user(),
        ]);

        Auth::logout();
        redirect_to($config, '/?r=login');
    }


if ($route === 'password-forgot') {
    $db = Database::connect($config);
    $users = new UserRepository($db, $config);
    $passwordResetRequests = new PasswordResetRequestRepository($db);

    $genericMessage = 'Falls ein passendes Konto existiert, wurde eine E-Mail mit einem Reset-Link versendet.';

    if ($method === 'POST') {
        Csrf::verify($config);

        $ipAddress = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $identifier = (string)($_POST['identifier'] ?? '');

        $passwordResetRequests->cleanupExpired();

        $maxRequests = (int)($config['password_reset']['max_requests_per_ip_per_hour'] ?? 5);
        $recentRequests = $passwordResetRequests->countRecentRequestsByIp($ipAddress, 60);

        if ($recentRequests >= $maxRequests) {
            Logger::warning($config, 'Password reset rate limit reached', [
                'ip_address' => $ipAddress,
            ]);

            View::render($config, 'password_forgot', [
                'message' => $genericMessage,
            ]);
            exit;
        }

        $user = $users->findByUidOrMail($identifier);

        if ($user) {
            try {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);

                $lifetimeMinutes = (int)($config['password_reset']['request_token_lifetime_minutes'] ?? 30);

                $passwordResetRequests->createRequest(
                    (int)$user['id'],
                    $tokenHash,
                    $ipAddress,
                    $lifetimeMinutes
                );

                $resetUrl = app_url($config, '/?r=password-reset&token=' . urlencode($token));

                Mailer::sendPasswordResetMail($config, $user, $resetUrl);

                Logger::info($config, 'Password reset mail sent', [
                    'user_id' => (int)$user['id'],
                    'uid' => (string)$user['uid'],
                    'mail' => (string)$user['mail'],
                ]);
            } catch (Throwable $e) {
                Logger::exception($config, $e, [
                    'route' => $route,
                    'context' => 'password reset request',
                ]);

                // Keine technische Fehlermeldung an den User.
                // Sonst könnten Rückschlüsse auf Existenz oder Mailzustellung entstehen.
            }
        } else {
            Logger::info($config, 'Password reset requested for unknown identifier', [
                'ip_address' => $ipAddress,
            ]);
        }

        View::render($config, 'password_forgot', [
            'message' => $genericMessage,
        ]);
        exit;
    }

    View::render($config, 'password_forgot');
    exit;
}

if ($route === 'password-reset') {
    $db = Database::connect($config);
    $passwordResetRequests = new PasswordResetRequestRepository($db);

    $token = (string)($_GET['token'] ?? $_POST['token'] ?? '');

    if ($token === '') {
        View::render($config, 'password_reset', [
            'error' => 'Der Reset-Link ist ungültig oder abgelaufen.',
            'done' => true,
        ]);
        exit;
    }

    /*
     * Diese Methode ergänzen wir im nächsten Schritt:
     * Sie lädt einen gültigen Reset-Request anhand des request_token_hash.
     */
    $resetRequest = $passwordResetRequests->findValidRequestByToken($token);

    if (!$resetRequest) {
        View::render($config, 'password_reset', [
            'error' => 'Der Reset-Link ist ungültig oder abgelaufen.',
            'done' => true,
        ]);
        exit;
    }

    if ($method === 'POST') {
        Csrf::verify($config);

        try {
            $password = (string)($_POST['password'] ?? '');
            $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

            if ($password === '' || $passwordConfirm === '') {
                throw new UserVisibleException('Bitte gib das neue Passwort in beiden Feldern ein.');
            }

            if ($password !== $passwordConfirm) {
                throw new UserVisibleException('Die beiden Passwörter stimmen nicht überein.');
            }

            if (mb_strlen($password) < 10) {
                throw new UserVisibleException('Das Passwort muss mindestens 10 Zeichen lang sein.');
            }

            $encrypted = PasswordCrypto::encrypt($config, $password);

            $approveToken = bin2hex(random_bytes(32));
            $approveTokenHash = hash('sha256', $approveToken);

            $approvalLifetimeHours = (int)($config['password_reset']['approval_lifetime_hours'] ?? 24);

            $passwordResetRequests->markPendingAdmin(
                (int)$resetRequest['id'],
                $approveTokenHash,
                $encrypted['encrypted_password'],
                $encrypted['encryption_nonce'],
                $approvalLifetimeHours
            );

            $approvalUrl = app_url($config, '/?r=password-reset-approve&token=' . urlencode($approveToken));

            Mailer::sendPasswordResetApprovalMail($config, $resetRequest, $approvalUrl);

            Logger::info($config, 'Password reset pending admin approval', [
                'reset_request_id' => (int)$resetRequest['id'],
                'user_id' => (int)$resetRequest['user_id'],
                'uid' => (string)$resetRequest['uid'],
            ]);

            View::render($config, 'password_reset', [
                'message' => 'Deine Passwortänderung wurde vorgemerkt und wartet auf Admin-Freigabe.',
                'done' => true,
            ]);
            exit;
        } catch (UserVisibleException $e) {
            View::render($config, 'password_reset', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
            exit;
        }
    }

    View::render($config, 'password_reset', [
        'token' => $token,
    ]);
    exit;
}


if (!Auth::check() && $route === 'password-reset-approve') {
    $returnTo = (string)($_SERVER['REQUEST_URI'] ?? '/?r=users');

    if ($returnTo === '' || strpos($returnTo, '/') !== 0) {
        $returnTo = '/?r=users';
    }

    redirect_to($config, '/?r=login&return=' . urlencode($returnTo));
}


    Auth::requireLogin($config);

    $db = Database::connect($config);
    $users = new UserRepository($db, $config);
    $groups = new GroupRepository($db);
    $kas = new KasApiClient($config);

    switch ($route) {
        case 'users':
            View::render($config, 'users_index', [
                'users' => $users->all(),
            ]);
            break;

        case 'user-new':
            if ($method === 'POST') {
                Csrf::verify($config);
                $_POST = deriveUserPostFields($_POST, $config);

                try {
                    $password = validate_posted_password(true);

                    $uid = (string)($_POST['uid'] ?? '');
                    $mail = (string)($_POST['mail'] ?? '');
                    $imapUser = (string)($_POST['imap_user'] ?? '');

                    if ($users->existsByUidMailOrImapUser($uid, $mail, $imapUser)) {
                        throw new UserVisibleException(
                            'Ein Benutzer mit dieser UID, E-Mail-Adresse oder diesem IMAP-User existiert bereits.'
                        );
                    }

                    $kas->createMailAccount(
                        $mail,
                        (string)$password,
                        (string)($_POST['kas_2fa'] ?? '')
                    );

                    $users->create($_POST);

                    Logger::info($config, 'User created', [
                        'uid' => (string)($_POST['uid'] ?? ''),
                        'mail' => (string)($_POST['mail'] ?? ''),
                    ]);

                    redirect_to($config, '/?r=users');
                } catch (UserVisibleException $e) {
                    Logger::warning($config, 'User-visible user-new error', [
                        'route' => $route,
                        'message' => $e->getMessage(),
                    ]);

                    View::render($config, 'users_form', [
                        'user' => posted_user_for_form(),
                        'groups' => $groups->all(),
                        'defaultQuota' => $config['app']['default_quota'] ?? '512 MB',
                        'mailSuffix' => $config['app']['mail_domain_suffix'] ?? '@die-kerwe.de',
                        'error' => $e->getMessage(),
                    ]);
                    exit;
                } catch (PDOException $e) {
                    Logger::warning($config, 'User-create database error', [
                        'route' => $route,
                        'message' => $e->getMessage(),
                    ]);

                    $message = 'Der Benutzer konnte nicht angelegt werden. Bitte prüfe, ob UID, E-Mail-Adresse oder IMAP-User bereits existieren.';

                    View::render($config, 'users_form', [
                        'user' => posted_user_for_form(),
                        'groups' => $groups->all(),
                        'defaultQuota' => $config['app']['default_quota'] ?? '512 MB',
                        'mailSuffix' => $config['app']['mail_domain_suffix'] ?? '@die-kerwe.de',
                        'error' => $message,
                    ]);
                    exit;
                }
                
            }

            View::render($config, 'users_form', [
                'user' => null,
                'groups' => $groups->all(),
                'defaultQuota' => $config['app']['default_quota'] ?? '512 MB',
                'mailSuffix' => $config['app']['mail_domain_suffix'] ?? '@die-kerwe.de',
            ]);
            break;

        case 'user-edit':
            $id = (int)($_GET['id'] ?? 0);
            $user = $users->find($id);

            if (!$user) {
                http_response_code(404);
                exit('Benutzer nicht gefunden.');
            }

            if ($method === 'POST') {
                Csrf::verify($config);

                try {
                    $password = validate_posted_password(false);

                    if ($password !== null) {
                        $kas->updateMailPassword(
                            (string)$_POST['imap_user'],
                            $password,
                            (string)($_POST['kas_2fa'] ?? '')
                        );
                    }

                    $users->update($id, $_POST);

                    Logger::info($config, 'User updated', [
                        'id' => $id,
                        'uid' => (string)($_POST['uid'] ?? ''),
                    ]);

                    redirect_to($config, '/?r=users');
                } catch (UserVisibleException $e) {
                    Logger::warning($config, 'User-visible user-edit error', [
                        'route' => $route,
                        'id' => $id,
                        'message' => $e->getMessage(),
                    ]);

                    View::render($config, 'users_form', [
                        'user' => posted_user_for_form($id),
                        'groups' => $groups->all(),
                        'defaultQuota' => $config['app']['default_quota'] ?? '512 MB',
                        'mailSuffix' => $config['app']['mail_domain_suffix'] ?? '@die-kerwe.de',
                        'error' => $e->getMessage(),
                    ]);
                    exit;
                }
            }

            View::render($config, 'users_form', [
                'user' => $user,
                'groups' => $groups->all(),
                'defaultQuota' => $config['app']['default_quota'] ?? '512 MB',
                'mailSuffix' => $config['app']['mail_domain_suffix'] ?? '@die-kerwe.de',
            ]);
            break;

        case 'user-delete':
            if ($method !== 'POST') {
                http_response_code(405);
                exit('Method not allowed');
            }

            Csrf::verify($config);

            try {
                $deleteId = (int)($_POST['id'] ?? 0);
                $kas2fa = (string)($_POST['kas_2fa'] ?? '');

                $user = $users->findIncludingDeleted($deleteId);

                if (!$user) {
                    throw new UserVisibleException('Der Benutzer wurde nicht gefunden.');
                }

                if ($kas2fa === '') {
                    throw new UserVisibleException('Bitte gib den KAS-2FA-Code ein, um Benutzer und Postfach endgültig zu löschen.');
                }

                $kas->deleteMailAccountByAddress(
                    (string)$user['imap_user'],
                    $kas2fa
                );

                $users->hardDelete($deleteId);

                Logger::info($config, 'User and mail account deleted', [
                    'id' => $deleteId,
                    'uid' => (string)$user['uid'],
                    'mail' => (string)$user['mail'],
                ]);

                redirect_to($config, '/?r=users');
            } catch (UserVisibleException $e) {
                Logger::warning($config, 'User-visible delete error', [
                    'route' => $route,
                    'message' => $e->getMessage(),
                ]);

                View::render($config, 'users_index', [
                    'users' => $users->all(),
                    'error' => $e->getMessage(),
                ]);
                exit;
            }
        
        case 'groups':
            View::render($config, 'groups_index', [
                'groups' => $groups->all(),
            ]);
            break;

        case 'group-new':
            if ($method === 'POST') {
                Csrf::verify($config);

                $groups->create($_POST);

                Logger::info($config, 'Group created', [
                    'name' => (string)($_POST['name'] ?? ''),
                ]);

                redirect_to($config, '/?r=groups');
            }

            View::render($config, 'groups_form', [
                'group' => null,
            ]);
            break;

        case 'group-edit':
            $id = (int)($_GET['id'] ?? 0);
            $group = $groups->find($id);

            if (!$group) {
                http_response_code(404);
                exit('Gruppe nicht gefunden.');
            }

            if ($method === 'POST') {
                Csrf::verify($config);

                $groups->update($id, $_POST);

                Logger::info($config, 'Group updated', [
                    'id' => $id,
                    'name' => (string)($_POST['name'] ?? ''),
                ]);

                redirect_to($config, '/?r=groups');
            }

            View::render($config, 'groups_form', [
                'group' => $group,
            ]);
            break;

        case 'group-delete':
            if ($method !== 'POST') {
                http_response_code(405);
                exit('Method not allowed');
            }

            Csrf::verify($config);

            $deleteId = (int)($_POST['id'] ?? 0);
            $groups->delete($deleteId);

            Logger::info($config, 'Group deleted', [
                'id' => $deleteId,
            ]);

            redirect_to($config, '/?r=groups');

        case 'password-reset-approve':
            $passwordResetRequests = new PasswordResetRequestRepository($db);

            $token = (string)($_GET['token'] ?? $_POST['token'] ?? '');

            if ($token === '') {
                View::render($config, 'password_reset_approve', [
                    'error' => 'Der Freigabe-Link ist ungültig oder abgelaufen.',
                    'done' => true,
                ]);
                break;
            }

            $resetRequest = $passwordResetRequests->findPendingApprovalByToken($token);

            if (!$resetRequest) {
                View::render($config, 'password_reset_approve', [
                    'error' => 'Der Freigabe-Link ist ungültig, abgelaufen oder wurde bereits verwendet.',
                    'done' => true,
                ]);
                break;
            }

            if ($method === 'POST') {
                Csrf::verify($config);

                try {
                    $kas2fa = (string)($_POST['kas_2fa'] ?? '');

                    if ($kas2fa === '') {
                        throw new UserVisibleException('Bitte gib den KAS-2FA-Code ein.');
                    }

                    $plainPassword = PasswordCrypto::decrypt(
                        $config,
                        (string)$resetRequest['encrypted_password'],
                        (string)$resetRequest['encryption_nonce']
                    );

                    $kas->updateMailPassword(
                        (string)$resetRequest['imap_user'],
                        $plainPassword,
                        $kas2fa
                    );

                    // Passwort möglichst schnell aus dem RAM entfernen.
                    if (function_exists('sodium_memzero')) {
                        sodium_memzero($plainPassword);
                    } else {
                        $plainPassword = '';
                    }

                    $passwordResetRequests->markCompleted(
                        (int)$resetRequest['id'],
                        (string)Auth::user()
                    );

                    Logger::info($config, 'Password reset approved and completed', [
                        'reset_request_id' => (int)$resetRequest['id'],
                        'user_id' => (int)$resetRequest['user_id'],
                        'uid' => (string)$resetRequest['uid'],
                        'approved_by' => (string)Auth::user(),
                    ]);

                    View::render($config, 'password_reset_approve', [
                        'message' => 'Die Passwortänderung wurde freigegeben und erfolgreich umgesetzt.',
                        'done' => true,
                    ]);
                    break;
                } catch (UserVisibleException $e) {
                    Logger::warning($config, 'User-visible password reset approval error', [
                        'reset_request_id' => (int)$resetRequest['id'],
                        'message' => $e->getMessage(),
                    ]);

                    View::render($config, 'password_reset_approve', [
                        'token' => $token,
                        'resetRequest' => $resetRequest,
                        'error' => $e->getMessage(),
                    ]);
                    break;
                } catch (Throwable $e) {
                    Logger::exception($config, $e, [
                        'route' => $route,
                        'reset_request_id' => (int)$resetRequest['id'],
                    ]);

                    View::render($config, 'password_reset_approve', [
                        'token' => $token,
                        'resetRequest' => $resetRequest,
                        'error' => 'Die Passwortänderung konnte nicht umgesetzt werden.',
                    ]);
                    break;
                } finally {
                    if (isset($plainPassword) && is_string($plainPassword) && $plainPassword !== '') {
                        if (function_exists('sodium_memzero')) {
                            sodium_memzero($plainPassword);
                        } else {
                            $plainPassword = '';
                        }
                    }
                }
            }

            View::render($config, 'password_reset_approve', [
                'token' => $token,
                'resetRequest' => $resetRequest,
            ]);
            break;

        default:
            http_response_code(404);
            echo 'Seite nicht gefunden.';
    }
} catch (Throwable $e) {
    Logger::exception($config, $e, [
        'route' => $route,
        'method' => $method,
    ]);

    http_response_code(500);

    if (ini_get('display_errors')) {
        echo '<pre>' . h((string)$e) . '</pre>';
    } else {
        echo 'Interner Fehler. Details wurden in die Logdatei geschrieben.';
    }
}