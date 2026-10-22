<?php
declare(strict_types=1);

/*
 * Server-side workspace prototype for the Youth Council portal.
 * Requires PHP 8.1+ with PDO_SQLite. Configure PORTAL_DB_PATH to a location
 * OUTSIDE the public web root before deployment.
 */
session_start();

const APP_NAME = 'Молодёжный совет';
const ROLES = ['Участник', 'Модератор', 'Администратор'];

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $path = getenv('PORTAL_DB_PATH');
    if (!$path) {
        // Development fallback only. For deployment, set PORTAL_DB_PATH outside document root.
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'portal.sqlite';
    }
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Не удалось создать каталог базы данных.');
    }
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec("CREATE TABLE IF NOT EXISTS departments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        description TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name TEXT NOT NULL,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('Участник','Модератор','Администратор')),
        department_id INTEGER NULL REFERENCES departments(id) ON DELETE SET NULL,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    // Safe migration for databases created by an earlier prototype version.
    $userColumns = $pdo->query('PRAGMA table_info(users)')->fetchAll();
    if (!in_array('department_id', array_column($userColumns, 'name'), true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN department_id INTEGER NULL REFERENCES departments(id) ON DELETE SET NULL');
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        event_date TEXT NOT NULL,
        location TEXT NOT NULL DEFAULT '',
        description TEXT NOT NULL DEFAULT '',
        responsible_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
        created_by INTEGER NOT NULL REFERENCES users(id),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        due_date TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'Новая' CHECK(status IN ('Новая','Принята','В работе','На согласовании','Завершена')),
        assigned_to INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        created_by INTEGER NOT NULL REFERENCES users(id),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        event_id INTEGER NULL REFERENCES events(id) ON DELETE SET NULL,
        title TEXT NOT NULL,
        body TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'На проверке' CHECK(status IN ('На проверке','Одобрен модератором','Отклонён модератором','Утверждён администратором')),
        moderator_comment TEXT NOT NULL DEFAULT '',
        points INTEGER NOT NULL DEFAULT 0,
        reviewed_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
        approved_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS point_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        report_id INTEGER NULL REFERENCES reports(id) ON DELETE SET NULL,
        points INTEGER NOT NULL,
        note TEXT NOT NULL DEFAULT '',
        created_by INTEGER NOT NULL REFERENCES users(id),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    return $pdo;
}
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $q = db()->prepare('SELECT id, full_name, username, role FROM users WHERE id = ? AND active = 1');
    $q->execute([$_SESSION['user_id']]);
    $user = $q->fetch();
    if (!$user) { session_destroy(); return null; }
    return $user;
}
function require_role(array $user, array $roles): void {
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Недостаточно прав для выполнения действия.');
    }
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Сессия формы истекла. Обновите страницу и попробуйте снова.');
    }
}
function redirect_self(string $message = ''): never {
    $url = strtok($_SERVER['REQUEST_URI'] ?? 'portal.php', '?');
    if ($message !== '') $url .= '?msg=' . rawurlencode($message);
    header('Location: ' . $url);
    exit;
}

$pdo = db();
$user = current_user();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'login') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $q = $pdo->prepare('SELECT id, password_hash, active FROM users WHERE username = ?');
        $q->execute([$username]);
        $record = $q->fetch();
        if ($record && (int)$record['active'] === 1 && password_verify($password, $record['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$record['id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            redirect_self();
        }
        $error = 'Неверный логин или пароль либо учётная запись отключена.';
    } elseif ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? 'portal.php', '?'));
        exit;
    } elseif (!$user) {
        $error = 'Сначала войдите в систему.';
    } elseif ($action === 'create_user') {
        require_role($user, ['Администратор']);
        $name = trim((string)($_POST['full_name'] ?? ''));
        $username = trim((string)($_POST['new_username'] ?? ''));
        $password = (string)($_POST['new_password'] ?? '');
        $role = (string)($_POST['role'] ?? 'Участник');
        $departmentId = (int)($_POST['department_id'] ?? 0);
        if ($role === 'Участник') $departmentId = 0;
        if ($departmentId > 0) {
            $departmentCheck = $pdo->prepare('SELECT id FROM departments WHERE id=?');
            $departmentCheck->execute([$departmentId]);
            if (!$departmentCheck->fetch()) $departmentId = 0;
        }
        if ($name === '' || !preg_match('/^[A-Za-zА-Яа-яЁё0-9._-]{3,60}$/u', $username) || strlen($password) < 12 || !in_array($role, ROLES, true)) {
            $error = 'Проверьте данные. Логин: 3–60 символов; пароль: минимум 12 символов.';
        } else {
            try {
                $q = $pdo->prepare('INSERT INTO users(full_name, username, password_hash, role, department_id) VALUES(?,?,?,?,?)');
                $q->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role, $departmentId ?: null]);
                redirect_self('Учётная запись создана.');
            } catch (PDOException $ex) {
                $error = 'Не удалось создать учётную запись. Возможно, такой логин уже существует.';
            }
        }
    } elseif ($action === 'create_department') {
        require_role($user, ['Администратор']);
        $name = trim((string)($_POST['department_name'] ?? ''));
        $description = trim((string)($_POST['department_description'] ?? ''));
        if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
            $error = 'Укажите название отдела (не более 100 символов).';
        } else {
            try {
                $q = $pdo->prepare('INSERT INTO departments(name,description) VALUES(?,?)');
                $q->execute([$name, $description]);
                redirect_self('Отдел создан.');
            } catch (PDOException $ex) {
                $error = 'Не удалось создать отдел. Возможно, такое название уже существует.';
            }
        }
    } elseif ($action === 'create_event') {
        require_role($user, ['Администратор']);
        $title = trim((string)($_POST['title'] ?? ''));
        $date = trim((string)($_POST['event_date'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $responsible = (int)($_POST['responsible_user_id'] ?? 0);
        if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $error = 'Укажите название и корректную дату мероприятия.';
        } else {
            $q = $pdo->prepare("INSERT INTO events(title,event_date,location,description,responsible_user_id,created_by) VALUES(?,?,?,?,?,?)");
            $q->execute([$title, $date, $location, $description, $responsible ?: null, $user['id']]);
            if ($responsible > 0) {
                $task = $pdo->prepare("INSERT INTO tasks(title,description,due_date,assigned_to,created_by) VALUES(?,?,?,?,?)");
                $task->execute(['Организация: ' . $title, $description, $date, $responsible, $user['id']]);
            }
            redirect_self('Мероприятие добавлено.');
        }
    } elseif ($action === 'create_task') {
        require_role($user, ['Администратор']);
        $title = trim((string)($_POST['task_title'] ?? ''));
        $description = trim((string)($_POST['task_description'] ?? ''));
        $due = trim((string)($_POST['due_date'] ?? ''));
        $assigned = (int)($_POST['assigned_to'] ?? 0);
        $check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND active = 1 AND role = 'Модератор'");
        $check->execute([$assigned]);
        if ($title === '' || !$check->fetch()) {
            $error = 'Укажите задачу и действующего модератора.';
        } else {
            $q = $pdo->prepare('INSERT INTO tasks(title,description,due_date,assigned_to,created_by) VALUES(?,?,?,?,?)');
            $q->execute([$title, $description, $due, $assigned, $user['id']]);
            redirect_self('Задача назначена.');
        }
    } elseif ($action === 'task_status') {
        require_role($user, ['Модератор', 'Администратор']);
        $taskId = (int)($_POST['task_id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        $allowed = ['Новая','Принята','В работе','На согласовании','Завершена'];
        if (in_array($status, $allowed, true)) {
            if ($user['role'] === 'Администратор') {
                $q = $pdo->prepare('UPDATE tasks SET status = ? WHERE id = ?');
                $q->execute([$status, $taskId]);
            } else {
                $q = $pdo->prepare('UPDATE tasks SET status = ? WHERE id = ? AND assigned_to = ?');
                $q->execute([$status, $taskId, $user['id']]);
            }
        }
        redirect_self('Статус задачи обновлён.');
    } elseif ($action === 'submit_report') {
        require_role($user, ['Участник']);
        $title = trim((string)($_POST['report_title'] ?? ''));
        $body = trim((string)($_POST['report_body'] ?? ''));
        $eventId = (int)($_POST['event_id'] ?? 0);
        if ($title === '' || $body === '') {
            $error = 'Заполните название и описание отчёта.';
        } else {
            $q = $pdo->prepare('INSERT INTO reports(user_id,event_id,title,body) VALUES(?,?,?,?)');
            $q->execute([$user['id'], $eventId ?: null, $title, $body]);
            redirect_self('Отчёт отправлен на проверку.');
        }
    } elseif ($action === 'review_report') {
        require_role($user, ['Модератор']);
        $reportId = (int)($_POST['report_id'] ?? 0);
        $decision = (string)($_POST['decision'] ?? '');
        $comment = trim((string)($_POST['comment'] ?? ''));
        if ($decision === 'approve') {
            $q = $pdo->prepare("UPDATE reports SET status='Одобрен модератором', moderator_comment=?, reviewed_by=? WHERE id=? AND status='На проверке'");
            $q->execute([$comment, $user['id'], $reportId]);
        } elseif ($decision === 'reject' && $comment !== '') {
            $q = $pdo->prepare("UPDATE reports SET status='Отклонён модератором', moderator_comment=?, reviewed_by=? WHERE id=? AND status='На проверке'");
            $q->execute([$comment, $user['id'], $reportId]);
        }
        redirect_self('Решение по отчёту сохранено.');
    } elseif ($action === 'approve_report') {
        require_role($user, ['Администратор']);
        $reportId = (int)($_POST['report_id'] ?? 0);
        $points = filter_var($_POST['points'] ?? null, FILTER_VALIDATE_INT);
        $note = trim((string)($_POST['point_note'] ?? ''));
        if ($points === false || $points < 0 || $points > 100000) {
            $error = 'Укажите корректное количество баллов.';
        } else {
            $pdo->beginTransaction();
            $q = $pdo->prepare("SELECT id,user_id FROM reports WHERE id=? AND status='Одобрен модератором'");
            $q->execute([$reportId]);
            $report = $q->fetch();
            if (!$report) {
                $pdo->rollBack();
                $error = 'Этот отчёт ещё не одобрен модератором или уже обработан.';
            } else {
                $q = $pdo->prepare("UPDATE reports SET status='Утверждён администратором',points=?,approved_by=? WHERE id=?");
                $q->execute([$points, $user['id'], $reportId]);
                $q = $pdo->prepare('INSERT INTO point_log(user_id,report_id,points,note,created_by) VALUES(?,?,?,?,?)');
                $q->execute([$report['user_id'], $reportId, $points, $note, $user['id']]);
                $pdo->commit();
                redirect_self('Отчёт утверждён, баллы начислены.');
            }
        }
    }
    $user = current_user();
}

$notice = (string)($_GET['msg'] ?? '');
$events = $pdo->query('SELECT e.*, u.full_name AS responsible_name FROM events e LEFT JOIN users u ON u.id=e.responsible_user_id ORDER BY e.event_date ASC LIMIT 50')->fetchAll();
$moderators = $pdo->query("SELECT id,full_name FROM users WHERE active=1 AND role='Модератор' ORDER BY full_name")->fetchAll();
$allUsers = $pdo->query('SELECT u.id,u.full_name,u.username,u.role,u.active,u.created_at,d.name AS department_name FROM users u LEFT JOIN departments d ON d.id=u.department_id ORDER BY u.created_at DESC')->fetchAll();
$departments = $pdo->query("SELECT d.id,d.name,d.description,COUNT(u.id) AS staff_count FROM departments d LEFT JOIN users u ON u.department_id=d.id AND u.active=1 AND u.role IN ('Модератор','Администратор') GROUP BY d.id ORDER BY d.name")->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="referrer" content="same-origin">
<title><?= e(APP_NAME) ?> — рабочий кабинет</title>
<style>
:root{--green:#216744;--green-dark:#19462f;--bg:#f4f7f4;--ink:#203328;--muted:#68796e;--line:#e1e9e2;--white:#fff;--blue:#345e8a;--shadow:0 10px 32px rgba(24,54,36,.06}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;color:var(--ink)}button,input,textarea,select{font:inherit}button,.button{cursor:pointer}.layout{min-height:100vh;display:grid;grid-template-columns:245px 1fr}.sidebar{background:#173f2e;color:#eaf3ec;padding:24px 16px;display:flex;flex-direction:column;gap:22px}.brand{display:flex;gap:11px;align-items:center;padding:0 8px}.brand-mark{width:42px;height:42px;display:grid;place-items:center;border-radius:13px;background:#2d6b4a;font-size:21px}.brand strong{display:block;font-size:14px}.brand small{display:block;color:#b8d1c0;margin-top:4px}.nav{display:grid;gap:6px}.nav a{color:#d7e6dc;text-decoration:none;padding:11px 12px;border-radius:10px;font-size:14px}.nav a:hover,.nav a.active{background:#2a5c43;color:#fff}.side-note{margin-top:auto;border-top:1px solid #365c47;padding:16px 8px;color:#b8d1c0;font-size:12px;line-height:1.5}.main{min-width:0;padding:26px clamp(16px,3vw,38px) 42px}.topbar{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px}.eyebrow{font-size:11px;letter-spacing:.1em;color:var(--muted);font-weight:700;text-transform:uppercase}.topbar h1{margin:5px 0 0;font-size:clamp(24px,3vw,32px);letter-spacing:-.03em}.identity{display:flex;align-items:center;gap:12px}.identity small{display:block;color:var(--muted);margin-top:4px}.avatar{width:42px;height:42px;border-radius:50%;display:grid;place-items:center;background:#dcebe0;color:var(--green-dark);font-weight:700}.btn{border:0;border-radius:10px;padding:10px 14px;background:var(--green);color:white;font-weight:600}.btn.secondary{background:#e7efe8;color:var(--green-dark)}.btn.danger{background:#a73b36}.cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:20px 0}.stat,.panel,.event,.task,.report,.empty{background:var(--white);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow)}.stat{padding:17px}.stat-label{font-size:12px;color:var(--muted)}.stat-value{font-size:27px;font-weight:750;margin:9px 0 4px;letter-spacing:-.03em}.stat-note{font-size:12px;color:var(--muted)}.grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(280px,.75fr);gap:16px}.panel{padding:20px;margin-bottom:16px;min-width:0}.panel-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:15px}.panel h2{font-size:17px;margin:0}.panel p.sub{font-size:13px;color:var(--muted);margin:6px 0 0}.list{display:grid;gap:10px}.event,.task,.report{padding:14px;box-shadow:none}.item-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.item-title{font-weight:700;font-size:14px}.meta{color:var(--muted);font-size:12px;margin-top:5px;line-height:1.5}.badge{display:inline-flex;align-items:center;border-radius:100px;padding:5px 8px;font-size:11px;font-weight:700;background:#edf2ed;color:#42634c;white-space:nowrap}.badge.blue{background:#e8eff8;color:#355e8c}.badge.amber{background:#fbf0d9;color:#8a631b}.badge.red{background:#fae9e7;color:#a33f39}.badge.gray{background:#eef0f2;color:#59636d}.section-title{margin:24px 0 12px;font-size:18px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.field{display:grid;gap:6px}.field.full{grid-column:1/-1}.field label{font-size:12px;font-weight:700;color:#53665a}.field input,.field select,.field textarea{width:100%;border:1px solid #d8e2da;border-radius:10px;background:#fff;padding:11px 12px;color:var(--ink);min-width:0}.field textarea{min-height:100px;resize:vertical}.inline-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:12px}.inline-form input,.inline-form select,.inline-form textarea{padding:9px;border:1px solid #d8e2da;border-radius:9px;background:#fff;min-width:0}.inline-form input[type=number]{width:95px}.kanban{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.kanban-col{background:#eaf0ec;border-radius:13px;padding:10px;min-width:0}.kanban-col h3{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#557061;margin:4px 4px 10px}.kanban-col .task{margin-bottom:9px;padding:12px}.kanban-col .task:last-child{margin-bottom:0}.empty{padding:22px;text-align:center;color:var(--muted);box-shadow:none;font-size:13px}.notice{padding:12px 14px;border-radius:10px;background:#eaf3ec;color:#315b40;font-size:13px;line-height:1.5;margin-bottom:16px}.error{padding:12px 14px;background:#fae9e7;color:#8d302c;border-radius:10px;margin-bottom:14px}.success{padding:12px 14px;background:#eaf3ec;color:#315b40;border-radius:10px;margin-bottom:14px}.login-wrap{min-height:100vh;display:grid;place-items:center;padding:20px;background:radial-gradient(ellipse at top left,#e0efe3,transparent 45%),var(--bg)}.login{width:min(100%,430px);background:#fff;border:1px solid var(--line);border-radius:22px;padding:30px;box-shadow:0 18px 55px rgba(24,54,36,.1)}.login h1{margin:18px 0 6px;font-size:26px}.login p{color:var(--muted);font-size:14px;line-height:1.5}.login .field{margin-top:14px}.login .btn{width:100%;margin-top:18px;padding:12px}.fineprint{font-size:11px!important;margin-top:18px}.role-chip{font-size:11px;color:#dcebe0;background:#2a5c43;padding:5px 8px;border-radius:99px}
@media(max-width:1050px){.cards{grid-template-columns:repeat(2,minmax(0,1fr))}.grid{grid-template-columns:1fr}.layout{grid-template-columns:210px 1fr}}
@media(max-width:700px){.layout{display:block}.sidebar{padding:14px;gap:12px}.brand{padding:0 4px}.nav{display:flex;overflow-x:auto;padding-bottom:4px}.nav a{white-space:nowrap}.side-note{display:none}.main{padding:18px 14px 30px}.topbar{align-items:flex-start}.identity .user-meta{display:none}.cards{gap:9px}.stat{padding:13px}.stat-value{font-size:23px}.panel{padding:15px}.kanban{grid-template-columns:1fr}.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.topbar h1{font-size:24px}}
</style>
</head>
<body>
<?php if (!$user): ?>
<div class="login-wrap"><form class="login" method="post" autocomplete="on">
  <div class="brand" style="padding:0"><div class="brand-mark">М</div><div><strong style="color:#19462f">Молодёжный совет</strong><small style="color:#68796e">Рабочий портал</small></div></div>
  <h1>Вход в кабинет</h1><p>Войдите под своей учётной записью. Доступные разделы зависят от вашей роли.</p>
  <?php if ($error): ?><div class="error"><?=e($error)?></div><?php endif; ?>
  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="login">
  <div class="field"><label for="username">Логин</label><input id="username" name="username" required autocomplete="username"></div>
  <div class="field"><label for="password">Пароль</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
  <button class="btn" type="submit">Войти</button>
  <p class="fineprint">Учётные записи создаются администратором. Пароли хранятся в виде криптографических хешей, а не открытым текстом.</p>
</form></div>
<?php else:
$role = $user['role'];
$pointsQ = $pdo->prepare('SELECT COALESCE(SUM(points),0) FROM point_log WHERE user_id=?');
$pointsQ->execute([$user['id']]); $myPoints = (int)$pointsQ->fetchColumn();
$rank = (int)$pdo->query('SELECT COUNT(*)+1 FROM (SELECT user_id,SUM(points) AS total FROM point_log GROUP BY user_id HAVING total > ' . $myPoints . ')')->fetchColumn();
$myReportsQ = $pdo->prepare('SELECT * FROM reports WHERE user_id=? ORDER BY created_at DESC LIMIT 8'); $myReportsQ->execute([$user['id']]); $myReports=$myReportsQ->fetchAll();
if ($role === 'Модератор') {
  $reports = $pdo->query("SELECT r.*,u.full_name AS author,e.title AS event_title FROM reports r JOIN users u ON u.id=r.user_id LEFT JOIN events e ON e.id=r.event_id WHERE r.status='На проверке' ORDER BY r.created_at ASC LIMIT 30")->fetchAll();
  $tasksQ=$pdo->prepare('SELECT t.*,u.full_name AS creator FROM tasks t JOIN users u ON u.id=t.created_by WHERE t.assigned_to=? ORDER BY CASE t.status WHEN "Завершена" THEN 2 ELSE 0 END, t.due_date ASC LIMIT 40'); $tasksQ->execute([$user['id']]); $tasks=$tasksQ->fetchAll();
}
if ($role === 'Администратор') {
  $pending = $pdo->query("SELECT r.*,u.full_name AS author,e.title AS event_title FROM reports r JOIN users u ON u.id=r.user_id LEFT JOIN events e ON e.id=r.event_id WHERE r.status='Одобрен модератором' ORDER BY r.created_at ASC LIMIT 30")->fetchAll();
  $allReports = $pdo->query("SELECT r.*,u.full_name AS author FROM reports r JOIN users u ON u.id=r.user_id ORDER BY r.created_at DESC LIMIT 12")->fetchAll();
}
$myTasksQ=$pdo->prepare('SELECT COUNT(*) FROM tasks WHERE assigned_to=? AND status<>"Завершена"'); $myTasksQ->execute([$user['id']]); $myTasksCount=(int)$myTasksQ->fetchColumn();
$memberCount=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='Участник' AND active=1")->fetchColumn();
$taskCount=(int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE status<>'Завершена'")->fetchColumn();
$reportCount=(int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status IN ('На проверке','Одобрен модератором')")->fetchColumn();
?>
<div class="layout">
<aside class="sidebar">
  <div class="brand"><div class="brand-mark">М</div><div><strong>Молодёжный совет</strong><small>Рабочий портал</small></div></div>
  <nav class="nav">
    <a class="active" href="#overview">▦ &nbsp; Главная</a>
    <?php if ($role==='Участник'): ?><a href="#calendar">▦ &nbsp; Календарь событий</a><a href="#my-reports">▤ &nbsp; Мои отчёты</a><a href="#my-rating">♜ &nbsp; Мой рейтинг</a><?php endif; ?>
    <?php if ($role==='Модератор'): ?><a href="#tasks">☷ &nbsp; Мои задачи</a><a href="#review">✓ &nbsp; Проверка отчётов</a><?php endif; ?>
    <?php if ($role==='Администратор'): ?><a href="#events">▦ &nbsp; Мероприятия</a><a href="#tasks-admin">☷ &nbsp; Задачи</a><a href="#approve">✓ &nbsp; Утверждение отчётов</a><a href="#users">♙ &nbsp; Учётные записи</a><a href="rating.html">♜ &nbsp; Управление рейтингом</a><a href="#departments">▦ &nbsp; Отделы и права</a><?php endif; ?>
    <a href="molodezh_meropriyatiya.html">◷ &nbsp; Публичные мероприятия</a>
  </nav>
  <div class="side-note">Портал Молодёжного совета<br>Тульской области<br><br>Концепт рабочего кабинета</div>
</aside>
<main class="main" id="overview">
  <header class="topbar">
    <div><div class="eyebrow">Рабочее пространство / <?=e($role)?></div><h1><?= $role==='Участник'?'Мой кабинет':($role==='Модератор'?'Мои задачи и отчёты':'Управление советом') ?></h1></div>
    <div class="identity"><div class="user-meta"><strong><?=e($user['full_name'])?></strong><small><?=e($role)?></small></div><div class="avatar"><?=e(mb_substr($user['full_name'],0,1,'UTF-8'))?></div><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="logout"><button class="btn secondary" type="submit">Выйти</button></form></div>
  </header>
  <?php if ($error): ?><div class="error"><?=e($error)?></div><?php endif; ?>
  <?php if ($notice): ?><div class="success"><?=e($notice)?></div><?php endif; ?>
  <?php if ($role==='Участник'): ?>
    <div class="notice">Здесь собраны ближайшие события, ваши отчёты и результаты участия в совете.</div>
    <div class="cards">
      <div class="stat"><div class="stat-label">Мои баллы</div><div class="stat-value"><?=$myPoints?></div><div class="stat-note">Начислены администратором</div></div>
      <div class="stat"><div class="stat-label">Место в рейтинге</div><div class="stat-value"><?=$myPoints>0?'#'.$rank:'—'?></div><div class="stat-note">По сумме баллов</div></div>
      <div class="stat"><div class="stat-label">Мои отчёты</div><div class="stat-value"><?=count($myReports)?></div><div class="stat-note">Последние 8</div></div>
      <div class="stat"><div class="stat-label">Ближайшие события</div><div class="stat-value"><?=count(array_filter($events,fn($ev)=>$ev['event_date']>=date('Y-m-d'))) ?></div><div class="stat-note">В календаре портала</div></div>
    </div>
    <div class="grid"><section class="panel" id="calendar"><div class="panel-head"><div><h2>Ближайшие события</h2><p class="sub">Откройте событие, чтобы посмотреть подробности.</p></div></div><div class="list">
      <?php $futureEvents=array_values(array_filter($events,fn($ev)=>$ev['event_date']>=date('Y-m-d'))); ?>
      <?php if (!$futureEvents): ?><div class="empty">Пока нет запланированных мероприятий.</div><?php endif; ?>
      <?php foreach(array_slice($futureEvents,0,8) as $ev): ?><article class="event"><div class="item-head"><div><div class="item-title"><?=e($ev['title'])?></div><div class="meta"><?=e(date('d.m.Y',strtotime($ev['event_date'])))?> · <?=e($ev['location'] ?: 'Место уточняется')?></div></div><span class="badge"><?=e($ev['responsible_name'] ?: 'Совет')?></span></div><?php if($ev['description']): ?><div class="meta"><?=e($ev['description'])?></div><?php endif; ?></article><?php endforeach; ?>
    </div></section>
    <section class="panel" id="my-rating"><div class="panel-head"><div><h2>Мой рейтинг</h2><p class="sub">Баллы за утверждённые отчёты</p></div></div><div class="stat-value"><?=$myPoints?></div><div class="stat-note">Баллов всего</div><div class="notice" style="margin-top:14px">Баллы появляются после утверждения отчёта администратором.</div></section></div>
    <section class="panel" id="my-reports"><div class="panel-head"><div><h2>Мои отчёты</h2><p class="sub">Отправляйте отчёты о своей деятельности.</p></div></div>
      <form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="submit_report"><div class="field"><label>Название отчёта</label><input name="report_title" required maxlength="180"></div><div class="field"><label>Мероприятие</label><select name="event_id"><option value="0">Не привязывать</option><?php foreach($events as $ev): ?><option value="<?=$ev['id']?>"><?=e($ev['title'])?> — <?=e(date('d.m.Y',strtotime($ev['event_date'])))?></option><?php endforeach; ?></select></div><div class="field full"><label>Описание выполненной работы</label><textarea name="report_body" required></textarea></div><div class="field full"><button class="btn" type="submit">Отправить отчёт</button></div></form>
      <h3 class="section-title">История отчётов</h3><div class="list"><?php if(!$myReports): ?><div class="empty">Вы ещё не отправляли отчёты.</div><?php endif; ?><?php foreach($myReports as $r): ?><article class="report"><div class="item-head"><div><div class="item-title"><?=e($r['title'])?></div><div class="meta"><?=e(date('d.m.Y',strtotime($r['created_at'])))?> · <?=e($r['moderator_comment'])?></div></div><span class="badge <?=str_contains($r['status'],'Отклонён')?'red':(str_contains($r['status'],'Утверждён')?'':'amber')?>"><?=e($r['status'])?></span></div><?php if($r['points']>0): ?><div class="meta">Начислено баллов: <?=$r['points']?></div><?php endif; ?></article><?php endforeach; ?></div>
    </section>
  <?php elseif ($role==='Модератор'): ?>
    <div class="notice">Выполняйте поручения председателя и заместителя, обновляйте статусы задач и проверяйте отчёты участников.</div>
    <div class="cards"><div class="stat"><div class="stat-label">Мои незавершённые задачи</div><div class="stat-value"><?=$myTasksCount?></div><div class="stat-note">Назначены вам</div></div><div class="stat"><div class="stat-label">Отчёты на проверке</div><div class="stat-value"><?=count($reports)?></div><div class="stat-note">Очередь модерации</div></div><div class="stat"><div class="stat-label">Ближайшие события</div><div class="stat-value"><?=count(array_filter($events,fn($ev)=>$ev['event_date']>=date('Y-m-d'))) ?></div><div class="stat-note">В календаре</div></div><div class="stat"><div class="stat-label">Моя роль</div><div class="stat-value" style="font-size:20px">Модератор</div><div class="stat-note">Задачи и проверка</div></div></div>
    <section class="panel" id="tasks"><div class="panel-head"><div><h2>Мои задачи</h2><p class="sub">CRM-доска назначенных поручений.</p></div></div><div class="kanban">
    <?php foreach(['Новая','В работе','На согласовании'] as $col): ?><div class="kanban-col"><h3><?=$col?></h3><?php $colTasks=array_filter($tasks,fn($t)=>($col==='Новая'?in_array($t['status'],['Новая','Принята'],true):$t['status']===$col)); ?><?php if(!$colTasks): ?><div class="meta" style="padding:8px">Нет задач</div><?php endif; ?><?php foreach($colTasks as $t): ?><article class="task"><div class="item-title"><?=e($t['title'])?></div><div class="meta"><?=e($t['description'])?></div><div class="meta">Срок: <?=e($t['due_date']?date('d.m.Y',strtotime($t['due_date'])):'не задан')?></div><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="task_status"><input type="hidden" name="task_id" value="<?=$t['id']?>"><select name="status"><?php foreach(['Принята','В работе','На согласовании','Завершена'] as $st): ?><option <?= $t['status']===$st?'selected':'' ?>><?=e($st)?></option><?php endforeach; ?></select><button class="btn secondary" type="submit">Обновить</button></form></article><?php endforeach; ?></div><?php endforeach; ?></div></section>
    <section class="panel" id="review"><div class="panel-head"><div><h2>Отчёты на проверке</h2><p class="sub">Одобрение модератором не начисляет баллы автоматически.</p></div></div><div class="list"><?php if(!$reports): ?><div class="empty">Новых отчётов пока нет.</div><?php endif; ?><?php foreach($reports as $r): ?><article class="report"><div class="item-title"><?=e($r['title'])?></div><div class="meta">Автор: <?=e($r['author'])?> · <?=e($r['event_title'] ?: 'Без привязки к мероприятию')?></div><p><?=nl2br(e($r['body']))?></p><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="review_report"><input type="hidden" name="report_id" value="<?=$r['id']?>"><div class="field full"><label>Комментарий (обязателен при отклонении)</label><input name="comment" placeholder="Комментарий участнику"></div><div class="field"><button class="btn" name="decision" value="approve">Одобрить</button></div><div class="field"><button class="btn danger" name="decision" value="reject">Отклонить</button></div></form></article><?php endforeach; ?></div></section>
  <?php else: ?>
    <div class="notice">Планируйте мероприятия, назначайте ответственных модераторов, контролируйте задачи и утверждайте отчёты перед начислением баллов.</div>
    <div class="cards"><div class="stat"><div class="stat-label">Участники</div><div class="stat-value"><?=$memberCount?></div><div class="stat-note">Активные учётные записи</div></div><div class="stat"><div class="stat-label">Открытые задачи</div><div class="stat-value"><?=$taskCount?></div><div class="stat-note">По всем модераторам</div></div><div class="stat"><div class="stat-label">Отчёты в процессе</div><div class="stat-value"><?=$reportCount?></div><div class="stat-note">Проверка или утверждение</div></div><div class="stat"><div class="stat-label">Мероприятия</div><div class="stat-value"><?=count($events)?></div><div class="stat-note">В календаре портала</div></div></div>
    <div class="grid"><section class="panel" id="events"><div class="panel-head"><div><h2>Планирование мероприятия</h2><p class="sub">При назначении модератора создаётся задача по организации.</p></div></div><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create_event"><div class="field full"><label>Название мероприятия</label><input name="title" required maxlength="180"></div><div class="field"><label>Дата</label><input type="date" name="event_date" required></div><div class="field"><label>Место</label><input name="location"></div><div class="field"><label>Ответственный модератор</label><select name="responsible_user_id"><option value="0">Не назначен</option><?php foreach($moderators as $m): ?><option value="<?=$m['id']?>"><?=e($m['full_name'])?></option><?php endforeach; ?></select></div><div class="field full"><label>Описание и поручение</label><textarea name="description"></textarea></div><div class="field full"><button class="btn" type="submit">Добавить мероприятие</button></div></form></section>
    <section class="panel" id="tasks-admin"><div class="panel-head"><div><h2>Назначить задачу</h2><p class="sub">Поручение конкретному модератору.</p></div></div><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create_task"><div class="field full"><label>Задача</label><input name="task_title" required></div><div class="field"><label>Срок</label><input type="date" name="due_date"></div><div class="field"><label>Модератор</label><select name="assigned_to" required><?php foreach($moderators as $m): ?><option value="<?=$m['id']?>"><?=e($m['full_name'])?></option><?php endforeach; ?></select></div><div class="field full"><label>Описание</label><textarea name="task_description"></textarea></div><div class="field full"><button class="btn" type="submit">Назначить задачу</button></div></form></section></div>
    <section class="panel" id="approve"><div class="panel-head"><div><h2>Отчёты на окончательное утверждение</h2><p class="sub">Баллы начисляются только после решения администратора.</p></div></div><div class="list"><?php if(!$pending): ?><div class="empty">Нет отчётов, ожидающих утверждения.</div><?php endif; ?><?php foreach($pending as $r): ?><article class="report"><div class="item-head"><div><div class="item-title"><?=e($r['title'])?></div><div class="meta">Автор: <?=e($r['author'])?> · Одобрено модератором</div></div><span class="badge amber">Нужно утвердить</span></div><p><?=nl2br(e($r['body']))?></p><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="approve_report"><input type="hidden" name="report_id" value="<?=$r['id']?>"><label class="meta">Баллы</label><input type="number" name="points" min="0" max="100000" value="0" required><input name="point_note" placeholder="Основание начисления" style="flex:1" required><button class="btn" type="submit">Утвердить и начислить</button></form></article><?php endforeach; ?></div></section>
    <section class="panel" id="users"><div class="panel-head"><div><h2>Учётные записи</h2><p class="sub">Создание аккаунтов и назначение одной из трёх ролей.</p></div></div><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create_user"><div class="field"><label>ФИО</label><input name="full_name" required></div><div class="field"><label>Логин</label><input name="new_username" required minlength="3" maxlength="60" autocomplete="off"></div><div class="field"><label>Временный пароль (не менее 12 символов)</label><input type="password" name="new_password" required minlength="12" autocomplete="new-password"></div><div class="field"><label>Роль</label><select name="role" id="newUserRole"><option>Участник</option><option>Модератор</option><option>Администратор</option></select></div><div class="field"><label>Отдел (для модератора/администратора)</label><select name="department_id" id="newUserDepartment"><option value="0">Без отдела</option><?php foreach($departments as $d): ?><option value="<?=$d['id']?>"><?=e($d['name'])?></option><?php endforeach; ?></select></div><div class="field full"><button class="btn" type="submit">Создать учётную запись</button></div></form>
      <h3 class="section-title">Пользователи системы</h3><div class="list"><?php foreach($allUsers as $u): ?><article class="event"><div class="item-head"><div><div class="item-title"><?=e($u['full_name'])?></div><div class="meta"><?=e($u['username'])?><?php if($u['department_name']): ?> · Отдел: <?=e($u['department_name'])?><?php endif; ?></div></div><span class="badge <?= $u['role']==='Администратор'?'blue':'' ?>"><?=e($u['role'])?></span></div></article><?php endforeach; ?></div>
    </section>
    <section class="panel" id="departments"><div class="panel-head"><div><h2>Отделы и распределение доступа</h2><p class="sub">Создавайте отделы и прикрепляйте к ним модераторов и администраторов при создании учётной записи.</p></div></div><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create_department"><div class="field"><label>Название отдела</label><input name="department_name" required maxlength="100" placeholder="Например, Мероприятия"></div><div class="field"><label>Описание / зона ответственности</label><input name="department_description" maxlength="500" placeholder="За что отвечает отдел"></div><div class="field full"><button class="btn" type="submit">Создать отдел</button></div></form><h3 class="section-title">Созданные отделы</h3><div class="list"><?php if(!$departments): ?><div class="empty">Отделов пока нет.</div><?php endif; ?><?php foreach($departments as $d): ?><article class="event"><div class="item-head"><div><div class="item-title"><?=e($d['name'])?></div><div class="meta"><?=e($d['description'] ?: 'Описание не задано')?></div></div><span class="badge"><?=$d['staff_count']?> сотрудников</span></div></article><?php endforeach; ?></div><p class="meta">Сейчас отдел фиксирует принадлежность сотрудника. Ролевые права по-прежнему определяются основной ролью «Администратор» или «Модератор».</p></section>
    <section class="panel"><div class="panel-head"><div><h2>Последние отчёты</h2><p class="sub">Контроль движения отчётов по этапам.</p></div></div><div class="list"><?php if(!$allReports): ?><div class="empty">Отчётов пока нет.</div><?php endif; ?><?php foreach($allReports as $r): ?><article class="report"><div class="item-head"><div><div class="item-title"><?=e($r['title'])?></div><div class="meta"><?=e($r['author'])?></div></div><span class="badge"><?=e($r['status'])?></span></div><?php if($r['points']>0): ?><div class="meta">Баллы: <?=$r['points']?></div><?php endif; ?></article><?php endforeach; ?></div></section>
  <?php endif; ?>
  <p class="meta">Концепт портала · Серверная проверка доступа включена в обработчики действий. Для публикации настройте HTTPS и расположение базы вне публичного каталога.</p>
</main></div>
<?php endif; ?>
<script>
const roleSelect=document.getElementById('newUserRole');
const departmentSelect=document.getElementById('newUserDepartment');
if(roleSelect && departmentSelect){const syncDepartment=()=>{const allowed=roleSelect.value==='Модератор'||roleSelect.value==='Администратор';departmentSelect.disabled=!allowed;if(!allowed)departmentSelect.value='0';};roleSelect.addEventListener('change',syncDepartment);syncDepartment();}
</script>
</body></html>
