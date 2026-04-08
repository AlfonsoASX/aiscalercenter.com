<?php
declare(strict_types=1);

require_once __DIR__ . '/../../modules/landing-pages/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$accessToken = trim((string) ($toolContext['access_token'] ?? ''));
$userId = trim((string) ($toolContext['user_id'] ?? ''));
$action = trim((string) ($_POST['action'] ?? ''));

try {
    if ($accessToken === '' || $userId === '') {
        throw new RuntimeException('No encontramos la sesion segura para subir archivos.');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Metodo no permitido.');
    }

    if ($action !== 'upload_image') {
        throw new RuntimeException('Accion no soportada por el constructor.');
    }

    $file = $_FILES['image'] ?? null;

    if (!is_array($file)) {
        throw new RuntimeException('Selecciona una imagen para subir.');
    }

    $uploaded = landingBuilderUploadImageFile($file, $accessToken, $userId);

    echo json_encode([
        'success' => true,
        'data' => $uploaded,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => normalizeLandingBuilderException($exception),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function landingBuilderUploadImageFile(array $file, string $accessToken, string $userId): array
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode !== UPLOAD_ERR_OK) {
        throw new RuntimeException(landingBuilderUploadErrorMessage($errorCode));
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $originalName = basename((string) ($file['name'] ?? 'imagen'));
    $size = (int) ($file['size'] ?? 0);

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('No fue posible leer el archivo temporal.');
    }

    if ($size <= 0 || $size > 10 * 1024 * 1024) {
        throw new RuntimeException('La imagen debe pesar menos de 10 MB.');
    }

    $mimeType = landingBuilderDetectMimeType($tmpName);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
        'image/avif' => 'avif',
    ];

    if (!isset($extensions[$mimeType])) {
        throw new RuntimeException('Formato no permitido. Usa JPG, PNG, WebP, GIF, SVG o AVIF.');
    }

    $storagePath = appStorageUserPath(
        $userId,
        'landing_pages',
        gmdate('Y'),
        gmdate('m'),
        bin2hex(random_bytes(12)) . '.' . $extensions[$mimeType]
    );

    $body = file_get_contents($tmpName);

    if ($body === false) {
        throw new RuntimeException('No fue posible preparar la imagen para subirla.');
    }

    landingBuilderPutStorageObject($storagePath, $body, $mimeType, $accessToken);

    return [
        'bucket' => appStorageBucket(),
        'path' => $storagePath,
        'url' => appStoragePublicUrl($storagePath),
        'file_name' => $originalName,
        'mime_type' => $mimeType,
    ];
}

function landingBuilderPutStorageObject(
    string $path,
    string $body,
    string $mimeType,
    string $accessToken
): void {
    $curl = curl_init(appStorageObjectUrl($path));

    if ($curl === false) {
        throw new RuntimeException('No fue posible inicializar la subida a Supabase Storage.');
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . supabaseApiKey(),
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: ' . $mimeType,
            'x-upsert: false',
        ],
    ]);

    $responseBody = curl_exec($curl);

    if ($responseBody === false) {
        $error = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException('Error al subir la imagen a Supabase Storage: ' . $error);
    }

    $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($statusCode >= 400) {
        throw new RuntimeException('Supabase Storage respondio con error HTTP ' . $statusCode . ': ' . (string) $responseBody);
    }
}

function landingBuilderDetectMimeType(string $path): string
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    if ($finfo === false) {
        throw new RuntimeException('No fue posible validar el tipo de imagen.');
    }

    $mimeType = finfo_file($finfo, $path);
    finfo_close($finfo);

    return is_string($mimeType) ? $mimeType : '';
}

function landingBuilderUploadErrorMessage(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen supera el limite de carga permitido.',
        UPLOAD_ERR_PARTIAL => 'La imagen se subio de forma incompleta. Intenta nuevamente.',
        UPLOAD_ERR_NO_FILE => 'Selecciona una imagen para subir.',
        default => 'No fue posible subir la imagen.',
    };
}
