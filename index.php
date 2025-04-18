<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'functions.php';

terminalStyle();
echo '<div class="container">';
echo '<h1>Тикет-система</h1>';

// Обработка создания тикета
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    $name = htmlspecialchars($_POST['name'] ?? '');
    $email = htmlspecialchars($_POST['email'] ?? '');
    $message = htmlspecialchars($_POST['message'] ?? '');
    
    if (!empty($name) && !empty($email) && !empty($message)) {
        $ticketId = createTicket($name, $email, $message);
        if ($ticketId) {
            echo "<div class='alert'>Тикет успешно создан! Ваш ID: <strong>$ticketId</strong></div>";
            echo '<p>Используйте его для проверки статуса.</p>';
            echo '<p><a href="view.php?id=' . $ticketId . '">Просмотреть тикет</a></p>';
        } else {
            echo '<div class="error">Ошибка при создании тикета!</div>';
        }
    } else {
        echo '<div class="error">Все поля обязательны для заполнения!</div>';
    }
}

// Форма для создания тикета
echo '<form method="POST">';
echo '<p>Имя: <input type="text" name="name" required></p>';
echo '<p>Email: <input type="email" name="email" required></p>';
echo '<p>Сообщение: <textarea name="message" rows="5" required></textarea></p>';
echo '<button type="submit" name="create">Создать тикет</button>';
echo '</form>';

// Форма для проверки статуса тикета
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_status'])) {
    $ticket_id = htmlspecialchars($_POST['ticket_id'] ?? '');
    $ticket = getTicket($ticket_id);
    
    if ($ticket) {
        echo '<div class="ticket">';
        echo '<h2>Статус тикета</h2>';
        echo '<p><strong>ID:</strong> ' . htmlspecialchars($ticket['id'] ?? 'N/A') . '</p>';
        echo '<p><strong>Имя:</strong> ' . htmlspecialchars($ticket['name'] ?? 'Не указано') . '</p>';
        echo '<p><strong>Email:</strong> ' . htmlspecialchars($ticket['email'] ?? 'Не указано') . '</p>';
        echo '<p><strong>Сообщение:</strong> ' . nl2br(htmlspecialchars($ticket['message'] ?? 'Нет сообщения')) . '</p>';
        echo '<p><strong>Статус:</strong> ' . htmlspecialchars($ticket['status'] ?? 'Неизвестно') . '</p>';
        echo '<p><strong>Создано:</strong> ' . htmlspecialchars($ticket['created_at'] ?? 'Неизвестно') . '</p>';
        echo '</div>';
    } else {
        echo '<div class="error">Тикет не найден!</div>';
    }
}

// Форма для проверки статуса тикета
echo '<h2>Проверка статуса тикета</h2>';
echo '<form method="POST">';
echo '<p>ID тикета: <input type="text" name="ticket_id" required></p>';
echo '<button type="submit" name="check_status">Проверить статус</button>';
echo '</form>';

// Форма для поиска тикетов по имени или e-mail
echo '<h2>Поиск тикетов</h2>';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_tickets'])) {
    $search_query = htmlspecialchars($_POST['search_query'] ?? '');
    $tickets = searchTickets($search_query);
    
    if (!empty($tickets)) {
        echo '<h3>Найденные тикеты:</h3>';
        echo '<div class="tickets-list">';
        
        foreach ($tickets as $ticket) {
            echo '<div class="ticket">';
            echo '<p><strong>ID:</strong> ' . htmlspecialchars($ticket['id'] ?? 'N/A') . '</p>';
            echo '<p><strong>Имя:</strong> ' . htmlspecialchars($ticket['name'] ?? 'Не указано') . '</p>';
            echo '<p><strong>Email:</strong> ' . htmlspecialchars($ticket['email'] ?? 'Не указано') . '</p>';
            echo '<p><strong>Статус:</strong> ' . htmlspecialchars($ticket['status'] ?? 'Неизвестно') . '</p>';
            echo '<p><a href="view.php?id=' . htmlspecialchars($ticket['id'] ?? '') . '">Просмотреть</a></p>';
            echo '</div>';
        }

        echo '</div>';
    } else {
        echo '<div class="error">Тикеты не найдены!</div>';
    }
}

echo '<form method="POST">';
echo '<p>Имя или Email: <input type="text" name="search_query" required></p>';
echo '<button type="submit" name="search_tickets">Поиск тикетов</button>';
echo '</form>';

echo '<p><a href="admin.php">Админ-панель</a></p>';
echo '</div>';
?>