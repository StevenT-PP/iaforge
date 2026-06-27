<?php
/**
 * IAForge — Form handler
 * Déployer ce fichier à la racine de contact.iaforge.fr (via FTP Hostinger)
 * Ce script reçoit les POST du formulaire iaforge.fr et envoie un email à contact@iaforge.fr
 */

// CORS — autoriser uniquement iaforge.fr
$allowed = ['https://iaforge.fr', 'https://www.iaforge.fr'];
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

// Honeypot anti-spam
if (!empty($_POST['_hp'])) {
    // Bot détecté — réponse 200 silencieuse
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

$lines   = [
    "Nouveau contact depuis iaforge.fr",
    str_repeat('-', 40),
    "Prénom  : $prenom",
    "Nom     : $nom",
    "Email   : $email",
    "Site    : " . ($site ?: '—'),
    "Secteur : " . ($secteur ?: '—'),
    "Budget  : " . ($budget ?: '—'),
    "Délai   : " . ($delai ?: '—'),
    "",
    "Objectif :",
    $objectif ?: '—',
    str_repeat('-', 40),
    "Répondre directement à : $email",
];

$body    = implode("\r\n", $lines);

$headers = implode("\r\n", [
    "From: IAForge <contact@iaforge.fr>",
    "Reply-To: $email",
    "MIME-Version: 1.0",
    "Content-Type: text/plain; charset=UTF-8",
    "Content-Transfer-Encoding: 8bit",
    "X-Mailer: PHP/" . PHP_VERSION,
]);

$sent = mail($to, $subject, $body, $headers);

if ($sent) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'mail() failed']);
}
