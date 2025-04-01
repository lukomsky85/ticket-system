<?php
// Настройка ошибок и логов
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/logs/php_errors.log');

// Константы для хранения состояний
define('STATES_DIR', __DIR__.'/states/');
if (!file_exists(STATES_DIR)) {
    mkdir(STATES_DIR, 0755, true);
}

// Логирование
$log_file = __DIR__.'/logs/telegram_webhook.log';
file_put_contents($log_file, "\n".date('[Y-m-d H:i:s]')." Новый запрос\n", FILE_APPEND);

try {
    // Проверка метода запроса
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Неверный метод запроса");
    }

    // Подключение конфигурации
    require_once __DIR__.'/config.php';
    require_once __DIR__.'/functions.php';

    // Получение данных
    $input = file_get_contents('php://input');
    $update = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Ошибка JSON: ".json_last_error_msg());
    }

    // Логирование запроса
    file_put_contents($log_file, "Данные: ".print_r($update, true)."\n", FILE_APPEND);

    // Проверка сообщения
    if (!isset($update['message']['text'])) {
        throw new Exception("Нет текста в сообщении");
    }

    // Извлечение данных
    $text = trim($update['message']['text']);
    $chat_id = $update['message']['chat']['id'];
    $user_id = $update['message']['from']['id'];
    $username = $update['message']['from']['username'] ?? '';
    $first_name = $update['message']['from']['first_name'] ?? 'Пользователь';

    // Файл состояния пользователя
    $state_file = STATES_DIR."user_$chat_id.state";

    // Загрузка состояния
    $state = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : null;
    
    // Если файл состояния поврежден или пуст
    if (!$state || !isset($state['state'])) {
        $state = [
            'state' => 'idle',
            'data' => []
        ];
    }

    // Обработка сообщения
    $response = '';
    $new_state = $state['state'];
    $update_state = true;

    if (str_starts_with($text, '/')) {
        $command = strtolower(explode(' ', $text)[0]);
        
        switch ($command) {
            case '/start':
                $response = "👋 Привет, $first_name! Я бот поддержки.\n\n"
                          . "📋 Доступные команды:\n"
                          . "/new - Создать тикет\n"
                          . "/status #ID - Проверить статус\n"
                          . "/help - Справка\n"
                          . "/cancel - Отмена";
                $new_state = 'idle';
                break;

            case '/help':
                $response = "ℹ️ Справка:\n\n"
                          . "/new - Создать тикет\n"
                          . "/status #ID - Проверить статус\n"
                          . "/help - Эта справка\n"
                          . "/cancel - Отмена действия";
                $new_state = 'idle';
                break;

            case '/new':
                $new_state = 'awaiting_description';
                $response = "✍️ Опишите проблему в одном сообщении:\n\n"
                          . "Пример: Не работает кнопка 'Отправить' на главной странице\n\n"
                          . "Для отмены введите /cancel";
                break;

            case '/status':
                if (preg_match('/#([a-f0-9]+)/i', $text, $matches)) {
                    $ticket = getTicket($matches[1]);
                    if ($ticket) {
                        $status = [
                            'open' => '🟢 Открыт',
                            'in_progress' => '🟡 В работе',
                            'closed' => '🔴 Закрыт'
                        ][$ticket['status']] ?? $ticket['status'];
                        
                        $response = "📋 Тикет #{$ticket['id']}\n"
                                  . "📌 Статус: $status\n"
                                  . "📅 Создан: {$ticket['created_at']}\n"
                                  . "🔄 Обновлен: {$ticket['updated_at']}\n\n"
                                  . "💬 Сообщение: {$ticket['message']}";
                    } else {
                        $response = "❌ Тикет #{$matches[1]} не найден";
                    }
                } else {
                    $response = "⚠️ Укажите ID тикета в формате:\n/status #ID\n\n"
                              . "Пример: /status #abc123";
                }
                $new_state = 'idle';
                break;

            case '/cancel':
                if ($new_state !== 'idle') {
                    $response = "❌ Текущее действие отменено";
                    $new_state = 'idle';
                } else {
                    $response = "ℹ️ Нет активных действий для отмены";
                }
                break;

            default:
                $response = "❌ Неизвестная команда\n\n"
                          . "Используйте /help для списка доступных команд";
                $new_state = 'idle';
        }
    } else {
        // Обработка обычного текста
        switch ($state['state']) {
            case 'awaiting_description':
                // Проверяем, что текст не слишком короткий
                if (strlen(trim($text)) < 10) {
                    $response = "⚠️ Описание проблемы слишком короткое. Пожалуйста, опишите подробнее.";
                    $update_state = false; // Сохраняем состояние ожидания
                } else {
                    $ticket_id = createTicket($first_name, ($username ? $username.'@telegram.ru' : 'user_'.$user_id.'@telegram.ru'), $text, $user_id);
                    
                    if ($ticket_id) {
                        $response = "✅ Тикет #$ticket_id успешно создан!\n\n"
                                  . "📌 Текущий статус: 🟢 Открыт\n\n"
                                  . "Вы можете проверить статус командой:\n/status #$ticket_id";
                    } else {
                        error_log("Ошибка создания тикета для пользователя $user_id с текстом: $text");
                        $response = "❌ Не удалось создать тикет. Пожалуйста, попробуйте позже.";
                    }
                    $new_state = 'idle';
                }
                break;

            default:
                // Для любых других текстовых сообщений
                if ($state['state'] === 'idle') {
                    $response = "ℹ️ Для создания тикета введите /new\n"
                              . "Для просмотра команд введите /help";
                } else {
                    $response = "⚠️ Пожалуйста, завершите текущее действие или введите /cancel";
                }
                $update_state = false;
        }
    }

    // Сохранение состояния
    if ($update_state) {
        if ($new_state === 'idle') {
            // Удаляем файл состояния при возврате в idle
            if (file_exists($state_file)) {
                unlink($state_file);
            }
        } else {
            // Сохраняем новое состояние
            $state['state'] = $new_state;
            file_put_contents($state_file, json_encode($state));
        }
    }

    // Отправка ответа
    $send_result = sendTelegramMessage($chat_id, $response);
    file_put_contents($log_file, "Отправлен ответ: ".($send_result ? 'успешно' : 'ошибка')."\n", FILE_APPEND);

    if (!$send_result) {
        throw new Exception("Ошибка отправки сообщения в Telegram");
    }

    http_response_code(200);
    echo 'OK';

} catch (Exception $e) {
    file_put_contents($log_file, "ОШИБКА: ".$e->getMessage()."\n", FILE_APPEND);
    http_response_code(200);
    echo 'ERROR';
}
