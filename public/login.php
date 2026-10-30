<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
if (current_user()) {
    header('Location: dashboard.php');
    exit;
}
$error = isset($_GET['error']) ? 'Sign-in failed. Check your credentials or try again.' : '';
$notice = '';
if (!empty($_GET['registered']) || !empty($_GET['submitted'])) {
    $notice = 'Your account request was submitted. You can sign in once approved by an administrator.';
}

$identity = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Your security token expired. Refresh the page and try again.';
    } else {
        $identity = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($identity === '' || $password === '') {
            $error = 'Please enter both your username or email and password.';
        } else {
            $maxAttempts = (int)($config['login_max_attempts'] ?? 8);
            $windowMinutes = (int)($config['login_window_minutes'] ?? 15);
            $windowSeconds = $windowMinutes * 60;

            $now = time();
            $windowStart = (int)($_SESSION['login_window_start'] ?? 0);
            $failures = (int)($_SESSION['login_failures'] ?? 0);

            if ($windowStart === 0 || ($now - $windowStart) > $windowSeconds) {
                $_SESSION['login_window_start'] = $now;
                $_SESSION['login_failures'] = 0;
                $failures = 0;
            }

            if ($failures >= $maxAttempts) {
                $error = 'Too many failed login attempts. Please wait ' . $windowMinutes . ' minutes before trying again.';
            } else {
                try {
                    if (login_user($identity, $password)) {
                        header('Location: dashboard.php');
                        exit;
                    }

                    $_SESSION['login_failures'] = $failures + 1;

                    // Provide helpful feedback while protecting security
                    $chk = db()->prepare('SELECT is_active, account_status FROM users WHERE email = :e OR username = :u LIMIT 1');
                    $chk->execute(['e' => $identity, 'u' => $identity]);
                    $userRow = $chk->fetch();

                    if ($userRow) {
                        if ($userRow['account_status'] === 'pending') {
                            $error = 'Your account is pending administrator approval. You can sign in once approved.';
                        } elseif ($userRow['account_status'] === 'rejected') {
                            $error = 'Your account request was declined. Please contact your salon administrator.';
                        } elseif ((int)$userRow['is_active'] !== 1) {
                            $error = 'Your account has been deactivated. Please contact your salon administrator.';
                        } else {
                            $error = 'Incorrect password. Please verify your credentials and try again.';
                        }
                    } else {
                        $error = 'No account found with that username or email address.';
                    }
                } catch (Throwable $e) {
                    error_log('SalonDiva sign-in database error: ' . $e->getMessage());
                    $error = 'Unable to connect to the salon database. Please verify MySQL is running in XAMPP.';
                }
            }
        }
    }
}

function sd_login_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#fff6ec">
<title>SalonDiva · Staff sign in</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --pink-1:#FFC2D1; --pink-2:#FF93B0; --cream:#FFF6EC; --yellow:#FFD45C;
  --crimson:#C8264F; --ink:#2B1520; --muted:#6E4A57; --line:#F0D5C4;
  --white:#fff; --shadow:0 28px 80px rgba(91,28,50,.15);
}
*,*::before,*::after{box-sizing:border-box}
html{min-height:100%;scroll-behavior:smooth}
body{margin:0;min-height:100vh;color:var(--ink);font:400 16px/1.5 "Outfit",system-ui,-apple-system,"Segoe UI",sans-serif;background:linear-gradient(135deg,var(--pink-1),var(--pink-2));}
a{color:inherit;text-decoration:none}
button,input{font:inherit}
button,a,input{-webkit-tap-highlight-color:transparent}
:focus-visible{outline:3px solid var(--ink);outline-offset:3px}
.page{min-height:100vh;display:grid;place-items:center;padding:clamp(14px,3vw,38px)}
.shell{width:min(1160px,100%);min-height:min(740px,calc(100vh - 48px));display:grid;grid-template-columns:minmax(0,1.08fr) minmax(390px,.92fr);background:var(--cream);border:1px solid rgba(255,255,255,.72);border-radius:34px;overflow:hidden;box-shadow:var(--shadow)}
.story{position:relative;isolation:isolate;overflow:hidden;display:flex;flex-direction:column;justify-content:space-between;min-height:650px;padding:clamp(28px,4vw,54px);background:linear-gradient(145deg,#fff6ec 5%,#ffe8ed 55%,#ffc2d1 100%)}
.story::before,.story::after{position:absolute;z-index:-1;content:"";border-radius:50%;pointer-events:none}
.story::before{width:390px;height:390px;right:-165px;top:105px;background:radial-gradient(circle at 35% 35%,rgba(255,212,92,.95),rgba(255,212,92,.1) 69%,transparent 70%)}
.story::after{width:300px;height:300px;left:-155px;bottom:-130px;background:rgba(255,147,176,.38)}
.brand{display:inline-flex;align-items:center;gap:12px;width:max-content;font-size:24px;font-weight:800;letter-spacing:-.8px;color:var(--crimson)}
.brandmark{display:grid;place-items:center;width:44px;height:44px;border-radius:16px;background:var(--crimson);color:var(--cream);font-size:25px;box-shadow:0 9px 20px rgba(200,38,79,.2)}
.brand-note{margin-left:2px;color:var(--muted);font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase}
.story-main{max-width:520px;padding:42px 0 35px}
.kicker{display:inline-flex;align-items:center;gap:8px;padding:8px 13px;border:1px solid rgba(200,38,79,.16);border-radius:999px;background:rgba(255,255,255,.64);color:var(--crimson);font-size:11px;letter-spacing:1.25px;text-transform:uppercase;font-weight:800}
.kicker-dot{width:7px;height:7px;border-radius:50%;background:var(--crimson);box-shadow:0 0 0 4px rgba(200,38,79,.1)}
h1{max-width:550px;margin:20px 0 16px;font-size:clamp(42px,5.1vw,66px);line-height:.99;letter-spacing:-2.8px;font-weight:800}
h1 span{color:var(--crimson)}
.intro{max-width:445px;margin:0;color:#5d3e49;font-size:16px;line-height:1.75}
.feature-list{display:grid;gap:12px;margin-top:29px;max-width:410px}
.feature{display:flex;align-items:center;gap:12px;color:#543542;font-size:14px;font-weight:600}
.feature-icon{display:grid;place-items:center;width:34px;height:34px;flex:0 0 34px;border-radius:12px;background:var(--cream);color:var(--crimson);box-shadow:0 4px 12px rgba(91,28,50,.06);font-size:16px}
.story-bottom{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;color:var(--muted);font-size:11px}
.story-bottom strong{color:var(--ink)}
.dot-row{display:flex;align-items:center;gap:5px}.dot-row i{display:block;width:6px;height:6px;border-radius:50%;background:var(--crimson)}.dot-row i:nth-child(2){background:var(--pink-2)}.dot-row i:nth-child(3){background:var(--yellow)}
.login-side{display:grid;place-items:center;padding:clamp(24px,4vw,58px);background:rgba(255,246,236,.95)}
.login-card{width:min(100%,390px)}
.mobile-brand{display:none}
.eyebrow{margin:0 0 8px;color:var(--crimson);font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.9px}
.login-title{margin:0;font-size:clamp(31px,3.2vw,39px);line-height:1.1;letter-spacing:-1.2px}
.login-subtitle{margin:12px 0 25px;color:var(--muted);font-size:14px}
.error{display:flex;gap:10px;align-items:flex-start;margin:0 0 18px;padding:12px 14px;border:1px solid #efb3c4;border-radius:14px;background:#ffe8ee;color:#a41e42;font-size:13px;font-weight:600}
.error-icon{display:grid;place-items:center;flex:0 0 22px;width:22px;height:22px;border-radius:50%;background:#f7ceda;font-weight:800}
.form{display:grid;gap:17px}
.field label{display:block;margin-bottom:7px;font-size:12px;font-weight:800;color:#543542}
.input-wrap{position:relative}
.field input{width:100%;height:54px;border:1.5px solid var(--line);border-radius:15px;padding:0 15px;background:#fff;color:var(--ink);outline:none;transition:border-color .18s,box-shadow .18s}
.field input::placeholder{color:#b09aa3}
.field input:focus{border-color:var(--crimson);box-shadow:0 0 0 4px rgba(200,38,79,.09)}
.password-input{padding-right:76px!important}
.show-password{position:absolute;right:8px;top:50%;transform:translateY(-50%);border:0;background:transparent;padding:8px;color:var(--crimson);font-size:11px;font-weight:800;cursor:pointer;border-radius:8px}
.show-password:hover{background:#fff0f5}
.submit{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;min-height:54px;margin-top:4px;border:0;border-radius:16px;background:var(--crimson);color:#fff;font-weight:800;box-shadow:0 10px 22px rgba(200,38,79,.2);cursor:pointer;transition:transform .15s,background .15s,box-shadow .15s}
.submit:hover{background:#ad1e43;transform:translateY(-1px);box-shadow:0 13px 25px rgba(200,38,79,.25)}
.submit-arrow{font-size:19px;line-height:1}
.create-account-btn{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;min-height:52px;margin-top:12px;padding:0 18px;border:1.5px solid var(--crimson);border-radius:16px;background:var(--cream);color:var(--crimson);font-weight:800;transition:background .15s,color .15s,transform .15s;}
.create-account-btn:hover{background:#fff0f5;transform:translateY(-1px)}
.create-account-btn .submit-arrow{font-size:19px;line-height:1}
.secure-note{display:flex;align-items:center;justify-content:center;gap:7px;margin:15px 0 0;color:#8a737d;font-size:11px}
.secure-note svg{width:14px;height:14px;stroke:var(--crimson);fill:none;stroke-width:1.8}
.separator{display:flex;align-items:center;gap:12px;margin:26px 0 15px;color:#a18a94;font-size:10px;text-transform:uppercase;letter-spacing:1.4px;font-weight:800}
.separator::before,.separator::after{content:"";height:1px;flex:1;background:var(--line)}
.access-note{display:flex;gap:12px;align-items:flex-start;padding:14px;border:1px solid #f0dfd8;border-radius:15px;background:#fffaf6}
.access-icon{display:grid;place-items:center;width:32px;height:32px;flex:0 0 32px;border-radius:11px;background:#ffe8ed;color:var(--crimson)}
.access-note strong{display:block;margin:0 0 3px;font-size:12px}
.access-note p{margin:0;color:var(--muted);font-size:11px;line-height:1.55}.register-link{display:inline-block;margin-top:8px;color:var(--crimson);font-size:12px;font-weight:800;text-decoration:none}.register-link:hover{text-decoration:underline}
.login-footer{margin:24px 0 0;text-align:center;color:#a18a94;font-size:10px}
.login-footer strong{color:var(--crimson)}
@media(max-width:900px){.shell{grid-template-columns:minmax(0,.95fr) minmax(360px,1.05fr)}.story{padding:30px}.story-main{padding:35px 0}h1{font-size:clamp(42px,6vw,57px)}.login-side{padding:28px}}
@media(max-width:680px){.page{padding:0;display:block}.shell{display:flex;flex-direction:column;min-height:100vh;width:100%;border:0;border-radius:0;box-shadow:none}.story{display:none}.login-side{min-height:100vh;padding:32px 22px;align-items:center}.login-card{width:min(100%,420px)}.mobile-brand{display:flex;align-items:center;gap:10px;margin-bottom:42px;color:var(--crimson);font-size:21px;font-weight:800}.mobile-brand .brandmark{width:39px;height:39px;border-radius:13px;font-size:22px}.login-title{font-size:36px}.login-subtitle{margin-bottom:24px}}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{transition:none!important;scroll-behavior:auto!important}}
</style>
</head>
<body>
<div class="page">
  <main class="shell">
    <section class="story" aria-label="SalonDiva overview">
      <a class="brand" href="../index.html"><span class="brandmark" aria-hidden="true">S</span><span>SalonDiva<span class="brand-note" style="display:block">Salon management</span></span></a>
      <div class="story-main">
        <div class="kicker"><span class="kicker-dot" aria-hidden="true"></span> Your salon workspace</div>
        <h1>Your salon,<br><span>beautifully</span><br>in sync.</h1>
        <p class="intro">A calmer way to run the day. Keep your appointments, clients and team organized in one welcoming workspace.</p>
        <div class="feature-list">
          <div class="feature"><span class="feature-icon" aria-hidden="true">▦</span><span>Appointments and daily schedules</span></div>
          <div class="feature"><span class="feature-icon" aria-hidden="true">♡</span><span>Customer records and service history</span></div>
          <div class="feature"><span class="feature-icon" aria-hidden="true">✂</span><span>Staff, services and salon operations</span></div>
        </div>
      </div>
      <div class="story-bottom"><span><strong>Made for your team.</strong> Designed for smoother salon days.</span><span class="dot-row" aria-label="SalonDiva colors"><i></i><i></i><i></i></span></div>
    </section>

    <section class="login-side" aria-labelledby="login-title">
      <div class="login-card">
        <a class="mobile-brand" href="../index.html"><span class="brandmark" aria-hidden="true">S</span><span>SalonDiva</span></a>
        <p class="eyebrow">Staff access</p>
        <h2 class="login-title" id="login-title">Welcome back.</h2>
        <p class="login-subtitle">Sign in to continue to your salon workspace.</p>

        <?php if ($notice !== ''): ?>
          <div class="error" style="background:#eaf7ee;border-color:#b5e5c2;color:#1e6337" role="status"><span class="error-icon" style="background:#cdeed5;color:#1e6337" aria-hidden="true">✓</span><span><?= sd_login_h($notice) ?></span></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
          <div class="error" role="alert"><span class="error-icon" aria-hidden="true">!</span><span><?= sd_login_h($error) ?></span></div>
        <?php endif; ?>

        <form class="form" method="post" action="login.php">
          <input type="hidden" name="_csrf" value="<?= sd_login_h(csrf_token()) ?>">
          <div class="field">
            <label for="username">Username or email</label>
            <input id="username" name="username" type="text" autocomplete="username" placeholder="Enter your username or email" value="<?= sd_login_h($identity) ?>" required autofocus>
          </div>
          <div class="field">
            <label for="password">Password</label>
            <div class="input-wrap">
              <input class="password-input" id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
              <button class="show-password" type="button" id="togglePassword" aria-controls="password" aria-pressed="false">Show</button>
            </div>
          </div>
          <button class="submit" type="submit"><span>Sign in securely</span><span class="submit-arrow" aria-hidden="true">→</span></button>
        </form>
        <a class="create-account-btn" href="register.php"><span>Create now</span><span class="submit-arrow" aria-hidden="true">→</span></a>

        <p class="secure-note"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>Your sign-in is protected by SalonDiva.</p>
        <div class="separator">Authorized team members</div>
        <div class="access-note"><span class="access-icon" aria-hidden="true">✦</span><div><strong>New to SalonDiva?</strong><p>Use the Create now button above to request receptionist or stylist access. An administrator will approve the account before sign-in.</p></div></div>
        <p class="login-footer">© <?= date('Y') ?> <strong>SalonDiva</strong> · Salon management workspace</p>
      </div>
    </section>
  </main>
</div>
<script>
(() => {
  const input = document.getElementById('password');
  const button = document.getElementById('togglePassword');
  if (!input || !button) return;
  button.addEventListener('click', () => {
    const reveal = input.type === 'password';
    input.type = reveal ? 'text' : 'password';
    button.textContent = reveal ? 'Hide' : 'Show';
    button.setAttribute('aria-pressed', String(reveal));
  });
})();
</script>
</body>
</html>
