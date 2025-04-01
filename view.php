<?php
require_once 'functions.php';

terminalStyle();
echo '<div class="container">';
echo '<h1>Просмотр тикета</h1>';

if (isset($_GET['id'])) {
    $ticket = getTicket($_GET['id']);
    
    if ($ticket) {
        global $statuses;
        echo '<div class="ticket">';
        echo '<h2>Тикет #' . $ticket['id'] . '</h2>';
        echo '<p><strong>Имя:</strong> ' . $ticket['name'] . '</p>';
        echo '<p><strong>Email:</strong> ' . $ticket['email'] . '</p>';
        echo '<p><strong>Статус:</strong> <span class="status-' . $ticket['status'] . '">' . $statuses[$ticket['status']] . '</span></p>';
        echo '<p><strong>Создан:</strong> ' . $ticket['created_at'] . '</p>';
        echo '<p><strong>Обновлен:</strong> ' . $ticket['updated_at'] . '</p>';
        echo '<p><strong>Сообщение:</strong><br>' . nl2br($ticket['message']) . '</p>';
        echo '</div>';
    } else {
        echo '<p style="color:#f00">Тикет не найден!</p>';
    }
} else {
    echo '<form method="GET">';
    echo '<p>Введите ID тикета: <input type="text" name="id" required>';
    echo '<button type="submit">Найти</button></p>';
    echo '</form>';
}

echo '<p><a href="index.php">Создать новый тикет</a></p>';
echo '</div>';
?>