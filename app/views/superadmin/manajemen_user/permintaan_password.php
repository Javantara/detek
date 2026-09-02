<?php
require_role('superadmin');

// Handle approve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $req_id  = intval($_POST['request_id']);
    $action  = $_POST['action'];
    $alasan  = trim($_POST['alasan'] ?? '');

    // Ambil data request
    $stmt = $conn->prepare("SELECT * FROM password_requests WHERE request_id = ? AND status = 'pending'");
    $stmt->execute([$req_id]);
    $req = $stmt->fetch();

    if ($req) {
        if ($action === 'approve') {
            // Ganti password user
            $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?")
                 ->execute([$req['new_password'], $req['user_id']]);
            // Update status request
            $conn->prepare("UPDATE password_requests SET status='approved', alasan=?, reviewed_by=?, reviewed_at=NOW() WHERE request_id=?")
                 ->execute([$alasan ?: 'Permintaan disetujui.', $_SESSION['user_id'], $req_id]);
            set_flash('Password user berhasil diubah!', 'success');

        } elseif ($action === 'reject') {
            if ($alasan === '') {
                set_flash('Harap isi alasan penolakan!', 'error');
                redirect('superadmin.permintaan-password');
            }
            $conn->prepare("UPDATE password_requests SET status='rejected', alasan=?, reviewed_by=?, reviewed_at=NOW() WHERE request_id=?")
                 ->execute([$alasan, $_SESSION['user_id'], $req_id]);
            set_flash('Permintaan ditolak dan user sudah diberitahu.', 'success');
        }
    }
    redirect('superadmin.permintaan-password');
}

// Ambil semua request pending
$pending = $conn->query("
    SELECT pr.*, u.full_name, u.username, u.email, u.nip
    FROM password_requests pr
    JOIN users u ON pr.user_id = u.user_id
    WHERE pr.status = 'pending'
    ORDER BY pr.created_at ASC
")->fetchAll();

// Ambil riwayat request yang sudah diproses (7 hari terakhir)
$history = $conn->query("
    SELECT pr.*, u.full_name, u.username,
           s.full_name as reviewer_name
    FROM password_requests pr
    JOIN users u ON pr.user_id = u.user_id
    LEFT JOIN users s ON pr.reviewed_by = s.user_id
    WHERE pr.status != 'pending'
    ORDER BY pr.reviewed_at DESC
    LIMIT 20
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permintaan Ganti Password - Super Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        .req-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 22px 24px;
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 20px;
        }
        .req-avatar {
            width: 48px; height: 48px;
            border-radius: 50%;
            background: var(--accent-cyan);
            color: #0a1628;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 700;
            flex-shrink: 0;
        }
        .req-info { flex: 1; }
        .req-name { font-weight: 700; font-size: 16px; margin-bottom: 3px; }
        .req-sub  { font-size: 13px; color: var(--text-secondary); margin-bottom: 12px; }
        .req-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
        .alasan-input {
            width: 100%; padding: 9px 13px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 13px;
            resize: vertical; min-height: 60px;
            margin-bottom: 10px;
        }
        .badge-pending  { background: rgba(255,193,7,0.2); color: #ffc107; padding: 3px 10px; border-radius: 20px; font-size: 12px; }
        .badge-approved { background: rgba(93,216,126,0.2); color: #5dd87e; padding: 3px 10px; border-radius: 20px; font-size: 12px; }
        .badge-rejected { background: rgba(255,107,122,0.2); color: #ff6b7a; padding: 3px 10px; border-radius: 20px; font-size: 12px; }
    </style>
</head>
<body>
<div class="layout">
    <?php include VIEWS . 'shared/sidebar.php'; ?>
    <div class="main-content">
        <?php include VIEWS . 'shared/header.php'; ?>
        <div class="content">

            <h1 class="page-title">🔑 Permintaan Ganti Password</h1>
            <?= flash() ?>

            <!-- PENDING REQUESTS -->
            <div style="margin-bottom:32px">
                <h2 style="font-size:16px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:10px">
                    Menunggu Persetujuan
                    <?php if (count($pending) > 0): ?>
                    <span style="background:#ff6b7a;color:white;border-radius:50%;width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700">
                        <?= count($pending) ?>
                    </span>
                    <?php endif; ?>
                </h2>

                <?php if (empty($pending)): ?>
                <div style="text-align:center;padding:40px;color:var(--text-secondary);background:var(--bg-card);border:1px solid var(--border-color);border-radius:14px">
                    <i class="bi bi-check-circle" style="width:40px;height:40px;margin-bottom:12px;opacity:0.4"></i>
                    <p>Tidak ada permintaan yang menunggu persetujuan.</p>
                </div>
                <?php endif; ?>

                <?php foreach ($pending as $req): ?>
                <div class="req-card" style="border-left: 4px solid #ffc107">
                    <div class="req-avatar"><?= strtoupper(substr($req['full_name'], 0, 1)) ?></div>
                    <div class="req-info">
                        <div class="req-name"><?= htmlspecialchars($req['full_name']) ?></div>
                        <div class="req-sub">
                            NIP: <?= htmlspecialchars($req['nip']) ?> &nbsp;|&nbsp;
                            <?= htmlspecialchars($req['email']) ?> &nbsp;|&nbsp;
                            Diajukan: <strong><?= date('d M Y, H:i', strtotime($req['created_at'])) ?> WIB</strong>
                        </div>

                        <form method="POST" action="?page=superadmin.permintaan-password">
                            <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                            <textarea name="alasan" class="alasan-input"
                                      placeholder="Isi alasan (wajib jika menolak, opsional jika menyetujui)..."></textarea>
                            <div class="req-actions">
                                <button type="submit" name="action" value="approve"
                                        class="btn btn-primary"
                                        style="background:#5dd87e;border-color:#5dd87e;color:#0a1628;padding:10px 24px"
                                        onclick="return confirm('Setujui permintaan ganti password dari <?= addslashes($req['full_name']) ?>?')">
                                    ✅ Setujui
                                </button>
                                <button type="submit" name="action" value="reject"
                                        class="btn btn-danger"
                                        style="padding:10px 24px"
                                        onclick="return checkAlasan(this)">
                                    ❌ Tolak
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- HISTORY -->
            <?php if (!empty($history)): ?>
            <div>
                <h2 style="font-size:16px;font-weight:700;margin-bottom:16px">Riwayat (20 Terakhir)</h2>
                <div class="card" style="padding:0;overflow:hidden">
                    <div style="overflow-x:auto">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Status</th>
                                <th>Alasan</th>
                                <th>Diproses Oleh</th>
                                <th>Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($history as $h): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($h['full_name']) ?></strong><br>
                                <small style="color:var(--text-secondary)"><?= htmlspecialchars($h['username']) ?></small>
                            </td>
                            <td>
                                <span class="badge-<?= $h['status'] ?>">
                                    <?= $h['status'] === 'approved' ? '✅ Disetujui' : '❌ Ditolak' ?>
                                </span>
                            </td>
                            <td style="max-width:250px;font-size:13px">
                                <?= htmlspecialchars($h['alasan'] ?? '-') ?>
                            </td>
                            <td><?= htmlspecialchars($h['reviewer_name'] ?? '-') ?></td>
                            <td style="font-size:13px;white-space:nowrap">
                                <?= date('d M Y, H:i', strtotime($h['reviewed_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<script>
function checkAlasan(btn) {
    const form    = btn.closest('form');
    const alasan  = form.querySelector('textarea[name="alasan"]').value.trim();
    if (!alasan) {
        alert('Harap isi alasan penolakan sebelum menolak permintaan!');
        form.querySelector('textarea[name="alasan"]').focus();
        return false;
    }
    return confirm('Tolak permintaan ini dengan alasan yang sudah diisi?');
}
</script>
</body>
</html>
