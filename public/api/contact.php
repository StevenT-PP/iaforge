<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

// Honeypot anti-spam
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
    echo json_encode(['ok' => false, 'error' => 'Champs requis manquants']);
    exit;
}

$to      = 'contact@iaforge.fr';
$subject = '=?UTF-8?B?' . base64_encode("Nouveau lead IAForge — $prenom $nom") . '?=';

$body = implode("\r\n", [
    "Nouveau contact depuis iaforge.fr",
    str_repeat('-', 40),
    "Prenom  : $prenom",
    "Nom     : $nom",
    "Email   : $email",
    "Site    : " . ($site ?: '-'),
    "Secteur : " . ($secteur ?: '-'),
    "Budget  : " . ($budget ?: '-'),
    "Delai   : " . ($delai ?: '-'),
    "",
    "Objectif :",
    $objectif ?: '-',
    str_repeat('-', 40),
    "Repondre directement a : $email",
]);

$headers = implode("\r\n", [
    "From: IAForge <contact@iaforge.fr>",
    "Reply-To: $email",
    "MIME-Version: 1.0",
    "Content-Type: text/plain; charset=UTF-8",
    "Content-Transfer-Encoding: 8bit",
]);

$sent = mail($to, $subject, $body, $headers);

echo json_encode(['ok' => (bool)$sent]);
