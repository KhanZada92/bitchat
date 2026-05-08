<?php
require_once 'config/main_config.php';
require_once 'email_notifications.php';

// Already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') { header('Location: admin.php'); exit(); }
    $chk = $conn->prepare("SELECT plan, is_verified, status, otp FROM users WHERE id = ?");
    $chk->bind_param("i", $_SESSION['user_id']); $chk->execute();
    $lr = $chk->get_result()->fetch_assoc(); $chk->close();
    $p = $lr['plan'] ?? null;
    $needs_otp = $lr && (int)($lr['is_verified'] ?? 0) !== 1 && ($lr['status'] ?? '') === 'pending' && !empty($lr['otp']);
    if ($needs_otp) {
        $_SESSION['pending_user_id'] = (int)$_SESSION['user_id'];
        header('Location: verify_otp.php'); exit();
    }
    header('Location: ' . (empty($p) || $p === 'none' ? 'select_plan.php' : 'dashboard.php')); exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email required";
    if (empty($password)) $errors[] = "Password is required";

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id, username, email, password, site_id, role, status, plan, is_verified, otp, email_consent FROM users WHERE email = ?");
        $stmt->bind_param("s", $email); $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['site_id']   = $user['site_id'] ?? '';
                $_SESSION['role']      = $user['role'] ?? 'client';
                $_SESSION['status']    = $user['status'] ?? 'pending';
                $_SESSION['plan']      = $user['plan'] ?? null;
                $_SESSION['is_verified'] = (int)($user['is_verified'] ?? 0);
                $_SESSION['logged_in'] = true;

                // Update last_login
                $conn->query("UPDATE users SET last_login=NOW() WHERE id=".$user['id']);

                // Send login email (non-blocking)
                try {
                    $login_user = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'email_consent' => $user['email_consent'] ?? 0,
                    ];
                    sendLoginEmail($conn, $login_user);
                } catch (Throwable $e) {
                    // Never break login flow if email fails
                    error_log("Login email failed: " . $e->getMessage());
                }

                if ($user['role'] === 'admin') {
                    header('Location: admin.php'); exit();
                }

                // Enforce OTP verification before any plan/dashboard access
                $needs_otp = (int)($user['is_verified'] ?? 0) !== 1 && ($user['status'] ?? '') === 'pending' && !empty($user['otp']);
                if ($needs_otp) {
                    $_SESSION['pending_user_id'] = $user['id'];
                    if (!empty($_SESSION['selected_plan'])) {
                        $_SESSION['pending_plan'] = $_SESSION['selected_plan'];
                    }
                    header('Location: verify_otp.php'); exit();
                }
                
                // Check plan status
                $has_plan = !empty($user['plan']) && $user['plan'] !== 'none';
                
                if ($has_plan) {
                    // Check if plan is expired
                    $stmt_exp = $conn->prepare("SELECT plan_expiry_date FROM users WHERE id = ?");
                    $stmt_exp->bind_param("i", $user['id']);
                    $stmt_exp->execute();
                    $exp_result = $stmt_exp->get_result()->fetch_assoc();
                    $stmt_exp->close();
                    
                    $plan_expired = false;
                    if ($exp_result && !empty($exp_result['plan_expiry_date'])) {
                        $expiry_date = new DateTime($exp_result['plan_expiry_date']);
                        $today = new DateTime();
                        $today->setTime(0, 0, 0);
                        
                        if ($expiry_date < $today) {
                            $plan_expired = true;
                        }
                    }
                    
                    // If plan expired, redirect to select_plan with renew flag
                    if ($plan_expired) {
                        header('Location: select_plan.php?renew=1'); exit();
                    }
                }
                
                header('Location: ' . ($has_plan ? 'dashboard.php' : 'select_plan.php')); exit();
            } else {
                $errors[] = "Incorrect email or password";
            }
        } else {
            $errors[] = "Incorrect email or password";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Bitchatbot</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { font-family:'Inter',sans-serif; }
body { background:#080C14; }
.inp { width:100%; padding:12px 16px; background:#0E1220; border:1px solid rgba(255,255,255,0.08); border-radius:12px; color:white; font-size:14px; outline:none; transition:border-color 0.2s; }
.inp:focus { border-color:#6366f1; }
.inp::placeholder { color:#4B5563; }
.btn { width:100%; padding:13px; background:#4F46E5; color:white; font-weight:700; font-size:15px; border:none; border-radius:12px; cursor:pointer; transition:all 0.2s; box-shadow:0 6px 20px rgba(79,70,229,0.35); }
.btn:hover { background:#4338CA; transform:translateY(-1px); }
label { display:block; font-size:13px; font-weight:600; color:#9CA3AF; margin-bottom:8px; }
</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
<div style="position:fixed;inset:0;background:radial-gradient(ellipse at 50% 0%,rgba(79,70,229,0.12),transparent 60%);pointer-events:none;"></div>

<div style="width:100%;max-width:420px;position:relative;">
    <!-- Logo -->
    <div style="text-align:center;margin-bottom:32px;">
        <div style="width:52px;height:52px;background:linear-gradient(135deg,#4F46E5,#7C3AED);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;box-shadow:0 8px 24px rgba(79,70,229,0.35);">
            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        </div>
        <h1 style="font-size:26px;font-weight:800;color:white;margin-bottom:6px;">Welcome back</h1>
        <p style="font-size:14px;color:#6B7280;">Login to your Bitchatbot account</p>
    </div>

    <!-- Card -->
    <div style="background:#0E1220;border:1px solid rgba(255,255,255,0.07);border-radius:20px;padding:32px;">
        <?php if(!empty($errors)): ?>
        <div id="errBox" style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:12px;padding:14px 16px;margin-bottom:20px;">
            <?php foreach($errors as $e): ?><p style="font-size:13.5px;color:#F87171;margin-bottom:2px;">• <?php echo htmlspecialchars($e); ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" style="display:flex;flex-direction:column;gap:18px;">
            <div>
                <label>Email Address</label>
                <input type="email" name="email" class="inp" value="<?php echo htmlspecialchars($_POST['email']??''); ?>" placeholder="you@example.com" autocomplete="email">
            </div>
            <div>
                <label>Password</label>
                <div style="position:relative;">
                    <input type="password" id="pw" name="password" class="inp" placeholder="••••••••" style="padding-right:48px;" autocomplete="current-password">
                    <button type="button" onclick="tp()" tabindex="-1" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:#6B7280;cursor:pointer;padding:0;display:flex;">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn" style="margin-top:4px;">Login to Dashboard</button>
        </form>

        <div style="display:flex;align-items:center;gap:12px;margin:22px 0;">
            <div style="flex:1;height:1px;background:rgba(255,255,255,0.06);"></div>
            <span style="font-size:12px;color:#4B5563;">OR</span>
            <div style="flex:1;height:1px;background:rgba(255,255,255,0.06);"></div>
        </div>
        <p style="text-align:center;font-size:14px;color:#6B7280;">
            Don't have an account? <a href="register.php" style="color:#818cf8;font-weight:600;text-decoration:none;margin-left:4px;">Create one free →</a>
        </p>
    </div>
</div>

<script>
function tp(){const f=document.getElementById('pw');f.type=f.type==='password'?'text':'password';}
var b=document.getElementById('errBox');
if(b){setTimeout(function(){b.style.transition='opacity 0.5s';b.style.opacity='0';setTimeout(function(){b.style.display='none';},500);},5000);}
</script>
</body>
</html>