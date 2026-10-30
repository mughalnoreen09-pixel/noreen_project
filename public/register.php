<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

$error = '';
$success = '';
if (!empty($_SESSION['registration_success'])) {
    $success = (string) $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
}

function sd_register_e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function sd_register_name_length(string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : null)) {
        http_response_code(400);
        $error = 'Your security token expired. Please refresh the page and try again.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        $role = (string) ($_POST['role'] ?? '');

        // Validate one field at a time so the visitor sees exactly what needs fixing.
        if ($name === '') {
            $error = 'Please enter your full name.';
        } elseif (sd_register_name_length($name) > 120) {
            $error = 'Your full name must be 120 characters or fewer.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address, such as name@example.com.';
        } elseif (strlen($email) > 254) {
            $error = 'Your email address is too long.';
        } elseif ($username === '') {
            $error = 'Please choose a username.';
        } elseif (strlen($username) > 80) {
            $error = 'Your username must be 80 characters or fewer.';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
            $error = 'Username may contain only English letters, numbers, dots (.), underscores (_), and hyphens (-). Do not include spaces or @.';
        } elseif (strlen($password) < 12) {
            $error = 'Your password is ' . strlen($password) . ' characters long. It must contain at least 12 characters.';
        } elseif (strlen($password) > 4096) {
            $error = 'Your password is too long. Please choose a shorter password.';
        } elseif ($password !== $confirm) {
            $error = 'The password and confirmation do not match. Re-enter the same password in both fields.';
        } elseif (!in_array($role, ['receptionist', 'stylist'], true)) {
            $error = 'Please choose Receptionist or Stylist as the account type.';
        } else {
            try {
                $stmt = db()->prepare(
                    "INSERT INTO users (name, email, username, password_hash, role, is_active, account_status)
                     VALUES (:name, :email, :username, :hash, :role, 1, 'pending')"
                );
                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'username' => $username,
                    'hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                ]);
                $_SESSION['registration_success'] = 'Your account request was submitted successfully. It is now waiting for administrator approval. You can sign in after your request is approved.';
                header('Location: register.php?submitted=1');
                exit;
            } catch (PDOException $e) {
                if ((string) $e->getCode() === '23000') {
                    $error = 'That email or username is already registered. Try signing in or use different details.';
                } else {
                    error_log('SalonDiva registration database error: ' . $e->getMessage());
                    http_response_code(503);
                    $error = 'Registration could not be saved. Please check the database connection and users table, then try again.';
                }
            }
        }
    }
}
$oldName = (string) ($_POST['name'] ?? '');
$oldEmail = (string) ($_POST['email'] ?? '');
$oldUsername = (string) ($_POST['username'] ?? '');
$oldRole = (string) ($_POST['role'] ?? 'receptionist');
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SalonDiva · Create account</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap');
:root{--pink:#ffc2d1;--pink2:#ff93b0;--cream:#fff6ec;--yellow:#ffd45c;--crimson:#c8264f;--ink:#2b1520;--muted:#6e4a57;--line:#f0d5c4}
*{box-sizing:border-box}body{margin:0;min-height:100vh;padding:24px;display:grid;place-items:center;background:linear-gradient(135deg,var(--pink),var(--pink2));font:16px/1.5 Outfit,system-ui,sans-serif;color:var(--ink)}
.card{width:min(100%,560px);background:var(--cream);padding:clamp(24px,5vw,42px);border-radius:28px;box-shadow:0 22px 70px #8f24452b}.brand{font-size:1.7rem;font-weight:800;color:var(--crimson);letter-spacing:-.04em}.eyebrow{color:var(--crimson);font-weight:800;text-transform:uppercase;font-size:.75rem;letter-spacing:.13em;margin-top:22px}
h1{font-size:2rem;line-height:1.1;margin:.45rem 0}.muted{color:var(--muted);margin-top:0}label{display:block;font-weight:600;font-size:.9rem;margin:14px 0 6px}
input,select{width:100%;padding:13px 14px;border:1px solid #e8d5da;background:white;border-radius:12px;font:inherit;color:var(--ink);outline-color:var(--crimson)}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.btn{width:100%;border:0;border-radius:999px;background:var(--crimson);color:white;padding:14px;font:inherit;font-weight:700;margin-top:22px;cursor:pointer}.notice{padding:12px 14px;border-radius:12px;margin:14px 0;background:#fff0c7;color:#624713}.notice.error{background:#ffe2e6;color:#8f1737}.notice.success{background:#e5f7e9;color:#20572e}.foot{text-align:center;margin-top:18px;color:var(--muted)}a{color:var(--crimson);font-weight:700}.hint{font-size:.8rem;color:var(--muted);margin:8px 0 0}
@media(max-width:480px){body{padding:12px}.grid{grid-template-columns:1fr}.card{border-radius:20px}}
</style></head><body><main class="card">
<div class="brand">SalonDiva</div><div class="eyebrow">Join the team</div><h1>Create your account</h1>
<p class="muted">Request receptionist or stylist access. Your request appears in the administrator's <strong>Staff Accounts</strong> page. You can sign in after it is approved.</p>
<?php if ($error !== ''): ?><div class="notice error" role="alert"><?= sd_register_e($error) ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="notice success" role="status"><?= sd_register_e($success) ?></div><?php endif; ?>
<form method="post" action="register.php">
<input type="hidden" name="_csrf" value="<?= sd_register_e(csrf_token()) ?>">
<label for="name">Full name</label><input id="name" name="name" autocomplete="name" maxlength="120" required value="<?= sd_register_e($oldName) ?>">
<div class="grid"><div><label for="email">Email address</label><input id="email" type="email" name="email" autocomplete="email" maxlength="254" required value="<?= sd_register_e($oldEmail) ?>"></div>
<div><label for="username">Username</label><input id="username" name="username" autocomplete="username" maxlength="80" pattern="[A-Za-z0-9_.-]+" required value="<?= sd_register_e($oldUsername) ?>"></div></div>
<label for="role">Account type</label><select id="role" name="role" required><option value="receptionist" <?= $oldRole === 'receptionist' ? 'selected' : '' ?>>Receptionist</option><option value="stylist" <?= $oldRole === 'stylist' ? 'selected' : '' ?>>Stylist</option></select>
<div class="grid"><div><label for="password">Password (at least 12 characters)</label><input id="password" type="password" name="password" minlength="12" maxlength="4096" autocomplete="new-password" required aria-describedby="password-hint"><p class="hint" id="password-hint">Use 12 or more characters. Your password is not shown again if the form has an error.</p></div>
<div><label for="confirm_password">Confirm password</label><input id="confirm_password" type="password" name="confirm_password" minlength="12" maxlength="4096" autocomplete="new-password" required></div></div>
<button class="btn" type="submit">Submit account request</button><p class="hint">For security, administrator accounts cannot be created from public registration.</p>
</form><div class="foot">Already have an account? <a href="login.php">Staff sign in</a> · <a href="../index.html">Back to SalonDiva</a></div>
</main>
<script>
(() => {
  const password = document.getElementById('password');
  const confirm = document.getElementById('confirm_password');
  if (!password || !confirm) return;
  function checkMatch() {
    if (confirm.value && password.value !== confirm.value) {
      confirm.setCustomValidity('Passwords do not match.');
    } else {
      confirm.setCustomValidity('');
    }
  }
  password.addEventListener('input', checkMatch);
  confirm.addEventListener('input', checkMatch);
})();
</script>
</body></html>
