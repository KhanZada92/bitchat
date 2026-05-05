<?php
require_once 'config/main_config.php';
require_once 'email_notifications.php';

if (!isset($_SESSION['pending_user_id'])) {
    header('Location: register.php'); exit();
}

$user_id = $_SESSION['pending_user_id'];
$selected_plan = $_SESSION['pending_plan'] ?? '';
$errors = [];
$success = '';

$stmt = $conn->prepare("SELECT id, username, email, otp, otp_expires_at, is_verified FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) { header('Location: register.php'); exit(); }
if ($user['is_verified']) {
    $redirect = !empty($selected_plan) ? 'select_plan.php?plan=' . $selected_plan : 'select_plan.php';
    header('Location: ' . $redirect); exit();
}

if (isset($_GET['resend'])) {
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $stmt = $conn->prepare("UPDATE users SET otp = ?, otp_expires_at = ? WHERE id = ?");
    $stmt->bind_param("ssi", $otp, $otp_expires, $user_id);
    $stmt->execute(); $stmt->close();
    sendOTPEmail($conn, $user, $otp);
    $success = "New OTP sent to " . $user['email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = trim($_POST['otp'] ?? '');
    if (empty($entered_otp) || strlen($entered_otp) !== 6) {
        $errors[] = "Please enter the 6-digit OTP";
    } elseif (strtotime($user['otp_expires_at']) < time()) {
        $errors[] = "OTP has expired. <a href='verify_otp.php?resend=1' style='color:#818cf8;'>Click here to resend</a>";
    } elseif ($entered_otp !== $user['otp']) {
        $errors[] = "Invalid OTP. Please try again";
    } else {
        $stmt = $conn->prepare("UPDATE users SET is_verified = 1, otp = NULL, otp_expires_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute(); $stmt->close();

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['email']     = $user['email'];
        $_SESSION['role']      = 'client';
        $_SESSION['status']    = 'pending';
        $_SESSION['plan']      = null;
        $_SESSION['logged_in'] = true;
        unset($_SESSION['pending_user_id'], $_SESSION['pending_plan']);

        $welcome_user = ['id' => $user['id'], 'username' => $user['username'], 'email' => $user['email'], 'email_consent' => 1];
        sendWelcomeEmail($conn, $welcome_user);

        $redirect = !empty($selected_plan) ? 'select_plan.php?plan=' . $selected_plan : 'select_plan.php';
        header('Location: ' . $redirect); exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Email — Bitchatbot</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{font-family:'Inter',sans-serif;box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;}
body{
    background:#080C14;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
}
.page-wrap{
    width:100%;
    max-width:420px;
    margin:0 auto;
}
.otp-inp{
    width:52px;
    height:58px;
    text-align:center;
    font-size:22px;
    font-weight:700;
    background:#0E1220;
    border:2px solid rgba(255,255,255,0.08);
    border-radius:12px;
    color:white;
    outline:none;
    transition:border-color 0.2s, background 0.2s;
}
.otp-inp:focus{border-color:#6366f1;background:#111827;}
.otp-row{
    display:flex;
    gap:10px;
    justify-content:center;
    margin-bottom:24px;
    flex-wrap:nowrap;
}
.btn{
    width:100%;
    padding:13px;
    background:#4F46E5;
    color:white;
    font-weight:700;
    font-size:15px;
    border:none;
    border-radius:11px;
    cursor:pointer;
    transition:all 0.2s;
    box-shadow:0 6px 20px rgba(79,70,229,0.35);
}
.btn:hover{background:#4338CA;transform:translateY(-1px);}
.card{
    background:#0E1220;
    border:1px solid rgba(255,255,255,0.07);
    border-radius:20px;
    padding:28px 24px;
}
@media(max-width:480px){
    .otp-inp{width:44px;height:50px;font-size:18px;}
    .otp-row{gap:7px;}
}
</style>
</head>
<body>
<div style="position:fixed;inset:0;background:radial-gradient(ellipse at 50% 0%,rgba(79,70,229,0.1),transparent 60%);pointer-events:none;z-index:0;"></div>

<div class="page-wrap" style="position:relative;z-index:1;">

    <!-- Logo & Header -->
    <div style="text-align:center;margin-bottom:28px;">
        <div style="width:52px;height:52px;background:linear-gradient(135deg,#4F46E5,#7C3AED);border-radius:15px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;box-shadow:0 8px 24px rgba(79,70,229,0.35);">
            <svg width="24" height="24" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h1 style="font-size:24px;font-weight:800;color:white;margin-bottom:6px;">Verify your email</h1>
        <p style="font-size:13.5px;color:#6B7280;margin-bottom:4px;">We sent a 6-digit code to</p>
        <p style="font-size:14px;font-weight:600;color:#a5b4fc;"><?php echo htmlspecialchars($user['email']); ?></p>
    </div>

    <!-- Card -->
    <div class="card">

        <?php if(!empty($errors)): ?>
        <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:11px;padding:12px 14px;margin-bottom:18px;">
            <?php foreach($errors as $e): ?><p style="font-size:13px;color:#F87171;margin-bottom:2px;"><?php echo $e; ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if(!empty($success)): ?>
        <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);border-radius:11px;padding:12px 14px;margin-bottom:18px;">
            <p style="font-size:13px;color:#34D399;margin:0;">✅ <?php echo htmlspecialchars($success); ?></p>
        </div>
        <?php endif; ?>

        <form method="POST">
            <p style="font-size:13px;color:#9CA3AF;margin-bottom:20px;text-align:center;">Enter the 6-digit verification code</p>

            <div class="otp-row">
                <input type="text" class="otp-inp" id="o1" maxlength="1" inputmode="numeric" pattern="[0-9]" autofocus>
                <input type="text" class="otp-inp" id="o2" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-inp" id="o3" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-inp" id="o4" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-inp" id="o5" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-inp" id="o6" maxlength="1" inputmode="numeric" pattern="[0-9]">
            </div>
            <input type="hidden" name="otp" id="otpHidden">

            <button type="submit" class="btn" onclick="combineOTP()">Verify Email →</button>
        </form>

        <div style="text-align:center;margin-top:20px;">
            <p style="font-size:13px;color:#6B7280;">Code expires in <span id="timer" style="color:#a5b4fc;font-weight:600;">10:00</span></p>
            <p style="font-size:13px;color:#6B7280;margin-top:8px;">
                Didn't receive? <a href="verify_otp.php?resend=1" style="color:#818cf8;font-weight:600;text-decoration:none;">Resend OTP</a>
            </p>
        </div>
    </div>

    <!-- Step indicator -->
    <div style="margin-top:24px;display:flex;align-items:center;justify-content:center;">
        <?php foreach([['Register',false],['Verify',true],['Select Plan',false],['Dashboard',false]] as $i=>[$s,$a]): ?>
        <div style="display:flex;align-items:center;">
            <div style="display:flex;flex-direction:column;align-items:center;gap:5px;">
                <div style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;background:<?php echo $a?'#4F46E5':'rgba(255,255,255,0.07)';?>;color:<?php echo $a?'white':'#4B5563';?>;border:<?php echo $a?'2px solid #4F46E5':'1px solid rgba(255,255,255,0.08)';?>;"><?php echo $i+1;?></div>
                <span style="font-size:10.5px;color:<?php echo $a?'#a5b4fc':'#4B5563';?>;font-weight:<?php echo $a?'600':'400';?>;"><?php echo $s;?></span>
            </div>
            <?php if($i<3): ?><div style="width:28px;height:1px;background:rgba(255,255,255,0.06);margin:0 4px;margin-bottom:18px;"></div><?php endif;?>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<script>
const inputs = ['o1','o2','o3','o4','o5','o6'].map(id => document.getElementById(id));
inputs.forEach((inp, i) => {
    inp.addEventListener('input', () => { if(inp.value && i < 5) inputs[i+1].focus(); });
    inp.addEventListener('keydown', (e) => { if(e.key==='Backspace' && !inp.value && i > 0) inputs[i-1].focus(); });
});
inputs[0].addEventListener('paste', (e) => {
    e.preventDefault();
    const paste = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
    paste.split('').forEach((c,i) => { if(inputs[i]) inputs[i].value = c; });
    if(paste.length >= 6) inputs[5].focus();
});
function combineOTP() {
    document.getElementById('otpHidden').value = inputs.map(i => i.value).join('');
}
let sec = 600;
const timerEl = document.getElementById('timer');
const iv = setInterval(() => {
    sec--;
    if(sec <= 0){ clearInterval(iv); timerEl.textContent='Expired'; timerEl.style.color='#EF4444'; return; }
    timerEl.textContent = Math.floor(sec/60).toString().padStart(2,'0')+':'+(sec%60).toString().padStart(2,'0');
}, 1000);
</script>
</body>
</html>
