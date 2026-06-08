<?php
session_start();

// Flag: tells header.php to skip sidebar layout
$loginPage = true;
$pageTitle  = 'Login';

// Redirect if already logged in
if (isset($_SESSION['Username'])) {
    header('Location: dashboard.php');
    exit();
}

// Load only DB connection + helpers (header.php will return early because $loginPage = true)
include 'init.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['user'] ?? '');
    $password   = $_POST['pass'] ?? '';
    $hashedPass = sha1($password);

    $stmt = $con->prepare("
        SELECT UserID, Username, Password
        FROM   users
        WHERE  Username = ?
        AND    Password = ?
        AND    GroupID  = 1
        LIMIT  1
    ");
    $stmt->execute([$username, $hashedPass]);
    $row = $stmt->fetch();

    if ($row) {
        $_SESSION['Username'] = $row['Username'];
        $_SESSION['ID']       = $row['UserID'];
        header('Location: dashboard.php');
        exit();
    } else {
        $error = 'Username atau password salah. Silakan coba lagi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — MakanLokal</title>
  <link rel="stylesheet" href="<?= $css ?>bootstrap.min.css"/>
  <link rel="stylesheet" href="<?= $css ?>font-awesome.min.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:ital,wght@0,400;0,500;0,600;0,700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #0B1525;
      /* subtle dot-grid pattern */
      background-image: radial-gradient(rgba(255,255,255,.04) 1px, transparent 1px);
      background-size: 28px 28px;
      padding: 20px;
    }

    /* Ambient glow blobs */
    body::before, body::after {
      content: '';
      position: fixed;
      border-radius: 50%;
      filter: blur(80px);
      pointer-events: none;
      z-index: 0;
    }
    body::before {
      width: 480px; height: 480px;
      background: rgba(17, 30, 64, .7);
      top: -120px; left: -120px;
    }
    body::after {
      width: 380px; height: 380px;
      background: rgba(181, 39, 42, .12);
      bottom: -80px; right: -80px;
    }

    /* ---- Card ---- */
    .login-wrapper {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 420px;
    }

    .login-card {
      background: rgba(255,255,255,.97);
      border-radius: 20px;
      padding: 44px 40px 36px;
      box-shadow:
        0 0 0 1px rgba(255,255,255,.08),
        0 24px 64px rgba(0,0,0,.4),
        0 8px 24px rgba(0,0,0,.25);
    }

    /* ---- Brand ---- */
    .brand {
      text-align: center;
      margin-bottom: 32px;
    }
    .brand-icon {
      width: 56px; height: 56px;
      background: linear-gradient(135deg, #111E40 0%, #1B2E5E 100%);
      border-radius: 16px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 16px;
      box-shadow: 0 8px 28px rgba(17,30,64,.35);
    }
    .brand-icon i { color: #F4A261; font-size: 22px; }
    .brand h1 {
      font-family: 'Playfair Display', serif;
      font-size: 26px;
      font-weight: 900;
      color: #111E40;
      line-height: 1;
      margin-bottom: 6px;
    }
    .brand h1 span { color: #F4A261; }
    .brand p {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 1.2px;
      color: #9A9AB0;
    }

    /* ---- Form elements ---- */
    .form-group { margin-bottom: 20px; }

    label {
      display: block;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .7px;
      color: #4A4A6A;
      margin-bottom: 6px;
    }

    .input-wrap { position: relative; }

    .input-wrap i.prefix {
      position: absolute;
      left: 14px; top: 50%;
      transform: translateY(-50%);
      color: #B0B4C8;
      font-size: 14px;
      pointer-events: none;
    }

    .form-input {
      width: 100%;
      border: 1.5px solid #DDE1EC;
      border-radius: 10px;
      padding: 11px 14px 11px 40px;
      font-size: 14px;
      font-family: 'DM Sans', sans-serif;
      color: #1B2E5E;
      background: #fff;
      outline: none;
      transition: border-color .2s, box-shadow .2s;
      appearance: none;
    }
    .form-input:focus {
      border-color: #111E40;
      box-shadow: 0 0 0 3px rgba(17,30,64,.08);
    }
    .form-input::placeholder { color: #C0C4D6; }

    .eye-btn {
      position: absolute;
      right: 12px; top: 50%;
      transform: translateY(-50%);
      background: none; border: none;
      cursor: pointer;
      color: #B0B4C8;
      font-size: 14px;
      padding: 2px 4px;
      line-height: 1;
      transition: color .2s;
    }
    .eye-btn:hover { color: #4A4A6A; }

    /* ---- Alert ---- */
    .alert-error {
      background: #FDECEA;
      color: #9B1C1C;
      border: 1px solid #F8C4C4;
      border-radius: 10px;
      padding: 11px 14px;
      font-size: 13px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 9px;
    }
    .alert-error i { font-size: 15px; flex-shrink: 0; }

    /* ---- Submit button ---- */
    .btn-login {
      width: 100%;
      background: #111E40;
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 13px;
      font-size: 14px;
      font-weight: 600;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      transition: background .2s, transform .1s, box-shadow .2s;
      letter-spacing: .2px;
      box-shadow: 0 4px 16px rgba(17,30,64,.25);
      margin-top: 4px;
    }
    .btn-login:hover {
      background: #1B2E5E;
      box-shadow: 0 6px 20px rgba(17,30,64,.35);
    }
    .btn-login:active { transform: translateY(1px); }
    .btn-login i { margin-right: 7px; }

    /* ---- Footer ---- */
    .login-footer {
      text-align: center;
      font-size: 11px;
      color: rgba(255,255,255,.25);
      margin-top: 20px;
      letter-spacing: .3px;
    }
  </style>
</head>
<body>
  <div class="login-wrapper">

    <div class="login-card">
      <!-- Brand -->
      <div class="brand">
        <div class="brand-icon">
          <i class="fa fa-lock"></i>
        </div>
        <h1>Makan<span>Lokal</span></h1>
        <p>Admin Panel</p>
      </div>

      <!-- Error message -->
      <?php if ($error): ?>
      <div class="alert-error">
        <i class="fa fa-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <!-- Login form -->
      <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">

        <div class="form-group">
          <label for="user">Username</label>
          <div class="input-wrap">
            <i class="fa fa-user prefix"></i>
            <input
              type="text"
              id="user"
              name="user"
              class="form-input"
              placeholder="Masukkan username"
              autocomplete="off"
              value="<?= htmlspecialchars($_POST['user'] ?? '') ?>"
              required
            />
          </div>
        </div>

        <div class="form-group">
          <label for="pass">Password</label>
          <div class="input-wrap">
            <i class="fa fa-key prefix"></i>
            <input
              type="password"
              id="pass"
              name="pass"
              class="form-input"
              placeholder="Masukkan password"
              autocomplete="current-password"
              required
            />
            <button type="button" class="eye-btn" onclick="togglePassword(this)" title="Tampilkan password">
              <i class="fa fa-eye"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-login">
          <i class="fa fa-sign-in"></i> Masuk ke Admin Panel
        </button>

      </form>
    </div>

    <p class="login-footer">© 2026 MakanLokal Admin Panel — Universitas Buddhi Dharma</p>
  </div>

  <script>
    function togglePassword(btn) {
      const input = document.getElementById('pass');
      const icon  = btn.querySelector('i');
      if (input.type === 'password') {
        input.type   = 'text';
        icon.className = 'fa fa-eye-slash';
      } else {
        input.type   = 'password';
        icon.className = 'fa fa-eye';
      }
    }
    // Auto-focus username field
    document.getElementById('user').focus();
  </script>
</body>
</html>