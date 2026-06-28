<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

if (!empty($_POST['_hp'])) {
    echo json_encode(['ok' => true]);
    exit;
}

function s(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
}

$prenom   = s($_POST['prenom']   ?? '');
$nom      = s($_POST['nom']      ?? '');
$email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$site     = s($_POST['site']     ?? '');
$secteur  = s($_POST['secteur']  ?? '');
$objectif = s($_POST['objectif'] ?? '');
$budget   = s($_POST['budget']   ?? '');
$delai    = s($_POST['delai']    ?? '');

if (!$prenom || !$nom || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'champs_requis']);
    exit;
}

// ── SMTP ──────────────────────────────────────────────
$smtp_host = 'smtp.hostinger.com';
$smtp_port = 465;
$smtp_user = 'contact@iaforge.fr';
$smtp_pass = 'c7zrIx#fL8i';
$addr      = 'contact@iaforge.fr';

$subject = '=?UTF-8?B?' . base64_encode("Lead IAForge - $prenom $nom") . '?=';

$body = "Nouveau contact depuis iaforge.fr\r\n"
      . str_repeat('-', 38) . "\r\n"
      . "Prenom  : $prenom\r\n"
      . "Nom     : $nom\r\n"
      . "Email   : $email\r\n"
      . "Site    : " . ($site ?: '-') . "\r\n"
      . "Secteur : " . ($secteur ?: '-') . "\r\n"
      . "Budget  : " . ($budget ?: '-') . "\r\n"
      . "Delai   : " . ($delai ?: '-') . "\r\n\r\n"
      . "Objectif :\r\n" . ($objectif ?: '-') . "\r\n"
      . str_repeat('-', 38) . "\r\n"
      . "Repondre a : $email\r\n";

$ctx  = stream_context_create(['ssl' => [
    'verify_peer'      => false,
    'verify_peer_name' => false,
]]);
$sock = @stream_socket_client(
    "ssl://$smtp_host:$smtp_port", $errno, $errstr, 15,
    STREAM_CLIENT_CONNECT, $ctx
);

if (!$sock) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'smtp_connect']);
    exit;
}

function smtp_read($s): string {
    $r = '';
    while (($l = fgets($s, 1024)) !== false) {
        $r .= $l;
        if (strlen($l) >= 4 && $l[3] === ' ') break;
    }
    return $r;
}

function smtp_cmd($s, string $c): string {
    fwrite($s, $c . "\r\n");
    return smtp_read($s);
}

smtp_read($sock);
smtp_cmd($sock, "EHLO iaforge.fr");
smtp_cmd($sock, "AUTH LOGIN");
smtp_cmd($sock, base64_encode($smtp_user));
$auth = smtp_cmd($sock, base64_encode($smtp_pass));

if (strpos($auth, '235') === false) {
    fclose($sock);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'smtp_auth']);
    exit;
}

smtp_cmd($sock, "MAIL FROM:<$addr>");
smtp_cmd($sock, "RCPT TO:<$addr>");
smtp_cmd($sock, "DATA");

$msg = "From: IAForge <$addr>\r\n"
     . "To: $addr\r\n"
     . "Reply-To: $email\r\n"
     . "Subject: $subject\r\n"
     . "MIME-Version: 1.0\r\n"
     . "Content-Type: text/plain; charset=UTF-8\r\n"
     . "\r\n"
     . $body
     . "\r\n.";

$resp = smtp_cmd($sock, $msg);
smtp_cmd($sock, "QUIT");
fclose($sock);

echo json_encode(['ok' => strpos($resp, '250') !== false]);
