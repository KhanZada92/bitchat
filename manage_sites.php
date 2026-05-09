<?php
/**
 * manage_sites.php
 * AJAX endpoint for creating, listing, updating, deleting user sites.
 */
require_once 'config/main_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']); exit();
}

$user_id = $_SESSION['user_id'];
$plan    = $_SESSION['plan'] ?? 'basic';

$plan_limits = ['basic' => 1, 'starter' => 5, 'pro' => 10];
$max_sites   = $plan_limits[$plan] ?? 1;

$body   = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = $_POST;
$action = $body['action'] ?? '';

function drive_token_manage(): string {
    $sa = [
        "private_key"  => "-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDT0/k4+ZwEbqrx\nhBTZJ2cwTDDeyowxo/5Acud25OEHQVcTYKJoHY+dXo8ToO6hTxBn4aiPgq7C6TbH\nYrSvxbEivoSSl5TQLQ4jVvAeEkdn+JABY48qh1s2f8Bs2mhe0oRDytDq7d0atkf9\nZhbUeEgxmsY3FIP1RRX4OHH/he7wGvxVVeU34uDaRTVXV1TZaX+6jDJDNYBhl2jF\ndhOcQk6R9fzrNMyOAEUvQZTim7nyoydYl/XEPazixpeaXC2tgYrV8A9t6ES3qwNC\ntVjpmQpqWjeBqCt1yWTjPqjq3uxMq/qhVinWCaSM3QRhAhrmgPSGpdkR6rswqvlh\nhSeVPwl5AgMBAAECggEAFoYFC35bDQuZaJnTP63VZojLb30ZABRAbijpnLZEiSl0\n8U4Gpsxx31lVHFCx3vwRsgIIEs5h976lgPjpXoFGTvv0C8RLYgFQbgiJ+qCV8CO5\naDRmXi7LT5Ww58Inc+Gth6nSohBNwM/eAr8usUPi5UdgSS4Iw/UnG2AhGvrqR6Rd\nXm3cS6k6lbhhglQ+k8vIbixEZOXOO8O6x6TNES6R8NZzSIp0BUS6M38oPI8rF9xJ\nITQwsVwVBuOVl6Abegr2Q5LksyZK2UPxFspBAAo781mlOSOM3JvmBnUBlAtoUejK\nEs44G0yKzHelcUVIzymBh2nK/EZs4WmGlfmaaKyCWwKBgQDx5tLDq1ZTWlmvXTJ2\nMrx61lgfJqZ0aav+uqRwFgQaXLeOfQ1B9DI9z/4KTAWEyZ9X3nD65LwwhM8Wd1GA\nu/Txuyy0mfcKwEPqMimBNNrg2eZzR58I55RHk3Z1TGNx4ZW8h8hLhOZfoqG6Rski\nRZZwnMwfr5w+ubiDnUyAjYtynwKBgQDgLHN9xpxNw2gbiwZAyrOMyEIDh3SSt1sq\nb+8OoYiJRno3Sexf55E/vIZ3ll6sqe22stf7PRUii/m6SqL2x2VjgRsb3AsEBDHf\nKLTZOEUm4R9hJ9dIf6slDM9PE/LXQj/+mMysUUwwuk9/e+Wvonc/KeOg4Mtiqb01\nzstm0ovk5wKBgACxpNEi4LCEhdVW8xobsya3DrGoLroOw4uLhYU8yu44bd5exXb2\n+F3tBtGIvktPOMHLxY8ysMeC2gU6emVgJKe83bf26RqCyq8VTcEtIaObfGnAPtiL\nsYUzCxfzDCX7e656xTxSOUb09HnQUiti/7d6+6rrmgskBT97aAjGXywJAoGBALp8\nYAs2yNpr/1RCYA5QUfOAuGHlMlXHEKEKAu3B9Sp1pcAO0AOsSQmjlJ0xS0sKBcWh\nm8jWNJnLphCSfGUc1Txkr5+KeuN5dd92JpQ5mlVQm+Ef2pjmFAK7WE4pgzANXd03\nUbTb4Kz8oJul/xoP5nF2MHWp66gFGnEDufOsnVHrAoGBAL5t7fYojzPKVOyEhUoT\nJTOjbL9vyxj4amtDnD6/x1Fz6bxWy+gKOv75iibyejsCCkQR4uB/qsUm7th2bFpC\no5RHG6TpWCi9YDjYrJeKrC6iIM7R6vXsuDtvTKrogllOsi7grRDe12fU1Nmfhz9+\ntvpxWovyTapAtcBnDBTjMQJI\n-----END PRIVATE KEY-----\n",
        "client_email" => "n8n-855@bitchat-492118.iam.gserviceaccount.com",
        "token_uri"    => "https://oauth2.googleapis.com/token",
    ];
    if (!function_exists('openssl_sign')) return '';
    $now = time();
    $header  = rtrim(strtr(base64_encode(json_encode(['alg'=>'RS256','typ'=>'JWT'])),'+/','-_'),'=');
    $payload = rtrim(strtr(base64_encode(json_encode([
        'iss'=>$sa['client_email'],'scope'=>'https://www.googleapis.com/auth/drive.file',
        'aud'=>$sa['token_uri'],'iat'=>$now,'exp'=>$now+3600
    ])),'+/','-_'),'=');
    $sig = '';
    if (!openssl_sign("$header.$payload", $sig, $sa['private_key'], 'SHA256')) return '';
    $jwt = "$header.$payload.".rtrim(strtr(base64_encode($sig),'+/','-_'),'=');
    $ch = curl_init($sa['token_uri']);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,
        CURLOPT_POSTFIELDS=>http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt])]);
    $res = json_decode(curl_exec($ch), true); curl_close($ch);
    return (string)($res['access_token'] ?? '');
}

function drive_delete_file_manage(string $token, string $file_id): bool {
    if ($token === '' || $file_id === '') return false;
    $ch = curl_init('https://www.googleapis.com/drive/v3/files/' . rawurlencode($file_id) . '?supportsAllDrives=true');
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
    ]);
    curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return in_array($http, [200, 204, 404], true);
}

// ── LIST ──
if ($action === 'list') {
    $stmt = $conn->prepare("SELECT site_id, site_name, website_url, has_data, qa_count, created_at FROM sites WHERE user_id=? ORDER BY created_at ASC");
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $sites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    echo json_encode(['success' => true, 'sites' => $sites, 'max' => $max_sites]);
    exit();
}

// ── CREATE ──
if ($action === 'create') {
    $name = trim(substr($body['site_name'] ?? '', 0, 100));
    $url  = trim($body['website_url'] ?? '');
    if (empty($name)) { echo json_encode(['error' => 'Site name required']); exit(); }

    $count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM sites WHERE user_id=?");
    $count_stmt->bind_param("i", $user_id); $count_stmt->execute();
    $count = $count_stmt->get_result()->fetch_assoc()['c']; $count_stmt->close();

    if ($count >= $max_sites) {
        echo json_encode(['error' => "Your " . strtoupper($plan) . " plan allows max {$max_sites} site(s). Upgrade to add more."]);
        exit();
    }

    $username = preg_replace('/[^a-z0-9]/', '', strtolower($_SESSION['username'] ?? 'user'));
    $site_id  = $username . '_' . substr(md5(uniqid($user_id, true)), 0, 6);

    $stmt = $conn->prepare("INSERT INTO sites (user_id, site_id, site_name, website_url) VALUES (?,?,?,?)");
    $stmt->bind_param("isss", $user_id, $site_id, $name, $url);
    $stmt->execute(); $stmt->close();

    echo json_encode(['success' => true, 'site_id' => $site_id, 'site_name' => $name, 'website_url' => $url]);
    exit();
}

// ── UPDATE ──
if ($action === 'update') {
    $site_id = trim($body['site_id'] ?? '');
    $name    = trim(substr($body['site_name'] ?? '', 0, 100));
    $url     = trim($body['website_url'] ?? '');

    $upd = $conn->prepare("UPDATE sites SET site_name=?, website_url=? WHERE site_id=? AND user_id=?");
    $upd->bind_param("sssi", $name, $url, $site_id, $user_id);
    $upd->execute(); $upd->close();
    echo json_encode(['success' => true]);
    exit();
}

// ── DELETE ──
if ($action === 'delete') {
    $site_id = trim($body['site_id'] ?? '');

    $chk = $conn->prepare("SELECT id FROM sites WHERE site_id=? AND user_id=?");
    $chk->bind_param("si", $site_id, $user_id); $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        echo json_encode(['error' => 'Site not found']); exit();
    }
    $chk->close();

    // Don't delete if it's the only site
    $cnt = $conn->prepare("SELECT COUNT(*) as c FROM sites WHERE user_id=?");
    $cnt->bind_param("i", $user_id); $cnt->execute();
    $c = $cnt->get_result()->fetch_assoc()['c']; $cnt->close();
    if ($c <= 1) { echo json_encode(['error' => 'Cannot delete your only site']); exit(); }

    $warnings = [];

    // Collect Drive file IDs for cleanup.
    $drive_ids = [];
    $df = $conn->prepare("SELECT drive_file_id FROM uploads WHERE user_id=? AND site_id=? AND drive_file_id IS NOT NULL AND drive_file_id<>''");
    if ($df) {
        $df->bind_param("is", $user_id, $site_id);
        $df->execute();
        $rows = $df->get_result()->fetch_all(MYSQLI_ASSOC);
        $df->close();
        foreach ($rows as $r) $drive_ids[] = (string)$r['drive_file_id'];
    }
    $ds = $conn->prepare("SELECT drive_file_id FROM sites WHERE user_id=? AND site_id=? LIMIT 1");
    if ($ds) {
        $ds->bind_param("is", $user_id, $site_id);
        $ds->execute();
        $r = $ds->get_result()->fetch_assoc();
        $ds->close();
        if (!empty($r['drive_file_id'])) $drive_ids[] = (string)$r['drive_file_id'];
    }
    $drive_ids = array_values(array_unique(array_filter($drive_ids)));

    $conn->begin_transaction();
    try {
        $d1 = $conn->prepare("DELETE FROM chatbot_settings WHERE user_id=? AND site_id=?");
        if ($d1) { $d1->bind_param("is", $user_id, $site_id); $d1->execute(); $d1->close(); }

        $d2 = $conn->prepare("DELETE FROM chat_history WHERE site_id=?");
        if ($d2) { $d2->bind_param("s", $site_id); $d2->execute(); $d2->close(); }

        $d3 = $conn->prepare("DELETE FROM uploads WHERE user_id=? AND site_id=?");
        if ($d3) { $d3->bind_param("is", $user_id, $site_id); $d3->execute(); $d3->close(); }

        $del = $conn->prepare("DELETE FROM sites WHERE site_id=? AND user_id=?");
        $del->bind_param("si", $site_id, $user_id); $del->execute(); $del->close();

        // Keep users.site_id pointing to an existing site if needed.
        $fix = $conn->prepare("SELECT site_id FROM sites WHERE user_id=? ORDER BY created_at ASC LIMIT 1");
        if ($fix) {
            $fix->bind_param("i", $user_id);
            $fix->execute();
            $next = $fix->get_result()->fetch_assoc()['site_id'] ?? '';
            $fix->close();
            $u = $conn->prepare("UPDATE users SET site_id=? WHERE id=?");
            if ($u) { $u->bind_param("si", $next, $user_id); $u->execute(); $u->close(); }
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        echo json_encode(['error' => 'Delete failed: ' . $e->getMessage()]);
        exit();
    }

    // External cleanup (best-effort): Drive + Qdrant
    if (function_exists('curl_init')) {
        $tok = drive_token_manage();
        if ($tok === '') {
            if (!empty($drive_ids)) $warnings[] = 'Could not get Drive token for file cleanup';
        } else {
            foreach ($drive_ids as $fid) {
                if (!drive_delete_file_manage($tok, $fid)) {
                    $warnings[] = 'Drive delete failed for file ' . $fid;
                }
            }
        }

        $qdrant_base = 'http://n8n-n8n-7y0vkt-qdrant-1:6333';
        $collection  = 'chatbot_' . $site_id;
        $ch = curl_init("$qdrant_base/collections/$collection");
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST=>'DELETE', CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
        curl_exec($ch);
        $qhttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!in_array($qhttp, [200, 202, 204, 404, 0], true)) {
            $warnings[] = 'Qdrant cleanup response code: ' . $qhttp;
        }
    } else {
        $warnings[] = 'cURL not available for Drive/Qdrant cleanup';
    }

    echo json_encode(['success' => true, 'warnings' => $warnings]);
    exit();
}

echo json_encode(['error' => 'Unknown action']);
