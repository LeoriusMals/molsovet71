<?php
declare(strict_types=1);
/*
 * Run only from CLI: php setup_admin.php
 * Creates the first administrator account interactively.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "PHP extension pdo_sqlite is required.\n");
    exit(1);
}
$path = getenv('PORTAL_DB_PATH');
if (!$path) {
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'portal.sqlite';
}
$dir = dirname($path);
if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
    fwrite(STDERR, "Cannot create database directory.\n");
    exit(1);
}
$pdo = new PDO('sqlite:' . $path);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
 id INTEGER PRIMARY KEY AUTOINCREMENT, full_name TEXT NOT NULL, username TEXT NOT NULL UNIQUE,
 password_hash TEXT NOT NULL, role TEXT NOT NULL CHECK(role IN ('Участник','Модератор','Администратор')),
 active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
fwrite(STDOUT, "Создание первого администратора\nФИО: ");
$name = trim((string)fgets(STDIN));
fwrite(STDOUT, "Логин (3–60 символов): ");
$username = trim((string)fgets(STDIN));
fwrite(STDOUT, "Пароль (не менее 12 символов): ");
if (PHP_OS_FAMILY === 'Windows') {
    $password = trim((string)fgets(STDIN));
} else {
    system('stty -echo');
    $password = (string)fgets(STDIN);
    system('stty echo');
    fwrite(STDOUT, "\n");
    $password = rtrim($password, "\r\n");
}
if ($name === '' || !preg_match('/^[A-Za-zА-Яа-яЁё0-9._-]{3,60}$/u', $username) || strlen($password) < 12) {
    fwrite(STDERR, "Неверные данные. Логин 3–60 символов, пароль минимум 12 символов.\n");
    exit(1);
}
try {
    $stmt = $pdo->prepare("INSERT INTO users(full_name,username,password_hash,role) VALUES(?,?,?,'Администратор')");
    $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT)]);
    fwrite(STDOUT, "Администратор создан. Не публикуйте этот скрипт и удалите его с сервера после настройки.\n");
} catch (PDOException $e) {
    fwrite(STDERR, "Не удалось создать администратора: логин может быть занят.\n");
    exit(1);
}
