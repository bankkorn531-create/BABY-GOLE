<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/connect.php';

function input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = [];
    return array_merge($_POST, $data);
}
function respond($arr) { echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
function fail($msg) { respond(['success' => false, 'message' => $msg]); }

$in = input();
$action = $in['action'] ?? ($_GET['action'] ?? '');

function userOut($u) {
    return [
        'id' => (int)$u['id'],
        'name' => $u['name'],
        'user' => $u['username'],
        'pass' => $u['password'],
        'phone' => $u['phone'],
        'email' => $u['email'],
        'altEmails' => $u['alt_emails'] ? json_decode($u['alt_emails'], true) : [],
        'isAdmin' => (bool)$u['is_admin'],
        'profilePic' => $u['profile_pic'] ?? ''
    ];
}

switch ($action) {

    // ---------- AUTH ----------
    case 'list_users': {
        $rows = $pdo->query('SELECT * FROM users ORDER BY id ASC')->fetchAll();
        respond(['success' => true, 'users' => array_map('userOut', $rows)]);
    }

    case 'login': {
        $identity = trim($in['identity'] ?? '');
        $password = $in['password'] ?? '';
        $stmt = $pdo->query('SELECT * FROM users');
        $found = null;
        foreach ($stmt->fetchAll() as $u) {
            $alt = $u['alt_emails'] ? json_decode($u['alt_emails'], true) : [];
            $candidates = array_merge([$u['username'], $u['email'], $u['phone']], $alt ?: []);
            foreach ($candidates as $c) {
                if ($c !== null && strtolower((string)$c) === strtolower($identity) && $u['password'] === $password) {
                    $found = $u; break 2;
                }
            }
        }
        if (!$found) fail('Username/Email หรือ Password ไม่ถูกต้อง');
        respond(['success' => true, 'user' => userOut($found)]);
    }

    case 'register': {
        $name = trim($in['name'] ?? '');
        $phone = trim($in['phone'] ?? '');
        $email = trim($in['email'] ?? '');
        $altEmail = trim($in['altEmail'] ?? '');
        $username = trim($in['username'] ?? '');
        $password = $in['password'] ?? '';

        if (!$name || !$phone || !$email || !$username || !$password) fail('กรุณากรอกข้อมูลให้ครบทุกช่อง โดยเฉพาะ Email และ Username');

        $chk = $pdo->prepare('SELECT id FROM users WHERE LOWER(username)=LOWER(?)');
        $chk->execute([$username]);
        if ($chk->fetch()) fail('ชื่อผู้ใช้งานนี้ถูกใช้ไปแล้ว');

        $chk = $pdo->prepare('SELECT id, alt_emails FROM users WHERE email=?');
        $chk->execute([$email]);
        if ($chk->fetch()) fail('Email นี้ถูกใช้ไปแล้ว กรุณาใช้อีเมลอื่น');

        if ($altEmail) {
            $rows = $pdo->query('SELECT email, alt_emails FROM users')->fetchAll();
            foreach ($rows as $r) {
                $alt = $r['alt_emails'] ? json_decode($r['alt_emails'], true) : [];
                if ($r['email'] === $altEmail || in_array($altEmail, $alt ?: [])) fail('อีเมลสำรองนี้ถูกใช้ไปแล้ว');
            }
        }

        $altEmails = $altEmail ? json_encode([$altEmail], JSON_UNESCAPED_UNICODE) : null;
        $stmt = $pdo->prepare('INSERT INTO users (name, username, password, phone, email, alt_emails, is_admin, profile_pic) VALUES (?,?,?,?,?,?,0,"")');
        $stmt->execute([$name, $username, $password, $phone, $email, $altEmails]);
        respond(['success' => true]);
    }

    case 'forgot': {
        $identity = trim($in['identity'] ?? '');
        $rows = $pdo->query('SELECT * FROM users')->fetchAll();
        $found = null;
        foreach ($rows as $u) {
            $alt = $u['alt_emails'] ? json_decode($u['alt_emails'], true) : [];
            $candidates = array_merge([$u['username'], $u['email'], $u['phone']], $alt ?: []);
            foreach ($candidates as $c) {
                if ($c !== null && strtolower((string)$c) === strtolower($identity)) { $found = $u; break 2; }
            }
        }
        if (!$found) respond(['success' => true, 'found' => false]);
        if ($found['is_admin']) respond(['success' => true, 'found' => false]);
        if (!$found['email']) respond(['success' => true, 'found' => false, 'noEmail' => true]);
        respond(['success' => true, 'found' => true, 'name' => $found['name'], 'email' => $found['email'], 'pass' => $found['password']]);
    }

    // ---------- ADMIN: USERS ----------
    case 'add_user': {
        $name = trim($in['name'] ?? '');
        $username = trim($in['user'] ?? '');
        $password = $in['pass'] ?? '';
        $phone = trim($in['phone'] ?? '');
        $email = trim($in['email'] ?? '');
        $role = $in['role'] ?? 'user';
        if (!$name || !$username || !$password || !$phone || !$email) fail('กรุณากรอกข้อมูลให้ครบทุกช่อง');

        $chk = $pdo->prepare('SELECT id FROM users WHERE LOWER(username)=LOWER(?)');
        $chk->execute([$username]);
        if ($chk->fetch()) fail('ชื่อผู้ใช้งานนี้ถูกใช้ไปแล้ว');
        $chk = $pdo->prepare('SELECT id FROM users WHERE email=?');
        $chk->execute([$email]);
        if ($chk->fetch()) fail('Email นี้ถูกใช้แล้ว');

        $stmt = $pdo->prepare('INSERT INTO users (name, username, password, phone, email, is_admin, profile_pic) VALUES (?,?,?,?,?,?,"")');
        $stmt->execute([$name, $username, $password, $phone, $email, $role === 'admin' ? 1 : 0]);
        respond(['success' => true]);
    }

    case 'toggle_admin': {
        $id = (int)($in['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE users SET is_admin = 1 - is_admin WHERE id = ?');
        $stmt->execute([$id]);
        respond(['success' => true]);
    }

    case 'delete_user': {
        $id = (int)($in['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        respond(['success' => true]);
    }

    case 'add_alt_email': {
        $username = trim($in['username'] ?? '');
        $newEmail = trim($in['email'] ?? '');
        if (!$newEmail) fail('กรุณากรอกอีเมลสำรอง');

        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $u = $stmt->fetch();
        if (!$u) fail('ไม่พบผู้ใช้');
        if ($newEmail === $u['email']) fail('อีเมลนี้เป็นอีเมลหลักอยู่แล้ว');
        $alt = $u['alt_emails'] ? json_decode($u['alt_emails'], true) : [];
        if (in_array($newEmail, $alt)) fail('อีเมลนี้ถูกเพิ่มไว้แล้ว');

        $rows = $pdo->query('SELECT email, alt_emails FROM users')->fetchAll();
        foreach ($rows as $r) {
            $a = $r['alt_emails'] ? json_decode($r['alt_emails'], true) : [];
            if ($r['email'] === $newEmail || in_array($newEmail, $a ?: [])) fail('อีเมลนี้ถูกใช้กับบัญชีอื่นแล้ว');
        }

        $alt[] = $newEmail;
        $stmt = $pdo->prepare('UPDATE users SET alt_emails = ? WHERE username = ?');
        $stmt->execute([json_encode($alt, JSON_UNESCAPED_UNICODE), $username]);
        respond(['success' => true, 'altEmails' => $alt]);
    }

    case 'update_profile_pic': {
        $username = trim($in['username'] ?? '');
        $image = $in['image'] ?? '';
        $stmt = $pdo->prepare('UPDATE users SET profile_pic = ? WHERE username = ?');
        $stmt->execute([$image, $username]);
        respond(['success' => true]);
    }

    // ---------- FORUM ----------
    case 'list_forum': {
        $posts = $pdo->query('SELECT * FROM forum_posts ORDER BY id DESC')->fetchAll();
        $replyStmt = $pdo->prepare('SELECT author, text FROM forum_replies WHERE post_id = ? ORDER BY id ASC');
        $out = [];
        foreach ($posts as $p) {
            $replyStmt->execute([$p['id']]);
            $out[] = [
                'id' => (int)$p['id'],
                'author' => $p['author'],
                'title' => $p['title'],
                'detail' => $p['detail'],
                'replies' => $replyStmt->fetchAll()
            ];
        }
        respond(['success' => true, 'posts' => $out]);
    }

    case 'create_post': {
        $author = trim($in['author'] ?? 'ผู้เยี่ยมชม');
        $title = trim($in['title'] ?? '');
        $detail = trim($in['detail'] ?? '');
        if (!$title) fail('กรุณาใส่หัวข้อกระทู้');
        $stmt = $pdo->prepare('INSERT INTO forum_posts (author, title, detail) VALUES (?,?,?)');
        $stmt->execute([$author, $title, $detail]);
        respond(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    }

    case 'add_reply': {
        $postId = (int)($in['postId'] ?? 0);
        $author = trim($in['author'] ?? 'ผู้เยี่ยมชม');
        $text = trim($in['text'] ?? '');
        if (!$text) fail('กรุณากรอกข้อความตอบกลับ');
        $stmt = $pdo->prepare('INSERT INTO forum_replies (post_id, author, text) VALUES (?,?,?)');
        $stmt->execute([$postId, $author, $text]);
        respond(['success' => true]);
    }

    case 'delete_post': {
        $postId = (int)($in['postId'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM forum_posts WHERE id = ?');
        $stmt->execute([$postId]);
        respond(['success' => true]);
    }

    // ---------- TRACKER ----------
    case 'list_tracker': {
        $rows = $pdo->query('SELECT record_date AS d, age_text AS a, weight AS w, height AS h FROM tracker_records ORDER BY id ASC')->fetchAll();
        respond(['success' => true, 'records' => $rows]);
    }

    case 'save_tracker': {
        $d = trim($in['d'] ?? ''); $a = trim($in['a'] ?? ''); $w = trim($in['w'] ?? ''); $h = trim($in['h'] ?? '');
        $username = trim($in['username'] ?? '');
        if (!$d || !$a || !$w || !$h) fail('กรุณากรอกข้อมูลให้ครบถ้วน');
        $stmt = $pdo->prepare('INSERT INTO tracker_records (username, record_date, age_text, weight, height) VALUES (?,?,?,?,?)');
        $stmt->execute([$username, $d, $a, $w, $h]);
        respond(['success' => true]);
    }

    // ---------- NOTIFICATIONS ----------
    case 'list_notifications': {
        $rows = $pdo->query('SELECT id, text, created_at AS createdAt FROM notifications ORDER BY id DESC')->fetchAll();
        respond(['success' => true, 'notifications' => $rows]);
    }

    case 'push_notification': {
        $text = trim($in['text'] ?? '');
        if (!$text) fail('empty');
        $stmt = $pdo->prepare('INSERT INTO notifications (text) VALUES (?)');
        $stmt->execute([$text]);
        respond(['success' => true]);
    }

    case 'clear_notifications': {
        $pdo->exec('TRUNCATE TABLE notifications');
        respond(['success' => true]);
    }

    default:
        fail('ไม่รู้จักคำสั่ง action: ' . $action);
}
