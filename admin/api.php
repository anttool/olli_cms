<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';

use OlliCMS\ThemeManager;

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

// 1. Julkinen toiminto: Yhteydenottolomakkeen lähetys (suojattu CSRF:llä, Honeypotilla ja Rate Limitillä)
if ($action === 'submit_contact' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode((string)$raw, true) ?: $_POST;

    // Honeypot-tarkistus roskapostiboteille
    if (!empty($payload['website_hp'])) {
        echo json_encode(['ok' => true]);
        exit;
    }

    // CSRF-tarkistus
    if (!$auth->verifyCsrf((string)($payload['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Istunto on vanhentunut. Päivitä sivu ja yritä uudelleen.']);
        exit;
    }

    // Rate-limit: enintään 1 viesti / 30 sekuntia per istunto
    $lastContact = $_SESSION['last_contact_time'] ?? 0;
    if (time() - $lastContact < 30) {
        echo json_encode(['ok' => false, 'error' => 'Odota hetki ennen uuden viestin lähettämistä.']);
        exit;
    }

    $name = mb_substr(trim((string)($payload['name'] ?? '')), 0, 120);
    $email = mb_substr(trim((string)($payload['email'] ?? '')), 0, 150);
    $subject = mb_substr(trim((string)($payload['subject'] ?? 'Yhteydenotto sivustolta')), 0, 200);
    $message = mb_substr(trim((string)($payload['message'] ?? '')), 0, 4000);

    if ($name === '' || $email === '' || $message === '') {
        echo json_encode(['ok' => false, 'error' => 'Täytä kaikki pakolliset kentät.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'error' => 'Tarkista sähköpostiosoitteen muoto.']);
        exit;
    }

    $saved = $storage->saveMessage([
        'name' => $name,
        'email' => $email,
        'subject' => $subject,
        'message' => $message,
    ]);

    if ($saved) {
        $_SESSION['last_contact_time'] = time();
    }

    echo json_encode(['ok' => $saved]);
    exit;
}

// Kaikki muut API-kutsut vaativat kirjautumisen
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Kirjautuminen vaaditaan.']);
    exit;
}

// Tarkistetaan CSRF-tunniste
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? ''));
if (!$auth->verifyCsrf((string)$csrfToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Virheellinen CSRF-tunniste. Päivitä sivu.']);
    exit;
}

$rawBody = file_get_contents('php://input');
$jsonBody = $rawBody ? (json_decode($rawBody, true) ?: []) : [];

switch ($action) {
    case 'save_page':
        if (empty($jsonBody['title'])) {
            echo json_encode(['ok' => false, 'error' => 'Sivun otsikko puuttuu.']);
            exit;
        }
        $ok = $storage->savePage($jsonBody);
        echo json_encode(['ok' => $ok, 'slug' => $storage->slugify($jsonBody['slug'] ?? $jsonBody['title'])]);
        break;

    case 'delete_page':
        $slug = (string)($jsonBody['slug'] ?? '');
        $ok = $storage->deletePage($slug);
        echo json_encode(['ok' => $ok]);
        break;

    case 'save_post':
        if (empty($jsonBody['title'])) {
            echo json_encode(['ok' => false, 'error' => 'Artikkelin otsikko puuttuu.']);
            exit;
        }
        $ok = $storage->savePost($jsonBody);
        echo json_encode(['ok' => $ok, 'slug' => $storage->slugify($jsonBody['slug'] ?? $jsonBody['title'])]);
        break;

    case 'delete_post':
        $slug = (string)($jsonBody['slug'] ?? '');
        $ok = $storage->deletePost($slug);
        echo json_encode(['ok' => $ok]);
        break;

    case 'save_theme':
        if (!empty($jsonBody['apply_preset'])) {
            $presets = ThemeManager::getPresets();
            $presetKey = (string)$jsonBody['apply_preset'];
            if (isset($presets[$presetKey])) {
                $presetData = $presets[$presetKey];
                $presetData['preset'] = $presetKey;
                $ok = $storage->saveThemeConfig($presetData);
                echo json_encode(['ok' => $ok, 'theme' => $presetData]);
                exit;
            }
        }
        $ok = $storage->saveThemeConfig($jsonBody);
        echo json_encode(['ok' => $ok, 'theme' => $jsonBody]);
        break;

    case 'save_site_config':
        $ok = $storage->saveSiteConfig($jsonBody);
        echo json_encode(['ok' => $ok]);
        break;

    case 'upload_media':
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'error' => 'Tiedoston lataus epäonnistui.']);
            exit;
        }

        // Kokorajoitus: enintään 10 Mt
        if (($_FILES['file']['size'] ?? 0) > 10 * 1024 * 1024) {
            echo json_encode(['ok' => false, 'error' => 'Tiedosto on liian suuri (enintään 10 Mt).']);
            exit;
        }

        $origName = basename((string)$_FILES['file']['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        // Sallitut päätteet ja niitä vastaavat MIME-tyypit (SVG jätetty pois XSS-riskin vuoksi)
        $allowedMimes = [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'webp' => ['image/webp'],
            'pdf'  => ['application/pdf'],
            'zip'  => ['application/zip', 'application/x-zip-compressed'],
            'txt'  => ['text/plain'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ];

        if (!isset($allowedMimes[$ext])) {
            echo json_encode(['ok' => false, 'error' => 'Tiedostotyyppi ei ole sallittu.']);
            exit;
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo ? finfo_file($finfo, $_FILES['file']['tmp_name']) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
            if ($detectedMime && !in_array($detectedMime, $allowedMimes[$ext], true)) {
                echo json_encode(['ok' => false, 'error' => 'Tiedoston sisältö ei vastaa tiedostopäätettä.']);
                exit;
            }
        }

        $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
        $safeBase = substr($safeBase ?: 'tiedosto', 0, 60);
        $finalName = $safeBase . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $dest = $storage->getContentDir() . '/uploads/' . $finalName;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            @chmod($dest, 0644);
            echo json_encode([
                'ok' => true,
                'filename' => $finalName,
                'url' => 'content/uploads/' . rawurlencode($finalName)
            ]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Tiedoston tallennus epäonnistui.']);
        }
        break;

    case 'delete_media':
        $filename = basename((string)($jsonBody['filename'] ?? ''));
        if ($filename === '' || $filename === '.htaccess') {
            echo json_encode(['ok' => false, 'error' => 'Virheellinen tiedostonimi.']);
            exit;
        }
        $ok = $storage->deleteFile('uploads/' . $filename);
        echo json_encode(['ok' => $ok]);
        break;

    case 'delete_message':
        $id = (string)($jsonBody['id'] ?? '');
        $ok = $storage->deleteMessage($id);
        echo json_encode(['ok' => $ok]);
        break;

    case 'change_password':
        $newPass = (string)($jsonBody['new_password'] ?? '');
        $user = $auth->getUser();
        $ok = $auth->changePassword($user['username'] ?? 'admin', $newPass);
        echo json_encode(['ok' => $ok, 'error' => $ok ? null : 'Salasanan tulee olla vähintään 8 merkkiä.']);
        break;

    case 'export_backup':
        if (!class_exists('ZipArchive')) {
            echo json_encode(['ok' => false, 'error' => 'PHP ZipArchive -laajennus ei ole käytössä palvelimella. Voit kopioida content/-kansion suoraan FTP:llä.']);
            exit;
        }
        $zipFile = sys_get_temp_dir() . '/olli_cms_backup_' . bin2hex(random_bytes(8)) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $source = $storage->getContentDir();
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                $filePath = $file->getRealPath();
                $relPath = 'content/' . substr($filePath, strlen($source) + 1);
                $relPath = str_replace('\\', '/', $relPath);
                if ($file->isDir()) {
                    $zip->addEmptyDir($relPath);
                } else {
                    $zip->addFile($filePath, $relPath);
                }
            }
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="olli_cms_backup_' . date('Y-m-d') . '.zip"');
            header('Content-Length: ' . filesize($zipFile));
            readfile($zipFile);
            unlink($zipFile);
            exit;
        }
        echo json_encode(['ok' => false, 'error' => 'ZIP-paketin luonti epäonnistui.']);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Tuntematon API-kutsu.']);
        break;
}
