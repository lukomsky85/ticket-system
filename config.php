<?php
// Конфигурация системы
define('TICKETS_DIR', __DIR__ . '/tickets/');
define('LOGS_DIR', __DIR__ . '/logs/');
define('ADMIN_PASSWORD', 'admin123'); // Замените на реальный пароль

// Настройки Telegram
define('TELEGRAM_BOT_TOKEN', 'ВАШ_ТОКЕН_БОТА'); // Вставьте токен вашего бота
define('TELEGRAM_CHAT_ID', 'ВАШ_CHAT_ID'); // Вставьте chat_id вашей группы или пользователя

// Настройки Telegram бота
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot'.TELEGRAM_BOT_TOKEN.'/');
define('TELEGRAM_WEBHOOK_URL', 'https://ВАШ_АДРЕС/webhook.php'); // Замените на URL вашего вебхука

// Статусы тикетов
$statuses = [
    'open' => 'Открыт',
    'in_progress' => 'В работе',
    'closed' => 'Закрыт'
];

// Инициализация сессии
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true
    ]);
}