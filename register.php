<?php
require_once 'config/main_config.php';

// Already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') { header('Location: admin.php'); exit(); }
    $chk = $conn->prepare("SELECT plan FROM users WHERE id = ?");
    $chk->bind_param("i", $_SESSION['user_id']); $chk->execute();
    $p = $chk->get_result()->fetch_assoc()['plan'] ?? null; $chk->close();
    header('Location: ' . (empty($p) || $p === 'none' ? 'select_plan.php' : 'dashboard.php')); exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username         = trim($_POST['username'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || strlen($username) < 3) $errors[] = "Username must be at least 3 characters";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email required";
    if (empty($password) || strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email); $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $errors[] = "Username or email already exists";
        $stmt->close();
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        // plan = NULL — forces select_plan after login
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status, plan) VALUES (?, ?, ?, 'client', 'pending', NULL)");
        $stmt->bind_param("sss", $username, $email, $hashed);

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            // Auto-login
            session_regenerate_id(true);
            $_SESSION['user_id']   = $new_id;
            $_SESSION['username']  = $username;
            $_SESSION['email']     = $email;
            $_SESSION['site_id']   = '';
            $_SESSION['role']      = 'client';
            $_SESSION['status']    = 'pending';
            $_SESSION['plan']      = null;
            $_SESSION['logged_in'] = true;
            header('Location: select_plan.php'); exit();
        } else {
            $errors[] = "Registration failed. Please try again.";
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
<title>Register — Bitchat</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { font-family:'Inter',sans-serif; }
body { background:#080C14; }
.inp { width:100%; padding:11px 14px; background:#0E1220; border:1px solid rgba(255,255,255,0.08); border-radius:11px; color:white; font-size:14px; outline:none; transition:border-color 0.2s; }
.inp:focus { border-color:#6366f1; }
.inp::placeholder { color:#4B5563; }
.btn { width:100%; padding:12px; background:#4F46E5; color:white; font-weight:700; font-size:14.5px; border:none; border-radius:11px; cursor:pointer; transition:all 0.2s; box-shadow:0 6px 20px rgba(79,70,229,0.35); }
.btn:hover { background:#4338CA; transform:translateY(-1px); }
label { display:block; font-size:12.5px; font-weight:600; color:#9CA3AF; margin-bottom:7px; }
</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
<div style="position:fixed;inset:0;background:radial-gradient(ellipse at 50% 0%,rgba(79,70,229,0.1),transparent 60%);pointer-events:none;"></div>

<div style="width:100%;max-width:400px;position:relative;">
    <!-- Logo -->
    <div style="text-align:center;margin-bottom:28px;">
        <div style="width:50px;height:50px;background:linear-gradient(135deg,#4F46E5,#7C3AED);border-radius:15px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;box-shadow:0 8px 24px rgba(79,70,229,0.35);">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        </div>
        <h1 style="font-size:24px;font-weight:800;color:white;margin-bottom:5px;">Create your account</h1>
        <p style="font-size:13.5px;color:#6B7280;">Join Bitchat and deploy your AI chatbot</p>
    </div>

    <!-- Card -->
    <div style="background:#0E1220;border:1px solid rgba(255,255,255,0.07);border-radius:20px;padding:28px;">
        <?php if(!empty($errors)): ?>
        <div id="errBox" style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:11px;padding:12px 14px;margin-bottom:18px;">
            <?php foreach($errors as $e): ?><p style="font-size:13px;color:#F87171;margin-bottom:2px;">• <?php echo htmlspecialchars($e); ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label>Username</label>
                <input type="text" name="username" class="inp" value="<?php echo htmlspecialchars($_POST['username']??''); ?>" placeholder="yourname" autocomplete="username">
            </div>
            <div>
                <label>Email Address</label>
                <input type="email" name="email" class="inp" value="<?php echo htmlspecialchars($_POST['email']??''); ?>" placeholder="you@example.com" autocomplete="email">
            </div>
            <div>
                <label>Password</label>
                <div style="position:relative;">
                    <input type="password" id="pw1" name="password" class="inp" placeholder="Min 6 characters" style="padding-right:44px;" autocomplete="new-password">
                    <button type="button" onclick="tp('pw1')" tabindex="-1" style="position:absolute;right:13px;top:50%;transform:translateY(-50%);background:none;border:none;color:#6B7280;cursor:pointer;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
            </div>
            <div>
                <label>Confirm Password</label>
                <div style="position:relative;">
                    <input type="password" id="pw2" name="confirm_password" class="inp" placeholder="Repeat password" style="padding-right:44px;" autocomplete="new-password">
                    <button type="button" onclick="tp('pw2')" tabindex="-1" style="position:absolute;right:13px;top:50%;transform:translateY(-50%);background:none;border:none;color:#6B7280;cursor:pointer;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn" style="margin-top:4px;">Create Account →</button>
        </form>

        <div style="display:flex;align-items:center;gap:10px;margin:20px 0;">
            <div style="flex:1;height:1px;background:rgba(255,255,255,0.05);"></div>
            <span style="font-size:12px;color:#374151;">OR</span>
            <div style="flex:1;height:1px;background:rgba(255,255,255,0.05);"></div>
        </div>
        <p style="text-align:center;font-size:13.5px;color:#6B7280;">
            Already have an account? <a href="login.php" style="color:#818cf8;font-weight:600;text-decoration:none;margin-left:3px;">Login →</a>
        </p>
    </div>

    <!-- Step indicator -->
    <div style="margin-top:24px;display:flex;align-items:center;justify-content:center;">
        <?php foreach([['Register',true],['Select Plan',false],['Dashboard',false]] as $i=>[$s,$a]): ?>
        <div style="display:flex;align-items:center;">
            <div style="display:flex;flex-direction:column;align-items:center;gap:5px;">
                <div style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;background:<?php echo $a?'#4F46E5':'rgba(255,255,255,0.07)';?>;color:<?php echo $a?'white':'#4B5563';?>;border:<?php echo $a?'2px solid #4F46E5':'1px solid rgba(255,255,255,0.08)';?>;"><?php echo $i+1;?></div>
                <span style="font-size:10.5px;color:<?php echo $a?'#a5b4fc':'#4B5563';?>;font-weight:<?php echo $a?'600':'400';?>;"><?php echo $s;?></span>
            </div>
            <?php if($i<2): ?><div style="width:36px;height:1px;background:rgba(255,255,255,0.06);margin:0 4px;margin-bottom:18px;"></div><?php endif;?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function tp(id){const f=document.getElementById(id);f.type=f.type==='password'?'text':'password';}
var b=document.getElementById('errBox');
if(b){setTimeout(function(){b.style.transition='opacity 0.5s';b.style.opacity='0';setTimeout(function(){b.style.display='none';},500);},5000);}
</script>
</body>
</html>