<?php
require_login();

$user_id = $_SESSION['user_id'];
$error   = '';
$success = '';

// Cek request aktif yang belum selesai
$stmt = $conn->prepare("
    SELECT * FROM password_requests
    WHERE user_id = ? AND status = 'pending'
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$user_id]);
$pending = $stmt->fetch();

// Cek hasil review terakhir (approved/rejected)
$stmt = $conn->prepare("
    SELECT pr.*, u.full_name as reviewer_name
    FROM password_requests pr
    LEFT JOIN users u ON pr.reviewed_by = u.user_id
    WHERE pr.user_id = ? AND pr.status != 'pending'
    ORDER BY pr.reviewed_at DESC LIMIT 1
");
$stmt->execute([$user_id]);
$last_result = $stmt->fetch();

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi    = $_POST['konfirmasi']    ?? '';

    // Ambil password sekarang dari DB
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();

    if (!password_verify($password_lama, $user_data['password'])) {
        $error = 'Password lama tidak sesuai!';
    } elseif (strlen($password_baru) < 6) {
        $error = 'Password baru minimal 6 karakter!';
    } elseif ($password_baru !== $konfirmasi) {
        $error = 'Konfirmasi password tidak cocok!';
    } elseif ($pending) {
        $error = 'Kamu masih punya permintaan yang sedang menunggu persetujuan superadmin!';
    } else {
        // Simpan request dengan password sudah di-hash
        $hash = password_hash($password_baru, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO password_requests (user_id, new_password, status)
            VALUES (?, ?, 'pending')
        ");
        $stmt->execute([$user_id, $hash]);
        $success = 'Permintaan ganti password berhasil dikirim! Menunggu persetujuan Super Admin.';
        // Refresh pending status
        $pending = $conn->query("SELECT * FROM password_requests WHERE user_id = $user_id AND status = 'pending' LIMIT 1")->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - PLN</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">

            <h1 class="page-title">🔑 Ganti Password</h1>

            <!-- Status Permintaan Aktif -->
            <?php if ($pending): ?>
            <div style="background:rgba(255,193,7,0.12);border:1px solid rgba(255,193,7,0.4);border-radius:12px;padding:18px 22px;margin-bottom:24px;display:flex;align-items:center;gap:14px">
                <span style="font-size:28px">⏳</span>
                <div>
                    <div style="font-weight:700;color:#ffc107;margin-bottom:4px">Permintaan Sedang Menunggu Persetujuan</div>
                    <div style="font-size:13px;color:var(--text-secondary)">
                        Dikirim: <?= date('d M Y, H:i', strtotime($pending['created_at'])) ?> WIB.
                        Superadmin akan segera meninjau permintaanmu.
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Hasil Review Terakhir -->
            <?php if ($last_result && !$pending): ?>
            <?php $is_approved = $last_result['status'] === 'approved'; ?>
            <div style="background:<?= $is_approved ? 'rgba(93,216,126,0.12)' : 'rgba(255,107,122,0.12)' ?>;
                        border:1px solid <?= $is_approved ? 'rgba(93,216,126,0.4)' : 'rgba(255,107,122,0.4)' ?>;
                        border-radius:12px;padding:18px 22px;margin-bottom:24px;display:flex;align-items:flex-start;gap:14px">
                <span style="font-size:28px"><?= $is_approved ? '✅' : '❌' ?></span>
                <div>
                    <div style="font-weight:700;color:<?= $is_approved ? '#5dd87e' : '#ff6b7a' ?>;margin-bottom:4px">
                        <?= $is_approved ? 'Password Berhasil Diubah!' : 'Permintaan Ditolak' ?>
                    </div>
                    <div style="font-size:13px;color:var(--text-secondary)">
                        Diproses oleh <strong><?= htmlspecialchars($last_result['reviewer_name'] ?? 'Admin') ?></strong>
                        pada <?= date('d M Y, H:i', strtotime($last_result['reviewed_at'])) ?> WIB
                    </div>
                    <?php if ($last_result['alasan']): ?>
                    <div style="margin-top:8px;padding:10px 14px;background:var(--bg-secondary);border-radius:8px;font-size:13px">
                        <strong>Alasan:</strong> <?= htmlspecialchars($last_result['alasan']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Error / Success -->
            <?php if ($error): ?>
            <div style="background:rgba(255,107,122,0.12);border:1px solid rgba(255,107,122,0.4);color:#ff6b7a;border-radius:10px;padding:14px 18px;margin-bottom:20px">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            <?php if ($success): ?>
            <div style="background:rgba(93,216,126,0.12);border:1px solid rgba(93,216,126,0.4);color:#5dd87e;border-radius:10px;padding:14px 18px;margin-bottom:20px">
                ✅ <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <!-- Form Ganti Password -->
            <?php if (!$pending): ?>
            <div class="card" style="max-width:480px;padding:32px">
                <h3 style="margin-bottom:20px;font-size:16px">Ajukan Permintaan Ganti Password</h3>

                <div style="background:rgba(0,217,255,0.07);border:1px solid rgba(0,217,255,0.2);border-radius:10px;padding:12px 16px;margin-bottom:24px;font-size:13px;color:var(--text-secondary)">
                    💡 Permintaanmu akan dikirim ke <strong>Super Admin</strong> untuk disetujui.
                    Password hanya akan berubah setelah disetujui.
                </div>

                <form method="POST" action="?page=ganti-password">

                    <div class="form-group">
                        <label class="form-label">Password Lama</label>
                        <input type="password" name="password_lama" class="form-control"
                               placeholder="Masukkan password sekarang" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password Baru</label>
                        <input type="password" name="password_baru" class="form-control"
                               placeholder="Minimal 6 karakter" required minlength="6"
                               id="pwd_baru">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" name="konfirmasi" class="form-control"
                               placeholder="Ulangi password baru" required
                               id="pwd_konfirm">
                        <small id="match_msg" style="font-size:12px;margin-top:4px;display:block"></small>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;padding:12px">
                        Kirim Permintaan Ganti Password
                    </button>

                </form>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<script>
// Cek kesesuaian password secara real-time
document.getElementById('pwd_konfirm')?.addEventListener('input', function() {
    const baru = document.getElementById('pwd_baru').value;
    const msg  = document.getElementById('match_msg');
    if (this.value === '') { msg.textContent = ''; return; }
    if (this.value === baru) {
        msg.textContent = '✅ Password cocok';
        msg.style.color = '#5dd87e';
    } else {
        msg.textContent = '❌ Password tidak cocok';
        msg.style.color = '#ff6b7a';
    }
});
</script>
</body>
</html>
