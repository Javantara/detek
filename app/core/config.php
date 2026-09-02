<?php
// ============================================================
// CORE CONFIG - Helper Functions & Session
// TIDAK bisa diakses langsung via browser (ada di /app/)
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// ─── Konstanta ───────────────────────────────────────────────
define('BASE_URL', '/pln_web/public/');
define('APP',      __DIR__ . '/../');       // path ke folder app/
define('VIEWS',    APP . 'views/');
define('SITE_NAME', 'PLN Dashboard');

date_default_timezone_set('Asia/Jakarta');

// ─── Auth Helpers ────────────────────────────────────────────

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect ke halaman lewat index.php.
 * URL: BASE_URL + ?page=xxx&key=val
 */
function redirect(string $page, array $params = []): void {
    $url = BASE_URL . '?page=' . urlencode($page);
    foreach ($params as $k => $v) {
        $url .= '&' . urlencode($k) . '=' . urlencode($v);
    }
    header('Location: ' . $url);
    exit;
}

function require_login(): void {
    if (!is_logged_in()) {
        redirect('login');
    }
}

function require_role(string|array $allowed): void {
    require_login();
    $roles = (array) $allowed;
    if (!in_array($_SESSION['role'], $roles)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px"><h2>403 – Akses Ditolak</h2><p>Anda tidak memiliki izin untuk halaman ini.</p><a href="' . BASE_URL . '">← Kembali</a></div>');
    }
}

// ─── Input & Output ──────────────────────────────────────────

function clean_input(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)));
}

function show_alert(string $type, string $message): string {
    return "<div class='alert alert-{$type}'>" . htmlspecialchars($message) . "</div>";
}

// Baca & hapus flash message dari session
function flash(): string {
    if (!empty($_SESSION['msg'])) {
        $html = show_alert($_SESSION['msg_type'] ?? 'info', $_SESSION['msg']);
        unset($_SESSION['msg'], $_SESSION['msg_type']);
        return $html;
    }
    return '';
}

function set_flash(string $message, string $type = 'success'): void {
    $_SESSION['msg']      = $message;
    $_SESSION['msg_type'] = $type;
}

// ─── Logging ─────────────────────────────────────────────────

function get_user_ip(): string {
    if (!empty($_SERVER['HTTP_CLIENT_IP']))        return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))  return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function log_activity(int $user_id, string $action, ?int $plant_id = null, ?int $unit_id = null): void {
    global $conn;
    try {
        // Ambil data user dari DB
        $stmt = $conn->prepare("SELECT nip, full_name, email FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        // Kalau user tidak ada di DB (misal sudah dihapus), pakai data dari session sebagai fallback
        if (!$user) {
            $user = [
                'nip'       => $_SESSION['nip']       ?? '-',
                'full_name' => $_SESSION['full_name'] ?? 'Unknown',
                'email'     => $_SESSION['email']     ?? '-',
            ];
            // Jika user_id tidak ada di DB, jangan insert (FK constraint akan gagal)
            // Cukup skip logging
            return;
        }

        $ip   = get_user_ip();
        $stmt = $conn->prepare("INSERT INTO user_activity (user_id, nip, full_name, email, action, plant_id, unit_id, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $user['nip'], $user['full_name'], $user['email'], $action, $plant_id, $unit_id, $ip]);
    } catch (PDOException $e) {
        // Gagal log activity - tidak perlu crash, cukup skip
    }
}
