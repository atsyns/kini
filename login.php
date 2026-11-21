<?php
declare(strict_types=1);

session_start();

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// ตรวจสอบบัญชีจาก PDO หากมี config; รองรับ password_hash และรหัสที่เก็บแบบ plain text สำหรับการย้ายระบบ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $authenticatedUser = null;
        $pdo = null;
        $pdoConfig = __DIR__ . '/config/conn_pdo.php';

        if (is_file($pdoConfig)) {
            require_once $pdoConfig;
        }

        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT id, username, name, email, password
                     FROM users
                     WHERE username = :username OR email = :username
                     LIMIT 1'
                );
                $stmt->execute(['username' => $username]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    $storedPassword = (string) ($row['password'] ?? '');
                    $passwordMatches = password_verify($password, $storedPassword);
                    if (!$passwordMatches && hash_equals($storedPassword, $password)) {
                        $passwordMatches = true;
                    }
                    if ($passwordMatches) {
                        $authenticatedUser = [
                            'id' => (int) ($row['id'] ?? 0),
                            'name' => (string) ($row['name'] ?? $row['username'] ?? $username),
                            'username' => (string) ($row['username'] ?? $username),
                            'email' => (string) ($row['email'] ?? ''),
                        ];
                    }
                }
            } catch (Throwable $e) {
                $error = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้ในขณะนี้';
            }
        }

        // โหมดสำรองสำหรับทดลองระบบก่อนสร้างตาราง users
        if ($authenticatedUser === null && $error === '' && $username === 'admin' && $password === 'admin123') {
            $authenticatedUser = [
                'id' => 1,
                'name' => 'ผู้ดูแลระบบ',
                'username' => 'admin',
                'email' => 'admin@example.com',
            ];
        }

        if ($authenticatedUser !== null) {
            session_regenerate_id(true);
            $_SESSION['user'] = $authenticatedUser;
            header('Location: index.php');
            exit;
        }

        if ($error === '') {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}

$safeError = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบ | TTM Data Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">
    <main class="login-card" aria-labelledby="loginTitle">
        <div class="login-accent" aria-hidden="true"></div>
        <div class="login-content">
            <div class="login-brand">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v13a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 18.5v-13Z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                </span>
                <div>
                    <h1 id="loginTitle">เข้าสู่ระบบ TTM</h1>
                    <p>ระบบจัดการข้อมูล</p>
                </div>
            </div>

            <?php if ($error !== ''): ?>
                <div class="login-error" role="alert"><?= $safeError ?></div>
            <?php endif; ?>

            <form class="login-form" method="post" action="login.php" autocomplete="on">
                <div class="login-field">
                    <label for="username">ชื่อผู้ใช้หรืออีเมล</label>
                    <input id="username" name="username" type="text" autocomplete="username" required autofocus
                           value="<?= htmlspecialchars((string) ($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="login-field">
                    <label for="password">รหัสผ่าน</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                </div>
                <button class="login-submit" type="submit">เข้าสู่ระบบ</button>
            </form>
            <p class="login-hint">โหมดทดสอบ: admin / admin123</p>
        </div>
    </main>
</body>
</html>
