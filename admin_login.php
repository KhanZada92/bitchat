<?php
require_once 'config/main_config.php';

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: admin.php'); exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE email=? AND role='admin'");
    $stmt->bind_param("s", $email); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    
    if ($row && password_verify($pass, $row['password'])) {
        $_SESSION['user_id']  = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role']     = $row['role'];
        $_SESSION['logged_in'] = true;
        header('Location: admin.php'); exit();
    } else {
        $error = 'Invalid admin credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Login — Bitchatbot</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
* { box-sizing:border-box; margin:0; padding:0; font-family:'Plus Jakarta Sans',sans-serif; }
body { background:#0A0A0F; min-height:100vh; display:flex; align-items:center; justify-content:center; }
.box { width:100%; max-width:380px; background:#111118; border:1px solid rgba(255,255,255,0.07); border-radius:20px; padding:36px; }
.logo { display:flex; align-items:center; gap:10px; margin-bottom:28px; justify-content:center; }
.logo-icon { width:34px; height:34px; background:linear-gradient(135deg,#7C3AED,#06B6D4); border-radius:10px; display:flex; align-items:center; justify-content:center; }
h1 { font-size:18px; font-weight:800; color:white; text-align:center; margin-bottom:6px; }
p.sub { font-size:12.5px; color:#6B7280; text-align:center; margin-bottom:24px; }
label { font-size:11.5px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.05em; display:block; margin-bottom:6px; }
input { width:100%; background:#1A1A26; border:1px solid rgba(255,255,255,0.07); border-radius:10px; padding:10px 14px; color:white; font-size:13.5px; outline:none; margin-bottom:14px; }
input:focus { border-color:#7C3AED; }
.btn { width:100%; background:#7C3AED; color:white; border:none; padding:12px; border-radius:10px; font-weight:700; font-size:14px; cursor:pointer; margin-top:4px; }
.btn:hover { background:#8B5CF6; }
.error { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2); color:#F87171; font-size:12.5px; padding:10px 14px; border-radius:8px; margin-bottom:16px; }
.back { text-align:center; margin-top:18px; font-size:12.5px; color:#6B7280; }
.back a { color:#A78BFA; text-decoration:none; font-weight:600; }
</style>
</head>
<body>
<div class="box">
  <div class="logo">
    <div class="logo-icon">
      <svg fill="none" stroke="white" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
    </div>
    <span style="font-size:16px;font-weight:800;color:white;">Bitchatbot Admin</span>
  </div>
  <h1>Admin Access</h1>
  <p class="sub">Sign in to the admin control panel</p>
  <?php if($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <form method="POST">
    <label>Email</label>
    <input type="email" name="email" placeholder="admin@bitchatbot.io" required autofocus>
    <label>Password</label>
    <input type="password" name="password" placeholder="••••••••" required>
    <button type="submit" class="btn">Sign In as Admin</button>
  </form>
  <div class="back"><a href="login.php">← User Login</a> · <a href="/">Home</a></div>
</div>
</body>
</html>