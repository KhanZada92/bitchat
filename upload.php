<?php
require_once 'config/main_config.php';
require_once __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json');

// ── Auth ──────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']); exit();
}

$user_id      = $_SESSION['user_id'];
$upload_limit = $_SESSION['upload_limit_mb'] ?? 5;

// ── 1. File validation ────────────────────────────────
if (!isset($_FILES['qa_file']) || $_FILES['qa_file']['error'] !== UPLOAD_ERR_OK) {
    $errs = [1=>'File too large (server)',2=>'File too large (form)',3=>'Partial upload',
             4=>'No file',6=>'No temp dir',7=>'Write failed',8=>'Extension blocked'];
    echo json_encode(['success'=>false,'error'=>$errs[$_FILES['qa_file']['error']??-1]??'Upload error']); exit();
}

$file      = $_FILES['qa_file'];
$orig_name = $file['name'];
$tmp_path  = $file['tmp_name'];
$file_size = $file['size'];
$ext       = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

if (!in_array($ext, ['docx','pdf','json'])) {
    echo json_encode(['success'=>false,'error'=>'Only DOCX, PDF, JSON allowed']); exit();
}
if ($file_size > ($upload_limit * 1024 * 1024)) {
    echo json_encode(['success'=>false,'error'=>"Max {$upload_limit}MB allowed"]); exit();
}

// ── 2. site_id — SESSION ya USERNAME se lo, FILE NAME se NAHI ──
// Yeh ensure karta hai dashboard aur Qdrant collection name MATCH kare
// Dashboard mein: site_id = ahsan_qa
// Qdrant mein:    collection = chatbot_ahsan_qa  ✓
$site_id = $_SESSION['site_id'] ?? '';

if (empty($site_id)) {
    $username = $_SESSION['username'] ?? '';
    if (!empty($username)) {
        $site_id = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $username)));
    }
}

if (empty($site_id)) {
    $site_id = 'user_' . $user_id;
}

error_log("[upload] site_id: $site_id | user_id: $user_id");

// ── 3. DOCX extractor ────────────────────────────────
function extract_docx(string $path): array {
    try {
        $phpWord  = \PhpOffice\PhpWord\IOFactory::load($path);
        $pairs    = [];
        $currentQ = null;
        $currentA = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $el) {
                $text = '';
                if (method_exists($el, 'getText')) {
                    $text = trim($el->getText());
                } elseif (method_exists($el, 'getElements')) {
                    foreach ($el->getElements() as $child) {
                        if (method_exists($child, 'getText')) $text .= $child->getText();
                        elseif (method_exists($child, 'getElements')) {
                            foreach ($child->getElements() as $c2) {
                                if (method_exists($c2, 'getText')) $text .= $c2->getText();
                            }
                        }
                    }
                    $text = trim($text);
                }
                if (empty($text)) continue;

                $isHeading = false;
                if ($el instanceof \PhpOffice\PhpWord\Element\Title) {
                    $isHeading = true;
                } elseif (method_exists($el, 'getParagraphStyle')) {
                    $style = $el->getParagraphStyle();
                    if ($style instanceof \PhpOffice\PhpWord\Style\Paragraph) {
                        if (strpos(strtolower($style->getStyleName() ?? ''), 'heading') !== false) $isHeading = true;
                    } elseif (is_string($style)) {
                        if (strpos(strtolower($style), 'heading') !== false) $isHeading = true;
                    }
                }
                if (!$isHeading && preg_match('/^(\d+[\.\)]\s+.{5,}|Q\d*\s*[:\.]\s*.{5,})/iu', $text)) {
                    $isHeading = true;
                }

                if ($isHeading) {
                    if ($currentQ !== null && !empty($currentA)) {
                        $pairs[] = ['question'=>$currentQ, 'answer'=>trim(implode(' ', $currentA))];
                    }
                    $currentQ = trim(preg_replace('/^(\d+[\.\)]\s+|Q\d*\s*[:\.]\s*)/iu', '', $text));
                    $currentA = [];
                } else {
                    if ($currentQ !== null) $currentA[] = $text;
                }
            }
        }
        if ($currentQ !== null && !empty($currentA)) {
            $pairs[] = ['question'=>$currentQ, 'answer'=>trim(implode(' ', $currentA))];
        }
        error_log("[upload] DOCX pairs: " . count($pairs));

        if (empty($pairs)) {
            $rawText = '';
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $el) {
                    if (method_exists($el, 'getElements')) {
                        foreach ($el->getElements() as $child) {
                            if (method_exists($child, 'getText')) $rawText .= $child->getText()."\n";
                            elseif (method_exists($child, 'getElements')) {
                                foreach ($child->getElements() as $c2) {
                                    if (method_exists($c2,'getText')) $rawText .= $c2->getText();
                                }
                                $rawText .= "\n";
                            }
                        }
                    } elseif (method_exists($el,'getText')) {
                        $rawText .= $el->getText()."\n";
                    }
                }
            }
            $pairs = parse_qa_strict($rawText);
        }
        return $pairs;
    } catch (\Exception $e) {
        error_log("[upload] DOCX error: " . $e->getMessage());
        return [];
    }
}

// ── 4. Q:/A: parser ──────────────────────────────────
function parse_qa_strict(string $text): array {
    $pairs = []; $q = ''; $a = '';
    foreach (array_filter(array_map('trim', explode("\n", $text))) as $line) {
        if (preg_match('/^Q\d*\s*[:।\-\.]\s*(.+)/iu', $line, $m)) {
            if ($q && $a) $pairs[] = ['question'=>trim($q),'answer'=>trim($a)];
            $q = trim($m[1]); $a = '';
        } elseif (preg_match('/^A\d*\s*[:।\-\.]\s*(.+)/iu', $line, $m)) {
            $a = trim($m[1]);
        } elseif ($q && $a) {
            $a .= ' '.$line;
        }
    }
    if ($q && $a) $pairs[] = ['question'=>trim($q),'answer'=>trim($a)];
    return $pairs;
}

// ── 5. PDF extractor ─────────────────────────────────
function extract_pdf(string $path): array {
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $text   = $parser->parseFile($path)->getText();
        $pairs  = parse_qa_strict($text);
        if (empty($pairs)) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));
            $q = null; $a = []; $out = [];
            foreach ($lines as $line) {
                if (strlen($line) < 120 && (substr($line,-1)==='?' || preg_match('/^\d+[\.\)]/u',$line))) {
                    if ($q && $a) $out[] = ['question'=>$q,'answer'=>trim(implode(' ',$a))];
                    $q = preg_replace('/^\d+[\.\)]\s*/u','',$line); $a = [];
                } elseif ($q) {
                    $a[] = $line;
                }
            }
            if ($q && $a) $out[] = ['question'=>$q,'answer'=>trim(implode(' ',$a))];
            $pairs = $out;
        }
        return $pairs;
    } catch (\Exception $e) {
        error_log("[upload] PDF error: " . $e->getMessage());
        return [];
    }
}

// ── 6. JSON extractor ────────────────────────────────
function extract_json(string $path): array {
    $data = json_decode(file_get_contents($path), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) return [];
    $pairs = [];
    foreach ($data as $item) {
        if (!empty($item['question']) && !empty($item['answer'])) {
            $pairs[] = ['question'=>trim($item['question']),'answer'=>trim($item['answer'])];
        }
    }
    return $pairs;
}

// ── 7. Run extractor ─────────────────────────────────
switch ($ext) {
    case 'docx': $qa_pairs = extract_docx($tmp_path); break;
    case 'pdf':  $qa_pairs = extract_pdf($tmp_path);  break;
    case 'json': $qa_pairs = extract_json($tmp_path); break;
    default:     $qa_pairs = [];
}

error_log("[upload] Total Q&A pairs: " . count($qa_pairs));

if (empty($qa_pairs)) {
    echo json_encode(['success'=>false,'error'=>'No Q&A pairs found. Check file format.']); exit();
}

// ── 8. Write temp JSON ────────────────────────────────
$json_filename = $site_id . '.json';  // e.g. ahsan_qa.json
$json_tmp      = sys_get_temp_dir() . '/' . $json_filename;
file_put_contents($json_tmp, json_encode($qa_pairs, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

// ── 9. Google Drive Auth ──────────────────────────────
$sa = [
    "type"           => "service_account",
    "project_id"     => "bitchat-492118",
    "private_key_id" => "9b488b2bc9f9454934447597673baba237a5416e",
    "private_key"    => "-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDT0/k4+ZwEbqrx\nhBTZJ2cwTDDeyowxo/5Acud25OEHQVcTYKJoHY+dXo8ToO6hTxBn4aiPgq7C6TbH\nYrSvxbEivoSSl5TQLQ4jVvAeEkdn+JABY48qh1s2f8Bs2mhe0oRDytDq7d0atkf9\nZhbUeEgxmsY3FIP1RRX4OHH/he7wGvxVVeU34uDaRTVXV1TZaX+6jDJDNYBhl2jF\ndhOcQk6R9fzrNMyOAEUvQZTim7nyoydYl/XEPazixpeaXC2tgYrV8A9t6ES3qwNC\ntVjpmQpqWjeBqCt1yWTjPqjq3uxMq/qhVinWCaSM3QRhAhrmgPSGpdkR6rswqvlh\nhSeVPwl5AgMBAAECggEAFoYFC35bDQuZaJnTP63VZojLb30ZABRAbijpnLZEiSl0\n8U4Gpsxx31lVHFCx3vwRsgIIEs5h976lgPjpXoFGTvv0C8RLYgFQbgiJ+qCV8CO5\naDRmXi7LT5Ww58Inc+Gth6nSohBNwM/eAr8usUPi5UdgSS4Iw/UnG2AhGvrqR6Rd\nXm3cS6k6lbhhglQ+k8vIbixEZOXOO8O6x6TNES6R8NZzSIp0BUS6M38oPI8rF9xJ\nITQwsVwVBuOVl6Abegr2Q5LksyZK2UPxFspBAAo781mlOSOM3JvmBnUBlAtoUejK\nEs44G0yKzHelcUVIzymBh2nK/EZs4WmGlfmaaKyCWwKBgQDx5tLDq1ZTWlmvXTJ2\nMrx61lgfJqZ0aav+uqRwFgQaXLeOfQ1B9DI9z/4KTAWEyZ9X3nD65LwwhM8Wd1GA\nu/Txuyy0mfcKwEPqMimBNNrg2eZzR58I55RHk3Z1TGNx4ZW8h8hLhOZfoqG6Rski\nRZZwnMwfr5w+ubiDnUyAjYtynwKBgQDgLHN9xpxNw2gbiwZAyrOMyEIDh3SSt1sq\nb+8OoYiJRno3Sexf55E/vIZ3ll6sqe22stf7PRUii/m6SqL2x2VjgRsb3AsEBDHf\nKLTZOEUm4R9hJ9dIf6slDM9PE/LXQj/+mMysUUwwuk9/e+Wvonc/KeOg4Mtiqb01\nzstm0ovk5wKBgACxpNEi4LCEhdVW8xobsya3DrGoLroOw4uLhYU8yu44bd5exXb2\n+F3tBtGIvktPOMHLxY8ysMeC2gU6emVgJKe83bf26RqCyq8VTcEtIaObfGnAPtiL\nsYUzCxfzDCX7e656xTxSOUb09HnQUiti/7d6+6rrmgskBT97aAjGXywJAoGBALp8\nYAs2yNpr/1RCYA5QUfOAuGHlMlXHEKEKAu3B9Sp1pcAO0AOsSQmjlJ0xS0sKBcWh\nm8jWNJnLphCSfGUc1Txkr5+KeuN5dd92JpQ5mlVQm+Ef2pjmFAK7WE4pgzANXd03\nUbTb4Kz8oJul/xoP5nF2MHWp66gFGnEDufOsnVHrAoGBAL5t7fYojzPKVOyEhUoT\nJTOjbL9vyxj4amtDnD6/x1Fz6bxWy+gKOv75iibyejsCCkQR4uB/qsUm7th2bFpC\no5RHG6TpWCi9YDjYrJeKrC6iIM7R6vXsuDtvTKrogllOsi7grRDe12fU1Nmfhz9+\ntvpxWovyTapAtcBnDBTjMQJI\n-----END PRIVATE KEY-----\n",
    "client_email"   => "n8n-855@bitchat-492118.iam.gserviceaccount.com",
    "client_id"      => "112393546510052635931",
    "token_uri"      => "https://oauth2.googleapis.com/token",
];

function get_drive_token(array $sa): string {
    $now     = time();
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
    return $res['access_token'] ?? '';
}

$token = get_drive_token($sa);
if (!$token) { echo json_encode(['success'=>false,'error'=>'Drive auth failed']); exit(); }

// ── 10. Upload to Shared Drive ────────────────────────
$drive_folder_id = '0AB2nBzt58cUxUk9PVA';
$file_content    = file_get_contents($json_tmp);
$metadata        = json_encode(['name'=>$json_filename,'parents'=>[$drive_folder_id]]);
$boundary        = '----WLP'.uniqid();
$body = "--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$metadata\r\n"
      . "--$boundary\r\nContent-Type: application/json\r\n\r\n$file_content\r\n--$boundary--";

$ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,webViewLink,name');
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,
    CURLOPT_POSTFIELDS=>$body,
    CURLOPT_HTTPHEADER=>["Authorization: Bearer $token","Content-Type: multipart/related; boundary=$boundary","Content-Length: ".strlen($body)]]);
$raw      = curl_exec($ch);
$curl_err = curl_error($ch);
$http_code= curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
@unlink($json_tmp);

if ($curl_err || $http_code !== 200) {
    $res = json_decode($raw, true);
    echo json_encode(['success'=>false,'error'=>'Drive upload failed: '.($res['error']['message']??$curl_err)]); exit();
}
$res = json_decode($raw, true);

// ── 11. Save to DB ────────────────────────────────────
$file_size_kb = round($file_size / 1024);
$qa_count     = count($qa_pairs);
$drive_id     = $res['id'] ?? '';
$now_dt       = date('Y-m-d H:i:s');

$ins = $conn->prepare("INSERT INTO uploads (user_id, site_id, filename, file_size_kb, qa_count, status, created_at, drive_file_id) VALUES (?, ?, ?, ?, ?, 'done', ?, ?)");
$ins->bind_param("issiiis", $user_id, $site_id, $json_filename, $file_size_kb, $qa_count, $now_dt, $drive_id);
$ins->execute();
$ins->close();

// site_id hamesha update karo (file name se kabhi nahi)
$upd = $conn->prepare("UPDATE users SET site_id=? WHERE id=?");
$upd->bind_param("si", $site_id, $user_id);
$upd->execute();
$upd->close();
$_SESSION['site_id'] = $site_id;

// ── 12. Push to Qdrant ───────────────────────────────
$qdrant_base = 'http://n8n-n8n-7y0vkt-qdrant-1:6333';
$collection  = 'chatbot_' . $site_id;  // e.g. chatbot_ahsan_qa ✓

error_log("[upload] Qdrant collection: $collection");

$ch = curl_init("$qdrant_base/collections/$collection");
curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>'DELETE',CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);
curl_exec($ch); curl_close($ch);

$create_body = json_encode(['vectors'=>['size'=>768,'distance'=>'Cosine']]);
$ch = curl_init("$qdrant_base/collections/$collection");
curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>'PUT',CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,
    CURLOPT_POSTFIELDS=>$create_body,CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
$cr = curl_exec($ch); $cr_code = curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
error_log("[upload] Qdrant create HTTP $cr_code");

$ollama_base = 'http://n8n-n8n-7y0vkt-ollama-1:11434';
$points      = [];

foreach ($qa_pairs as $idx => $pair) {
    $embed_body = json_encode(['model'=>'nomic-embed-text:v1.5','input'=>$pair['question']]);
    $ch = curl_init("$ollama_base/api/embed");
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,
        CURLOPT_POSTFIELDS=>$embed_body,CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
    $emb_raw  = curl_exec($ch); curl_close($ch);
    $emb_data = json_decode($emb_raw, true);
    $vector   = $emb_data['embeddings'][0] ?? $emb_data['embedding'] ?? null;

    if (!$vector || count($vector) !== 768) {
        error_log("[upload] Embedding failed idx $idx");
        continue;
    }

    $points[] = [
        'id'      => $idx + 1,
        'vector'  => $vector,
        'payload' => ['question'=>$pair['question'],'answer'=>$pair['answer'],
                      'text'=>$pair['question'].' '.$pair['answer'],'site_id'=>$site_id]
    ];
}

if (!empty($points)) {
    $upsert_body = json_encode(['points'=>$points]);
    $ch = curl_init("$qdrant_base/collections/$collection/points");
    curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>'PUT',CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,
        CURLOPT_POSTFIELDS=>$upsert_body,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Content-Length: '.strlen($upsert_body)]]);
    $upsert_raw  = curl_exec($ch);
    $upsert_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    error_log("[upload] Qdrant upsert HTTP $upsert_code | points: ".count($points));
}

// ── 13. Return ────────────────────────────────────────
echo json_encode([
    'success'        => true,
    'site_id'        => $site_id,
    'qa_count'       => $qa_count,
    'vectors_stored' => count($points),
    'drive_file_id'  => $drive_id,
    'drive_link'     => $res['webViewLink'] ?? null,
    'filename'       => $json_filename,
]);