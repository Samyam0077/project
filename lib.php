<?php
// Shared helpers (no output).

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function questions_file() { return __DIR__ . '/data/questions.json'; }
function messages_file()  { return __DIR__ . '/private/messages.json.php'; }

function read_questions() {
    $f = questions_file();
    if (!is_file($f)) return ['categories' => []];
    $j = json_decode(file_get_contents($f), true);
    if (!is_array($j) || !isset($j['categories']) || !is_array($j['categories'])) return ['categories' => []];
    return $j;
}

function write_questions($data) {
    $data['categories'] = array_values($data['categories']);
    foreach ($data['categories'] as &$c) { $c['items'] = array_values(isset($c['items']) ? $c['items'] : []); }
    return file_put_contents(
        questions_file(),
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    ) !== false;
}

// Messages are stored in a .php file that starts with an exit line,
// so the raw data can never be downloaded even if .htaccess is ignored.
function read_messages() {
    $f = messages_file();
    if (!is_file($f)) return [];
    $raw = file_get_contents($f);
    $pos = strpos($raw, "\n");
    if ($pos === false) return [];
    $j = json_decode(substr($raw, $pos + 1), true);
    return is_array($j) ? $j : [];
}

function write_messages($list) {
    $f = messages_file();
    if (!is_dir(dirname($f))) @mkdir(dirname($f), 0755, true);
    $head = "<?php http_response_code(404); exit; ?>\n";
    return file_put_contents($f, $head . json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}
