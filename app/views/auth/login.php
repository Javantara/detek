<?php
$error       = '';
$email_input = '';
$lupa_sukses = '';

// Handle lupa password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lupa_password'])) {
    $lupa_email    = clean_input($_POST['lupa_email'] ?? '');
    $password_baru = $_POST['lupa_pwd_baru']   ?? '';
    $konfirmasi    = $_POST['lupa_pwd_konfirm'] ?? '';

    $stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$lupa_email]);
    $found_user = $stmt->fetch();

    if (!$found_user) {
        $error = 'Email tidak terdaftar atau akun tidak aktif.';
        $email_input = $lupa_email;
    } elseif (strlen($password_baru) < 6) {
        $error = 'Password baru minimal 6 karakter!';
        $email_input = $lupa_email;
    } elseif ($password_baru !== $konfirmasi) {
        $error = 'Konfirmasi password tidak cocok!';
        $email_input = $lupa_email;
    } else {
        $stmt = $conn->prepare("SELECT request_id FROM password_requests WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$found_user['user_id']]);
        if ($stmt->fetch()) {
            $error = 'Permintaan ganti password sebelumnya masih menunggu persetujuan admin.';
            $email_input = $lupa_email;
        } else {
            $hash = password_hash($password_baru, PASSWORD_DEFAULT);
            $conn->prepare("INSERT INTO password_requests (user_id, new_password, status) VALUES (?, ?, 'pending')")
                 ->execute([$found_user['user_id'], $hash]);
            $lupa_sukses = 'Permintaan berhasil dikirim! Tunggu persetujuan Super Admin.';
        }
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['lupa_password'])) {
    $email_input = clean_input($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';

    if ($email_input && $password) {
        $stmt = $conn->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.email = ? AND u.status = 'active'");
        $stmt->execute([$email_input]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role_name'];
            $_SESSION['role_id']   = $user['role_id'];
            $_SESSION['nip']       = $user['nip'];
            $conn->prepare("UPDATE users SET updated_at = NOW() WHERE user_id = ?")->execute([$user['user_id']]);
            redirect($user['role_name'] === 'superadmin' ? 'superadmin.dashboard' : 'select-plant');
        } else {
            $stmt2 = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND status = 'active'");
            $stmt2->execute([$email_input]);
            $error = $stmt2->fetch() ? 'Password salah!' : 'Email tidak ditemukan atau akun tidak aktif.';
        }
    } else {
        $error = 'Email dan password harus diisi!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PLN Dashboard</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260224">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        .modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);backdrop-filter:blur(4px);z-index:999;align-items:center;justify-content:center; }
        .modal-overlay.show { display:flex; }
        .modal-box { background:var(--bg-card);border:1px solid var(--border-color);border-radius:16px;padding:32px;width:100%;max-width:420px;margin:20px;animation:slideUp .25s ease; }
        @keyframes slideUp { from{transform:translateY(30px);opacity:0} to{transform:translateY(0);opacity:1} }
    </style>
</head>
<body>
    <div class="login-theme-toggle">
        <button id="themeToggle" class="theme-toggle" onclick="toggleTheme()" title="Ganti Tema">
            <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
        </button>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="logo-section" style="text-align:center;margin-bottom:40px">
                <div class="logo" style="justify-content:center">
                    <img src="<?= BASE_URL ?>assets/img/logo-pln.png" alt="PLN" style="width:60px">
                    <span class="logo-text" style="font-size:36px">PLN</span>
                </div>
                <p style="color:var(--text-secondary);margin-top:10px;font-size:14px">Performance Test System</p>
            </div>

            <?php if ($lupa_sukses): ?>
            <div style="background:rgba(93,216,126,0.12);border:1px solid rgba(93,216,126,0.35);color:#5dd87e;border-radius:10px;padding:14px 16px;margin-bottom:20px;font-size:14px;display:flex;gap:10px;align-items:center">
                <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($lupa_sukses) ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:20px">
                <i class="bi bi-exclamation-circle-fill" style="margin-right:6px"></i><?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>">
                <input type="hidden" name="page" value="login">

                <div class="form-group">
                    <label class="form-label" style="display:flex;align-items:center;gap:6px">
                        <i class="bi bi-envelope" style="font-size:14px"></i> EMAIL *
                    </label>
                    <input type="email" name="email" class="form-control"
                           placeholder="nama@pln.co.id"
                           value="<?= htmlspecialchars($email_input) ?>"
                           id="emailInput" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" style="display:flex;align-items:center;gap:6px">
                        <i class="bi bi-lock" style="font-size:14px"></i> PASSWORD *
                    </label>
                    <div class="input-with-icon">
                        <input type="password" name="password" id="password"
                               class="form-control" placeholder="Masukkan password"
                               style="padding-left:14px;padding-right:44px" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;margin-top:25px;display:flex;align-items:center;justify-content:center;gap:8px">
                    <i class="bi bi-box-arrow-in-right" style="font-size:18px"></i> Login
                </button>
            </form>

            <?php if (empty($lupa_sukses)): ?>
            <p style="text-align:center;margin-top:20px;color:var(--text-secondary);font-size:13px">
                <i class="bi bi-question-circle"></i> Lupa password?
                <button type="button" onclick="openLupa()"
                        style="background:none;border:none;color:var(--accent-cyan);cursor:pointer;font-size:13px;text-decoration:underline;padding:0">
                    Klik di sini
                </button>
            </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Lupa Password -->
    <div class="modal-overlay" id="lupaModal">
        <div class="modal-box">
            <div style="font-size:18px;font-weight:700;margin-bottom:6px">🔑 Lupa Password?</div>
            <div style="font-size:13px;color:var(--text-secondary);margin-bottom:24px">
                Isi password baru. Permintaan dikirim ke Super Admin untuk disetujui.
            </div>
            <form method="POST" action="<?= BASE_URL ?>">
                <input type="hidden" name="page" value="login">
                <input type="hidden" name="lupa_password" value="1">
                <div class="form-group">
                    <label class="form-label" style="display:flex;align-items:center;gap:6px">
                        <i class="bi bi-envelope" style="font-size:13px"></i> Email Akun
                    </label>
                    <input type="email" name="lupa_email" class="form-control" id="lupaEmail"
                           value="<?= htmlspecialchars($email_input) ?>"
                           placeholder="nama@pln.co.id" required>
                </div>
                <div class="form-group">
                    <label class="form-label" style="display:flex;align-items:center;gap:6px">
                        <i class="bi bi-lock" style="font-size:13px"></i> Password Baru
                    </label>
                    <input type="password" name="lupa_pwd_baru" id="lupaPwdBaru"
                           class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label" style="display:flex;align-items:center;gap:6px">
                        <i class="bi bi-lock-fill" style="font-size:13px"></i> Konfirmasi Password Baru
                    </label>
                    <input type="password" name="lupa_pwd_konfirm" id="lupaPwdKonfirm"
                           class="form-control" placeholder="Ulangi password baru" required>
                    <small id="lupaMatchMsg" style="font-size:12px;margin-top:4px;display:block"></small>
                </div>
                <div style="display:flex;gap:10px;margin-top:8px">
                    <button type="submit" class="btn btn-primary" style="flex:1;padding:12px">Kirim Permintaan</button>
                    <button type="button" onclick="closeLupa()" class="btn btn-secondary" style="padding:12px 20px">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openLupa() {
            const email = document.getElementById('emailInput')?.value || '';
            document.getElementById('lupaEmail').value = email;
            document.getElementById('lupaModal').classList.add('show');
        }
        function closeLupa() { document.getElementById('lupaModal').classList.remove('show'); }
        document.getElementById('lupaModal').addEventListener('click', e => { if (e.target===document.getElementById('lupaModal')) closeLupa(); });

        document.getElementById('lupaPwdKonfirm')?.addEventListener('input', function() {
            const msg = document.getElementById('lupaMatchMsg');
            const baru = document.getElementById('lupaPwdBaru').value;
            if (!this.value) { msg.textContent=''; return; }
            msg.textContent = this.value===baru ? '✅ Password cocok' : '❌ Password tidak cocok';
            msg.style.color = this.value===baru ? '#5dd87e' : '#ff6b7a';
        });

        function togglePassword() {
            const input = document.getElementById('password');
            const icon  = document.getElementById('eyeIcon');
            input.type  = input.type === 'password' ? 'text' : 'password';
            icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
        }

        function toggleTheme() {
            const isLight = document.body.classList.toggle('light-theme');
            document.getElementById('themeIcon').className = isLight ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
            localStorage.setItem('theme', isLight ? 'light' : 'dark');
        }
        window.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('theme') === 'light') {
                document.body.classList.add('light-theme');
                document.getElementById('themeIcon').className = 'bi bi-sun-fill';
            }
            if (window.history.replaceState) window.history.replaceState(null,'',window.location.pathname);
        });
        // Terapkan tema dari localStorage langsung (sebelum DOMContentLoaded)
        if (localStorage.getItem('theme') === 'light') document.body.classList.add('light-theme');
    </script>
</body>
</html>
