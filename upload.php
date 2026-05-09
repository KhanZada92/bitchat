<?php
require_once 'config/main_config.php';

$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

if (!function_exists('curl_init')) {
    echo json_encode(['success' => false, 'error' => 'Server missing cURL extension.']); exit();
}

// Always return JSON even on fatal runtime errors (prevents "Invalid server response").
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()]);
    exit();
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        http_response_code(500);
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Upload failed: ' . ($err['message'] ?? 'Server error')]);
    }
});

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

// ── 2. site_id from POST — which site is active ──
$site_id = trim($_POST['site_ref'] ?? '');

if (empty($site_id)) {
    // fallback: try session site_id for backward compat
    $site_id = $_SESSION['site_id'] ?? '';
}

if (empty($site_id)) {
    echo json_encode(['success'=>false,'error'=>'No site selected. Please select a site first.']); exit();
}

// Verify this site belongs to the user
$chk = $conn->prepare("SELECT id FROM sites WHERE site_id=? AND user_id=?");
$chk->bind_param("si", $site_id, $user_id); $chk->execute();
if (!$chk->get_result()->fetch_assoc()) {
    // Fallback: check if it's in users.site_id (backward compat for old accounts)
    $chk2 = $conn->prepare("SELECT id FROM users WHERE site_id=? AND id=?");
    $chk2->bind_param("si", $site_id, $user_id); $chk2->execute();
    if (!$chk2->get_result()->fetch_assoc()) {
        echo json_encode(['success'=>false,'error'=>'Invalid site selected']); exit();
    }
    $chk2->close();
}
$chk->close();

error_log("[upload] site_id: $site_id | user_id: $user_id");

// ── 3. DOCX extractor ────────────────────────────────
function extract_docx_text_zip(string $path): string {
    if (!class_exists('ZipArchive')) return '';
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return '';
    $parts = [];
    foreach (['word/document.xml', 'word/header1.xml', 'word/header2.xml', 'word/footer1.xml', 'word/footer2.xml'] as $entry) {
        $xml = $zip->getFromName($entry);
        if ($xml !== false && $xml !== '') $parts[] = $xml;
    }
    $zip->close();
    if (empty($parts)) return '';
    $xml = implode("\n", $parts);
    $xml = str_replace(['</w:p>', '</w:tr>', '</w:tbl>'], ["\n", "\n", "\n"], $xml);
    $text = preg_replace('/<[^>]+>/u', ' ', $xml);
    $text = html_entity_decode((string)$text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = preg_replace('/[ \t]+/u', ' ', $text);
    $text = preg_replace('/\n{2,}/u', "\n\n", $text);
    return trim((string)$text);
}

function extract_docx(string $path): array {
    if (!class_exists('\PhpOffice\PhpWord\IOFactory')) {
        error_log("[upload] DOCX library missing: phpoffice/phpword, using zip fallback");
        $rawText = extract_docx_text_zip($path);
        if ($rawText === '') return [];
        $pairs = parse_qa_strict($rawText);
        if (empty($pairs)) $pairs = build_pairs_from_raw_text($rawText);
        return $pairs;
    }
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
            if (empty($pairs)) {
                $pairs = build_pairs_from_raw_text($rawText);
            }
        }
        return $pairs;
    } catch (\Exception $e) {
        error_log("[upload] DOCX error: " . $e->getMessage());
        return [];
    }
}

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

function parse_qa_inline_pairs(string $text): array {
    $text = clean_text_for_qa($text);
    if ($text === '') return [];
    $pairs = [];

    // Handles formats like: Q1: ... A1: ... Q2: ... A2: ...
    if (preg_match_all('/Q\d*\s*[:\.\-]\s*(.*?)\s*A\d*\s*[:\.\-]\s*(.*?)(?=Q\d*\s*[:\.\-]|$)/isu', $text, $m, PREG_SET_ORDER)) {
        foreach ($m as $row) {
            $q = trim((string)($row[1] ?? ''));
            $a = trim((string)($row[2] ?? ''));
            if ($q !== '' && $a !== '') $pairs[] = ['question' => $q, 'answer' => $a];
        }
    }
    return $pairs;
}

function clean_text_for_qa(string $text): string {
    // Normalize potentially binary/non-UTF8 text (common in raw PDF extraction).
    if (!preg_match('//u', $text)) {
        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if (is_string($converted)) $text = $converted;
    }
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    if (!is_string($text)) $text = '';
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    if (!is_string($text)) $text = '';
    return trim((string)$text);
}

function ulen(string $s): int {
    return function_exists('mb_strlen') ? (int)mb_strlen($s) : strlen($s);
}
function usub(string $s, int $start, ?int $len = null): string {
    if (function_exists('mb_substr')) {
        return $len === null ? (string)mb_substr($s, $start) : (string)mb_substr($s, $start, $len);
    }
    return $len === null ? substr($s, $start) : substr($s, $start, $len);
}
function ulower(string $s): string {
    return function_exists('mb_strtolower') ? (string)mb_strtolower($s) : strtolower($s);
}

function make_question_from_answer(string $answer, int $idx): string {
    $parts = preg_split('/(?<=[\.\!\?])\s+/u', $answer, 2);
    $firstSentence = trim((string)($parts[0] ?? ''));
    if ($firstSentence !== '' && usub($firstSentence, -1) === '?') {
        return $firstSentence;
    }
    $title = trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', $firstSentence));
    $words = preg_split('/\s+/u', $title);
    $words = array_slice(array_filter($words), 0, 10);
    $title = trim(implode(' ', $words));
    if ($title === '') $title = 'Topic ' . $idx;
    return "Explain: {$title}?";
}

function build_pairs_from_raw_text(string $raw): array {
    $raw = clean_text_for_qa($raw);
    if ($raw === '') return [];

    $pairs = [];
    $paragraphs = preg_split('/\n\s*\n/u', $raw);
    $paragraphs = array_values(array_filter(array_map('trim', $paragraphs), function ($p) {
        return ulen($p) >= 30;
    }));

    foreach ($paragraphs as $pidx => $para) {
        $sentences = preg_split('/(?<=[\.\!\?])\s+/u', $para);
        $sentences = array_values(array_filter(array_map('trim', $sentences)));
        if (empty($sentences)) $sentences = [trim($para)];

        $chunk = [];
        $chunkLen = 0;
        foreach ($sentences as $s) {
            $chunk[] = $s;
            $chunkLen += ulen($s);
            // Keep chunks reasonably small so one giant paragraph isn't one answer.
            if ($chunkLen >= 260 || count($chunk) >= 3) {
                $ans = trim(implode(' ', $chunk));
                if (ulen($ans) >= 30) {
                    $pairs[] = ['question' => make_question_from_answer($ans, count($pairs) + 1), 'answer' => $ans];
                }
                $chunk = [];
                $chunkLen = 0;
            }
        }
        if (!empty($chunk)) {
            $ans = trim(implode(' ', $chunk));
            if (ulen($ans) >= 30) {
                $pairs[] = ['question' => make_question_from_answer($ans, count($pairs) + 1), 'answer' => $ans];
            }
        }
    }

    if (count($pairs) < 2) {
        $pairs = [];
        $sentences = preg_split('/(?<=[\.\!\?])\s+/u', $raw);
        $sentences = array_values(array_filter(array_map('trim', $sentences), function ($s) {
            return ulen($s) > 5;
        }));
        $chunk = [];
        $chunkLen = 0;
        foreach ($sentences as $s) {
            $chunk[] = $s;
            $chunkLen += ulen($s);
            if ($chunkLen >= 220 || count($chunk) >= 3) {
                $ans = trim(implode(' ', $chunk));
                if (ulen($ans) >= 30) {
                    $pairs[] = ['question' => make_question_from_answer($ans, count($pairs) + 1), 'answer' => $ans];
                }
                $chunk = [];
                $chunkLen = 0;
            }
        }
        if (!empty($chunk)) {
            $ans = trim(implode(' ', $chunk));
            if (ulen($ans) >= 30) {
                $pairs[] = ['question' => make_question_from_answer($ans, count($pairs) + 1), 'answer' => $ans];
            }
        }
    }

    $out = [];
    $seen = [];
    foreach ($pairs as $pair) {
        $q = trim((string)($pair['question'] ?? ''));
        $a = trim((string)($pair['answer'] ?? ''));
        if (ulen($q) < 6 || ulen($a) < 20) continue;
        $key = ulower($q . '|' . usub($a, 0, 120));
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = ['question' => $q, 'answer' => $a];
    }
    return $out;
}

function extract_pdf(string $path): array {
    $extract_pdf_text_fallback = function (string $pdfPath): string {
        if (function_exists('shell_exec')) {
            $commands = [
                'pdftotext -enc UTF-8 -layout ' . escapeshellarg($pdfPath) . ' -',
                'pdftotext -enc UTF-8 ' . escapeshellarg($pdfPath) . ' -',
                'mutool draw -F txt -o - ' . escapeshellarg($pdfPath),
            ];
            foreach ($commands as $cmd) {
                $out = @shell_exec($cmd . ' 2>&1');
                if (is_string($out) && trim($out) !== '') {
                    return trim($out);
                }
            }
        }

        $decodePdfLiteral = function (string $v): string {
            $v = preg_replace('/\\\\([nrtbf\\\\\(\)])/u', "\n", $v);
            return stripcslashes($v);
        };
        $decodePdfHex = function (string $hex): string {
            $hex = preg_replace('/[^0-9A-Fa-f]/', '', $hex);
            if ($hex === '') return '';
            if (strlen($hex) % 2 === 1) $hex .= '0';
            $bin = @hex2bin($hex);
            if (!is_string($bin) || $bin === '') return '';
            // UTF-16BE with BOM or null bytes
            if (substr($bin, 0, 2) === "\xFE\xFF") {
                $txt = @iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bin, 2));
                if (is_string($txt) && $txt !== '') return $txt;
            }
            if (strpos($bin, "\x00") !== false) {
                $txt = @iconv('UTF-16BE', 'UTF-8//IGNORE', $bin);
                if (is_string($txt) && trim($txt) !== '') return $txt;
            }
            return $bin;
        };

        // Primitive PDF text recovery for simple and hex-encoded text PDFs.
        $bin = @file_get_contents($pdfPath);
        if (!is_string($bin) || $bin === '') return '';
        $textParts = [];
        if (preg_match_all('/stream(.*?)endstream/s', $bin, $matches)) {
            foreach ($matches[1] as $stream) {
                $s = ltrim($stream, "\r\n");
                $decoded = @gzuncompress($s);
                if (!is_string($decoded)) $decoded = @gzdecode($s);
                if (!is_string($decoded)) $decoded = @gzinflate($s);
                if (!is_string($decoded)) $decoded = $s;
                if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)\s*Tj/s', $decoded, $m1)) {
                    foreach ($m1[0] as $chunk) {
                        if (preg_match('/\((.*)\)\s*Tj/s', $chunk, $m2)) $textParts[] = $decodePdfLiteral($m2[1]);
                    }
                }
                if (preg_match_all('/<([0-9A-Fa-f\s]+)>\s*Tj/s', $decoded, $mHex)) {
                    foreach ($mHex[1] as $hexToken) {
                        $txt = $decodePdfHex($hexToken);
                        if (trim($txt) !== '') $textParts[] = $txt;
                    }
                }
                if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $m3)) {
                    foreach ($m3[1] as $arr) {
                        if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/s', $arr, $m4)) {
                            $line = '';
                            foreach ($m4[0] as $t) $line .= $decodePdfLiteral(trim($t, '()'));
                            if (trim($line) !== '') $textParts[] = $line;
                        }
                        if (preg_match_all('/<([0-9A-Fa-f\s]+)>/s', $arr, $m5)) {
                            $lineHex = '';
                            foreach ($m5[1] as $hx) $lineHex .= $decodePdfHex($hx);
                            if (trim($lineHex) !== '') $textParts[] = $lineHex;
                        }
                    }
                }
            }
        }
        if (empty($textParts)) {
            // Generic printable-text fallback for PDFs where operators are stripped/encrypted.
            if (preg_match_all('/[\x20-\x7E]{12,}/', $bin, $strs)) {
                foreach ($strs[0] as $s) {
                    $s = trim($s);
                    if (strlen($s) >= 20 && !preg_match('/^(obj|endobj|stream|endstream|xref|trailer|startxref|\/|%PDF)/i', $s)) {
                        $textParts[] = $s;
                    }
                }
            }
            if (empty($textParts) && preg_match_all('/(?:[\x00-\x7F]\x00){20,}/', $bin, $u16)) {
                foreach ($u16[0] as $chunk) {
                    $decoded = @iconv('UTF-16LE', 'UTF-8//IGNORE', $chunk);
                    if (is_string($decoded) && trim($decoded) !== '') $textParts[] = trim($decoded);
                }
            }
        }
        $txt = trim(implode("\n", $textParts));
        $txt = preg_replace('/\s{2,}/u', ' ', $txt);
        return trim((string)$txt);
    };

    try {
        $text = '';
        if (class_exists('\Smalot\PdfParser\Parser')) {
            $parser = new \Smalot\PdfParser\Parser();
            $text   = $parser->parseFile($path)->getText();
        } else {
            error_log("[upload] PDF library missing: smalot/pdfparser, using fallback");
        }
        if (!is_string($text) || trim($text) === '') {
            $text = $extract_pdf_text_fallback($path);
        }
        if (!is_string($text) || trim($text) === '') return [];

        $pairs  = parse_qa_strict($text);
        if (empty($pairs)) {
            $pairs = parse_qa_inline_pairs($text);
        }
        if (empty($pairs)) {
            $pairs = build_pairs_from_raw_text($text);
        }
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
            if (empty($pairs)) {
                $pairs = build_pairs_from_raw_text($text);
            }
        }
        return $pairs;
    } catch (\Exception $e) {
        error_log("[upload] PDF error: " . $e->getMessage());
        $fallbackText = $extract_pdf_text_fallback($path);
        if (!is_string($fallbackText) || trim($fallbackText) === '') return [];
        $pairs = parse_qa_strict($fallbackText);
        if (empty($pairs)) $pairs = parse_qa_inline_pairs($fallbackText);
        if (empty($pairs)) $pairs = build_pairs_from_raw_text($fallbackText);
        return $pairs;
    }
}

function extract_json(string $path): array {
    $raw  = file_get_contents($path);
    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) return [];

    $pairs = [];

    // ── Format 1: [{question, answer}] ──
    if (isset($data[0]) && is_array($data[0])) {
        foreach ($data as $item) {
            // Various key names people use
            $q = $item['question'] ?? $item['q'] ?? $item['Query'] ?? $item['query'] ?? $item['title'] ?? '';
            $a = $item['answer']   ?? $item['a'] ?? $item['Answer'] ?? $item['response'] ?? $item['content'] ?? $item['text'] ?? '';
            if (!empty($q) && !empty($a)) {
                $pairs[] = ['question'=>trim($q), 'answer'=>trim($a)];
            }
        }
        if (!empty($pairs)) return $pairs;
    }

    // ── Format 2: {faqs: [{question, answer}]} ──
    $container = $data['faqs'] ?? $data['data'] ?? $data['items'] ?? $data['qa'] ?? $data['questions'] ?? null;
    if (is_array($container)) {
        foreach ($container as $item) {
            $q = $item['question'] ?? $item['q'] ?? $item['title'] ?? '';
            $a = $item['answer']   ?? $item['a'] ?? $item['content'] ?? $item['text'] ?? '';
            if (!empty($q) && !empty($a)) {
                $pairs[] = ['question'=>trim($q), 'answer'=>trim($a)];
            }
        }
        if (!empty($pairs)) return $pairs;
    }

    // ── Format 3: {"Question text": "Answer text", ...} (key=value object) ──
    if (!isset($data[0])) {
        foreach ($data as $k => $v) {
            if (is_string($k) && is_string($v) && strlen($k) > 5 && strlen($v) > 5) {
                $pairs[] = ['question'=>trim($k), 'answer'=>trim($v)];
            }
        }
        if (!empty($pairs)) return $pairs;
    }

    // ── Format 4: [{title, body/description}] — blog/article style ──
    if (isset($data[0]) && is_array($data[0])) {
        foreach ($data as $item) {
            $q = $item['title'] ?? $item['heading'] ?? $item['topic'] ?? '';
            $a = $item['body']  ?? $item['description'] ?? $item['content'] ?? $item['detail'] ?? '';
            if (!empty($q) && !empty($a)) {
                $pairs[] = ['question'=>trim($q), 'answer'=>trim($a)];
            }
        }
        if (!empty($pairs)) return $pairs;
    }

    // ── Format 5: Raw text array ["Q: ... A: ..."] ──
    if (isset($data[0]) && is_string($data[0])) {
        $combined = implode("\n", $data);
        $strict = parse_qa_strict($combined);
        return !empty($strict) ? $strict : build_pairs_from_raw_text($combined);
    }

    // ── Format 6: Single string (raw text stored as JSON string) ──
    if (is_string($data)) {
        $strict = parse_qa_strict($data);
        return !empty($strict) ? $strict : build_pairs_from_raw_text($data);
    }

    if (empty($pairs)) {
        $pairs = build_pairs_from_raw_text($raw);
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

if (empty($qa_pairs)) {
    echo json_encode(['success'=>false,'error'=>'Could not extract meaningful content from file. Please upload a readable document with actual text.']); exit();
}

// ── 8. Write temp JSON ────────────────────────────────
$json_filename = $site_id . '.json';
$json_tmp      = sys_get_temp_dir() . '/' . $json_filename;
$json_payload  = json_encode($qa_pairs, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
if (!is_string($json_payload) || $json_payload === '') {
    echo json_encode(['success'=>false,'error'=>'Failed to generate JSON from extracted content']); exit();
}
if (@file_put_contents($json_tmp, $json_payload) === false) {
    echo json_encode(['success'=>false,'error'=>'Failed to prepare upload JSON file']); exit();
}

// Keep a local copy as fallback so training data is never lost if Drive fails.
$local_storage_dir = __DIR__ . '/storage/knowledge_json';
if (!is_dir($local_storage_dir)) {
    @mkdir($local_storage_dir, 0775, true);
}
$local_json_path = $local_storage_dir . '/' . $json_filename;
$local_saved = @file_put_contents($local_json_path, $json_payload) !== false;

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

$warnings = [];
$drive_id = '';
$drive_enabled = true;

$token = get_drive_token($sa);
if (!$token) {
    $drive_enabled = false;
    $warnings[] = 'Drive auth failed; saved locally instead.';
}

// ── 10. Upload to Shared Drive ────────────────────────
if ($drive_enabled) {
    $drive_folder_id = '0AB2nBzt58cUxUk9PVA';
    $file_content    = file_get_contents($json_tmp);
    $metadata        = json_encode(['name'=>$json_filename,'parents'=>[$drive_folder_id]]);
    $boundary        = '----WLP'.uniqid();
    $body_upload = "--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$metadata\r\n"
          . "--$boundary\r\nContent-Type: application/json\r\n\r\n$file_content\r\n--$boundary--";

    $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,webViewLink,name');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,
        CURLOPT_POSTFIELDS=>$body_upload,
        CURLOPT_HTTPHEADER=>["Authorization: Bearer $token","Content-Type: multipart/related; boundary=$boundary","Content-Length: ".strlen($body_upload)]]);
    $raw       = curl_exec($ch);
    $curl_err  = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curl_err || $http_code !== 200) {
        $drive_enabled = false;
        $res = json_decode((string)$raw, true);
        $drive_msg = (string)($res['error']['message'] ?? $curl_err ?: ('HTTP ' . $http_code));
        $warnings[] = 'Drive upload failed; saved locally instead. ' . $drive_msg;
    } else {
        $res = json_decode((string)$raw, true);
        $drive_id = (string)($res['id'] ?? '');
    }
}
@unlink($json_tmp);
if (!$local_saved && !$drive_enabled) {
    echo json_encode(['success'=>false,'error'=>'Could not store extracted JSON (both Drive and local save failed).']); exit();
}

// ── 11. Save to DB ────────────────────────────────────
$file_size_kb = round($file_size / 1024);
$qa_count     = count($qa_pairs);
$now_dt       = date('Y-m-d H:i:s');

// Mark previous successful uploads for this site as replaced.
$mark_old = $conn->prepare("UPDATE uploads SET status='replaced' WHERE user_id=? AND site_id=? AND status='done'");
if ($mark_old) {
    $mark_old->bind_param("is", $user_id, $site_id);
    $mark_old->execute();
    $mark_old->close();
}

$ins = $conn->prepare("INSERT INTO uploads (user_id, site_id, filename, file_size_kb, qa_count, status, created_at, drive_file_id) VALUES (?, ?, ?, ?, ?, 'done', ?, ?)");
$ins->bind_param("issiiis", $user_id, $site_id, $json_filename, $file_size_kb, $qa_count, $now_dt, $drive_id);
$ins->execute(); $ins->close();

// ── 12. Update sites table ────────────────────────────
$upd_site = $conn->prepare("UPDATE sites SET has_data=1, qa_count=?, drive_file_id=? WHERE site_id=? AND user_id=?");
$upd_site->bind_param("issi", $qa_count, $drive_id, $site_id, $user_id);
$upd_site->execute(); $upd_site->close();

// Also update legacy users.site_id if this is their primary
$upd_user = $conn->prepare("UPDATE users SET site_id=? WHERE id=? AND (site_id IS NULL OR site_id='')");
$upd_user->bind_param("si", $site_id, $user_id); $upd_user->execute(); $upd_user->close();

$_SESSION['site_id'] = $site_id;

// ── 13. Push to Qdrant ───────────────────────────────
$qdrant_base = 'http://n8n-n8n-7y0vkt-qdrant-1:6333';
$collection  = 'chatbot_' . $site_id;

error_log("[upload] Qdrant collection: $collection");

$ch = curl_init("$qdrant_base/collections/$collection");
curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>'DELETE',CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);
curl_exec($ch); curl_close($ch);

$create_body = json_encode(['vectors'=>['size'=>768,'distance'=>'Cosine']]);
$ch = curl_init("$qdrant_base/collections/$collection");
curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>'PUT',CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,
    CURLOPT_POSTFIELDS=>$create_body,CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
curl_exec($ch); curl_close($ch);

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

    if (!$vector || count($vector) !== 768) continue;

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
    curl_exec($ch); curl_close($ch);
}

echo json_encode([
    'success'        => true,
    'site_id'        => $site_id,
    'qa_count'       => $qa_count,
    'vectors_stored' => count($points),
    'drive_file_id'  => $drive_id,
    'filename'       => $json_filename,
    'storage'        => $drive_enabled ? 'drive' : 'local',
    'warnings'       => $warnings,
]);
?>