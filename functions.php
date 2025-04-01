<?php
require_once __DIR__ . '/config.php';

/**
 * Создает новый тикет
 * @param string $name Имя пользователя
 * @param string $email Email пользователя
 * @param string $message Сообщение с проблемой
 * @param string|null $telegram_id ID пользователя в Telegram (опционально)
 * @return string|false ID созданного тикета или false при ошибке
 */
function createTicket($name, $email, $message, $telegram_id = null) 
{
    // Валидация данных
    if (empty($name)) {
        error_log("Имя пользователя не может быть пустым.");
        return false;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("Некорректный email: " . htmlspecialchars($email));
        return false;
    }
    
    if (empty($message)) {
        error_log("Сообщение не может быть пустым.");
        return false;
    }

    // Генерация уникального ID для тикета
    $id = uniqid();
    $data = array(
        'id' => $id,
        'name' => htmlspecialchars($name),
        'email' => $email,
        'message' => htmlspecialchars($message),
        'status' => 'open',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'user_telegram_id' => $telegram_id // ID Telegram пользователя
    );

    // Создаем директорию, если не существует
    if (!file_exists(TICKETS_DIR)) {
        if (!mkdir(TICKETS_DIR, 0755, true)) {
            error_log("Не удалось создать директорию для тикетов");
            return false;
        }
    }

    $filePath = TICKETS_DIR . $id . '.json';
    $result = file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    if ($result === false) {
        error_log("Не удалось записать файл тикета: " . $filePath);
        return false;
    }
    
    logEvent("Создан тикет: " . $id);
    sendTelegramNotification("Новый тикет #" . $id . " от " . $data['name']);
    
    return $id; // Возврат ID созданного тикета
}

/**
 * Получает тикет по ID
 */
function getTicket($id) {
    $file = TICKETS_DIR . $id . '.json';
    if (!file_exists($file)) return null;

    $data = json_decode(file_get_contents($file), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logEvent("Ошибка чтения тикета $id: " . json_last_error_msg());
        return null;
    }

    return $data;
}

/**
 * Обновляет статус тикета и уведомляет пользователя и админа через Telegram
 */
function updateTicketStatus($id, $status) {
    // Проверяем допустимость статуса
    $allowedStatuses = ['open', 'in_progress', 'closed'];
    if (!in_array($status, $allowedStatuses)) {
        logEvent("Попытка установить недопустимый статус '$status' для тикета $id");
        return false;
    }

    // Получаем данные тикета
    $ticket = getTicket($id);
    if (!$ticket) {
        logEvent("Тикет $id не найден при попытке обновления статуса");
        return false;
    }

    // Сохраняем предыдущий статус для логирования
    $oldStatus = $ticket['status'] ?? 'unknown';
    
    // Обновляем данные
    $ticket['status'] = $status;
    $ticket['updated_at'] = date('Y-m-d H:i:s');

    // Сохраняем изменения
    $filePath = TICKETS_DIR . $id . '.json';
    if (!file_put_contents($filePath, json_encode($ticket, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        logEvent("Ошибка записи при обновлении тикета $id");
        return false;
    }

    // Логируем событие
    logEvent("Статус тикета $id изменен: $oldStatus → $status");
    
    // Отправляем уведомления
    sendStatusNotifications($ticket, $oldStatus, $status);
    
    return true;
}

/**
 * Отправляет уведомления об изменении статуса
 */
function sendStatusNotifications($ticket, $oldStatus, $newStatus) {
    $ticketId = $ticket['id'];
    $statusNames = $GLOBALS['statuses'] ?? [
        'open' => 'Открыт',
        'in_progress' => 'В работе',
        'closed' => 'Закрыт'
    ];

    // 1. Уведомление для админа (в Telegram)
    $adminMessage = "🔄 Статус тикета #$ticketId изменен\n";
    $adminMessage .= "📊 Было: " . ($statusNames[$oldStatus] ?? $oldStatus) . "\n";
    $adminMessage .= "📊 Стало: " . ($statusNames[$newStatus] ?? $newStatus) . "\n";
    $adminMessage .= "👤 Клиент: " . ($ticket['name'] ?? 'Не указан') . "\n";
    $adminMessage .= "📧 Email: " . ($ticket['email'] ?? 'Не указан') . "\n";
    $adminMessage .= "📝 Сообщение: " . substr($ticket['message'] ?? '', 0, 100) . (strlen($ticket['message'] ?? '') > 100 ? '...' : '');

    sendTelegramNotification($adminMessage);

    // 2. Уведомление для пользователя (если указан telegram_id)
    if (!empty($ticket['user_telegram_id'])) {
        $userMessage = "🔄 Обновлен статус вашего тикета #$ticketId\n";
        $userMessage .= "📊 Новый статус: " . ($statusNames[$newStatus] ?? $newStatus) . "\n\n";
        $userMessage .= "Вы можете проверить статус, отправив мне команду:\n/status #$ticketId";
        
        sendTelegramMessage($ticket['user_telegram_id'], $userMessage);
    }
}

/**
 * Удаляет тикет
 */
function deleteTicket($id, $confirm = false) {
    if (!$confirm) return false;

    $file = TICKETS_DIR . $id . '.json';
    if (file_exists($file)) {
        if (unlink($file)) {
            logEvent("Тикет удален: $id");
            sendTelegramNotification("Тикет #$id удален");
            return true;
        }
    }
    return false;
}

/**
 * Получает список тикетов с фильтрацией
 */
function getTickets($filter = 'all', $limit = 20) {
    $files = glob(TICKETS_DIR . '*.json');
    if (!$files) return [];

    $tickets = [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!$data) continue;

        // Устанавливаем значения по умолчанию для отсутствующих полей
        $data['id'] = $data['id'] ?? basename($file, '.json');
        $data['status'] = $data['status'] ?? 'unknown';

        if ($filter === 'all' || $data['status'] === $filter) {
            $tickets[] = $data;
        }
    }

    // Сортировка по дате (новые сначала)
    usort($tickets, function($a, $b) {
        return strtotime($b['created_at'] ?? '') - strtotime($a['created_at'] ?? '');
    });

    return array_slice($tickets, 0, $limit);
}

/**
 * Получает статистику по тикетам
 */
function getStats() {
    $files = glob(TICKETS_DIR . '*.json');
    if (!$files) return [
        'total' => 0,
        'open' => 0,
        'in_progress' => 0,
        'closed' => 0
    ];

    $stats = [
        'total' => 0,
        'open' => 0,
        'in_progress' => 0,
        'closed' => 0
    ];

    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!$data) continue;

        $stats['total']++;
        $status = $data['status'] ?? 'unknown';
        if (isset($stats[$status])) {
            $stats[$status]++;
        }
    }

    return $stats;
}

/**
 * Логирование событий
 */
function logEvent($message) {
    if (!file_exists(LOGS_DIR)) {
        mkdir(LOGS_DIR, 0755, true);
    }

    $logFile = LOGS_DIR . 'events.log';
    $logMessage = date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Получает последние записи логов
 */
function getLogs($limit = 10) {
    $logFile = LOGS_DIR . 'events.log';
    if (!file_exists($logFile)) return [];

    $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return array_reverse(array_slice($logs, -$limit));
}

/**
 * Отправляет уведомление в Telegram
 */
function sendTelegramMessage($chat_id, $text) {
    $bot_token = TELEGRAM_BOT_TOKEN;
    $url = "https://api.telegram.org/bot$bot_token/sendMessage";
    
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 5
        ]
    ];
    
    try {
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result === false) {
            error_log("Telegram API request failed to $chat_id");
            return false;
        }
        
        $response = json_decode($result, true);
        if (!$response['ok']) {
            error_log("Telegram API error: ".print_r($response, true));
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Telegram API Exception: ".$e->getMessage());
        return false;
    }
}

/**
 * Поиск тикетов по имени или email
 */
function searchTickets($search_query) {
    $files = glob(TICKETS_DIR . '*.json');
    $matched_tickets = [];
    
    if (empty($search_query)) {
        return $matched_tickets;
    }

    foreach ($files as $file) {
        $ticket = json_decode(file_get_contents($file), true);
        if (!$ticket) continue;
        
        // Проверяем наличие обязательных полей
        $ticket['id'] = $ticket['id'] ?? basename($file, '.json');
        $ticket['name'] = $ticket['name'] ?? '';
        $ticket['email'] = $ticket['email'] ?? '';
        $ticket['message'] = $ticket['message'] ?? '';
        $ticket['status'] = $ticket['status'] ?? 'unknown';
        $ticket['created_at'] = $ticket['created_at'] ?? '';

        // Поиск по имени или email (без учета регистра)
        if (stripos($ticket['name'], $search_query) !== false || 
            stripos($ticket['email'], $search_query) !== false) {
            $matched_tickets[] = $ticket;
        }
    }
    
    // Сортировка по дате создания (новые сначала)
    usort($matched_tickets, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return $matched_tickets;
}

/**
 * Отправляет уведомление администратору в Telegram
 * @param string $message Текст сообщения
 * @return bool Успешность отправки
 */
function sendTelegramNotification($message) {
    // Проверяем наличие необходимых констант
    if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_ADMIN_CHAT_ID')) {
        error_log("Telegram: Не настроены TELEGRAM_BOT_TOKEN или TELEGRAM_ADMIN_CHAT_ID");
        return false;
    }
    
    // Используем существующую функцию sendTelegramMessage
    return sendTelegramMessage(TELEGRAM_ADMIN_CHAT_ID, $message);
}

// Терминальный стиль CSS с улучшениями
function terminalStyle() {
    echo '<style>
   /* Основные стили */
        body {
            font-family: "Courier New", monospace;
            background-color: #f5f5f5;
            color: #333;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            padding: 25px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
        }

        h1, h2, h3 {
            color: #444;
            margin-top: 0;
        }

        h1 {
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 1.8rem;
        }

        h2 {
            font-size: 1.4rem;
            margin: 25px 0 15px 0;
            padding-bottom: 8px;
            border-bottom: 1px dashed #ddd;
        }

        /* Формы и кнопки */
        input, textarea, select {
            background: #fff;
            color: #333;
            border: 1px solid #ccc;
            padding: 10px 12px;
            font-family: inherit;
            border-radius: 3px;
            width: 100%;
            max-width: 500px;
            box-sizing: border-box;
            font-size: 1rem;
        }

        button, .btn {
            background: #eee;
            color: #333;
            border: 1px solid #ccc;
            padding: 10px 15px;
            font-family: inherit;
            border-radius: 3px;
            cursor: pointer;
            display: inline-block;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 1rem;
            margin: 5px 0;
        }

        button:hover, .btn:hover {
            background: #e0e0e0;
            border-color: #999;
        }

        /* Сообщения */
        .error {
            color: #d32f2f;
            background-color: #ffebee;
            padding: 12px;
            border-radius: 3px;
            margin-bottom: 15px;
            border-left: 4px solid #d32f2f;
        }

        .alert {
            color: #2e7d32;
            background-color: #e8f5e9;
            padding: 12px;
            border-radius: 3px;
            margin-bottom: 15px;
            border-left: 4px solid #2e7d32;
        }

        /* Статистика */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            display: block;
            margin-bottom: 8px;
            color: #555;
        }

        /* Фильтры */
        .ticket-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 15px;
            border: 1px solid #ddd;
            background: #f9f9f9;
            font-size: 0.95rem;
        }

        .filter-btn:hover {
            background: #eee;
        }

        /* Тикеты */
        .ticket {
            margin-bottom: 25px;
            border: 1px solid #e0e0e0;
            padding: 20px;
            border-radius: 5px;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .ticket-status {
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 0.95rem;
            font-weight: bold;
        }

        .status-open { background: #e8f5e9; color: #2e7d32; }
        .status-in_progress { background: #fff8e1; color: #ff8f00; }
        .status-closed { background: #f5f5f5; color: #616161; }

        .ticket-body p {
            margin: 10px 0;
            line-height: 1.6;
        }

        .ticket-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Опасные кнопки */
        .danger {
            background: #ffebee;
            border-color: #ef9a9a;
            color: #c62828;
        }

        .danger:hover {
            background: #ef9a9a;
            color: #fff;
        }

        /* Логи */
        .logs {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            padding: 15px;
            background: #fff;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .log-entry {
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
            font-family: "Courier New", monospace;
        }

        .log-entry:last-child {
            border-bottom: none;
        }

        /* Футер */
        .admin-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
        }

        .logout {
            background: #f5f5f5;
            border-color: #bdbdbd;
            padding: 10px 20px;
        }

        .logout:hover {
            background: #e0e0e0;
        }

        /* Поиск */
        .search-box {
            margin: 20px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 200px;
        }

        /* Мобильные стили */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            h1 {
                font-size: 1.5rem;
                padding-bottom: 8px;
            }
            
            h2 {
                font-size: 1.2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-number {
                font-size: 24px;
            }
            
            .ticket {
                padding: 15px;
            }
            
            .ticket-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .ticket-actions {
                flex-direction: column;
                gap: 8px;
            }
            
            button, .btn {
                width: 100%;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .container {
                padding: 12px;
            }
            
            h1 {
                font-size: 1.3rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .ticket-filters {
                flex-direction: column;
            }
            
            .filter-btn {
                width: 100%;
            }
            
            input, textarea, select {
                padding: 8px 10px;
            }
        }

        /* Анимации */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .ticket {
            animation: fadeIn 0.3s ease-out;
        }

        /* Дополнительные элементы интерфейса */
        .badge {
            display: inline-block;
            padding: 3px 7px;
            font-size: 0.8rem;
            font-weight: bold;
            line-height: 1;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 10px;
            background-color: #777;
        }

        .badge-primary { background-color: #337ab7; }
        .badge-success { background-color: #5cb85c; }
        .badge-warning { background-color: #f0ad4e; }
        .badge-danger { background-color: #d9534f; }

        /* Информационные блоки */
        .info-box {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #eee;
            border-left-width: 5px;
            border-radius: 3px;
            background-color: #f9f9f9;
        }

        .info-box-info {
            border-left-color: #5bc0de;
        }

        .info-box-warning {
            border-left-color: #f0ad4e;
        }

        /* Всплывающие подсказки */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.8rem;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        
        /* Новые улучшения для мобильных устройств */
        @media (max-width: 576px) {
            .container {
                padding: 10px;
            }
            
            h1 {
                font-size: 1.2rem;
                margin-bottom: 15px;
            }
            
            .ticket {
                padding: 12px;
                margin-bottom: 15px;
            }
            
            .ticket-actions .btn {
                padding: 8px 10px;
                font-size: 0.9rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .stat-card {
                padding: 12px;
            }
            
            .stat-number {
                font-size: 24px;
            }
        }
        
        /* Улучшения для темной темы */
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #121212;
                color: #e0e0e0;
            }
            
            .container {
                background-color: #1e1e1e;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            }
            
            h1, h2, h3 {
                color: #ffffff;
            }
            
            .ticket {
                background-color: #2d2d2d;
                border-color: #3d3d3d;
            }
            
            input, textarea, select {
                background-color: #2d2d2d;
                color: #ffffff;
                border-color: #3d3d3d;
            }
            
            .logs {
                background-color: #252525;
                border-color: #3d3d3d;
                color: #e0e0e0;
            }
        }
    </style>';
}