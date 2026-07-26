<?php
// ============================================================
// ai_chat.php
// รับข้อความจากหน้าเว็บ (aiInput) แล้วส่งต่อไปยัง Anthropic API
// พร้อมเปิดใช้งาน web_search tool เพื่อให้ AI ค้นข้อมูลจากอินเทอร์เน็ตได้จริง
// API key จะถูกเก็บไว้ฝั่งเซิร์ฟเวอร์เท่านั้น ไม่ถูกส่งไปให้ browser เห็น
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

function respond_error($message, $httpCode = 400) {
    http_response_code($httpCode);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_error('Method not allowed', 405);
}

if (ANTHROPIC_API_KEY === 'YOUR_ANTHROPIC_API_KEY_HERE' || empty(ANTHROPIC_API_KEY)) {
    respond_error('ยังไม่ได้ตั้งค่า ANTHROPIC_API_KEY บนเซิร์ฟเวอร์ กรุณาแก้ไขไฟล์ config.php', 500);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!$body || !isset($body['message']) || trim($body['message']) === '') {
    respond_error('กรุณาส่งข้อความ (message)');
}

$userMessage = trim($body['message']);
$historyIn = isset($body['history']) && is_array($body['history']) ? $body['history'] : [];

// จำกัดความยาวประวัติที่ส่งไป เพื่อคุมค่าใช้จ่าย/ความเร็ว (เก็บ 10 ข้อความล่าสุด)
if (count($historyIn) > 10) {
    $historyIn = array_slice($historyIn, -10);
}

// สร้าง messages array ให้ตรงรูปแบบ Anthropic API
$messages = [];
foreach ($historyIn as $turn) {
    if (!isset($turn['role'], $turn['content'])) continue;
    $role = $turn['role'] === 'assistant' ? 'assistant' : 'user';
    $messages[] = ['role' => $role, 'content' => (string) $turn['content']];
}
$messages[] = ['role' => 'user', 'content' => $userMessage];

$systemPrompt = <<<EOT
คุณคือ "Baby Gole AI" ผู้ช่วยวิเคราะห์อาการและให้ข้อมูลด้านสุขภาพ พัฒนาการ โภชนาการ และการดูแลเด็กเล็กอายุ 0-6 ปี สำหรับเว็บไซต์ baby Gole

ขอบเขตการวิเคราะห์: คุณสามารถวิเคราะห์ "ทุกอาการ" ที่ผู้ปกครองแจ้งมาได้ โดยไม่จำกัดเฉพาะกลุ่มอาการใดกลุ่มหนึ่ง เช่น ไข้ ไอ หวัด น้ำมูก เจ็บคอ อาเจียน ท้องเสีย ท้องผูก ปวดท้อง ผื่นผิวหนัง ผดร้อน แพ้อาหาร แพ้อากาศ ผื่นแพ้ แมลงกัดต่อย บาดแผล/หกล้ม ไข้ออกผื่น ตาแดง หูอักเสบ ปัญหาการนอน งอแงผิดปกติ ร้องไห้ไม่หยุด พัฒนาการช้า พฤติกรรม การกินยาก น้ำหนักตัว ฟันขึ้น และอาการอื่นๆ ที่พบได้ในเด็กวัยนี้ ให้วิเคราะห์อย่างละเอียดทุกครั้งตามข้อมูลที่ได้รับ ไม่ปฏิเสธการวิเคราะห์เพียงเพราะอาการนั้นดูไม่คุ้นเคยหรือซับซ้อน

รูปแบบการตอบ (ปรับใช้ตามความเหมาะสมของคำถาม):
1) สรุปสิ่งที่เข้าใจจากอาการที่แจ้งมา
2) วิเคราะห์สาเหตุที่เป็นไปได้ (เรียงจากที่พบบ่อย) พร้อมคำอธิบายสั้นๆ
3) คำแนะนำการดูแลเบื้องต้นที่บ้าน ที่ทำได้จริงและปลอดภัย
4) 🚩 สัญญาณอันตรายที่ต้องรีบพาไปพบแพทย์/โรงพยาบาลทันที

กติกาการตอบ:
- ตอบเป็นภาษาไทย สุภาพ อบอุ่น เข้าใจง่าย ไม่ใช้ศัพท์แพทย์ที่เข้าใจยากโดยไม่อธิบาย
- เมื่อคำถามเกี่ยวกับข้อมูลที่อาจเปลี่ยนแปลงได้ (เช่น ตารางวัคซีนล่าสุด, คำแนะนำจากกระทรวงสาธารณสุข, ข่าวสาร, การระบาดของโรค, ผลิตภัณฑ์, งานวิจัยใหม่ๆ) ให้ใช้เครื่องมือค้นหาเว็บเพื่อหาข้อมูลล่าสุดก่อนตอบเสมอ
- วิเคราะห์อาการให้ครบถ้วนและเป็นประโยชน์ที่สุดเท่าที่ข้อมูลจะเอื้ออำนวย แต่ระบุไว้เสมอว่านี่คือข้อมูลเพื่อการศึกษาเบื้องต้น ไม่ใช่การวินิจฉัยแทนแพทย์
- ห้ามให้ขนาดยาที่เฉพาะเจาะจงเกินคำแนะนำทั่วไปบนฉลาก ให้แนะนำปรึกษาเภสัชกร/แพทย์แทน
- หากอาการที่แจ้งมาเข้าข่ายฉุกเฉิน (เช่น ชัก หายใจลำบาก ตัวเขียว ซึมมาก ไม่ตอบสนอง ไข้สูงมากในเด็กเล็กมาก) ให้เน้นย้ำให้รีบไปโรงพยาบาลทันทีเป็นข้อความแรกสุด
EOT;

$payload = [
    'model' => ANTHROPIC_MODEL,
    'max_tokens' => 1536,
    'system' => $systemPrompt,
    'messages' => $messages,
    'tools' => [
        [
            'type' => 'web_search_20250305',
            'name' => 'web_search',
            'max_uses' => 3,
        ],
    ],
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => 60,
]);

$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    respond_error('เรียก API ไม่สำเร็จ: ' . $curlErr, 502);
}

$data = json_decode($response, true);

if ($httpCode !== 200) {
    $apiErrMsg = $data['error']['message'] ?? ('HTTP ' . $httpCode);
    respond_error('Anthropic API error: ' . $apiErrMsg, 502);
}

// ประกอบคำตอบข้อความ + รวบรวมแหล่งอ้างอิงจากผลการค้นเว็บ (ถ้ามี)
$replyText = '';
$sources = [];
$seenUrls = [];

if (!empty($data['content']) && is_array($data['content'])) {
    foreach ($data['content'] as $block) {
        if (($block['type'] ?? '') === 'text') {
            $replyText .= $block['text'];

            if (!empty($block['citations']) && is_array($block['citations'])) {
                foreach ($block['citations'] as $citation) {
                    $url = $citation['url'] ?? null;
                    if ($url && !isset($seenUrls[$url])) {
                        $seenUrls[$url] = true;
                        $sources[] = ['url' => $url, 'title' => $citation['title'] ?? $url];
                    }
                }
            }
        } elseif (($block['type'] ?? '') === 'web_search_tool_result') {
            $results = $block['content'] ?? [];
            if (is_array($results)) {
                foreach ($results as $r) {
                    $url = $r['url'] ?? null;
                    if ($url && !isset($seenUrls[$url])) {
                        $seenUrls[$url] = true;
                        $sources[] = ['url' => $url, 'title' => $r['title'] ?? $url];
                    }
                }
            }
        }
    }
}

if (trim($replyText) === '') {
    $replyText = 'ขออภัยค่ะ ระบบไม่สามารถสร้างคำตอบได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง';
}

echo json_encode([
    'reply' => $replyText,
    'sources' => $sources,
], JSON_UNESCAPED_UNICODE);
