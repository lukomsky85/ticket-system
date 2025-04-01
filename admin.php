<?php
// Должно быть первым, до любого вывода
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Обработка выхода
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Проверка времени бездействия (30 минут)
if (time() - ($_SESSION['last_activity'] ?? 0) > 1800) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

// Обработка действий
$message = '';
if (isset($_GET['action'], $_GET['id'])) {
    switch ($_GET['action']) {
        case 'close':
            if (updateTicketStatus($_GET['id'], 'closed')) {
                $message = "Тикет #{$_GET['id']} закрыт";
            }
            break;
        case 'progress':
            if (updateTicketStatus($_GET['id'], 'in_progress')) {
                $message = "Тикет #{$_GET['id']} взят в работу";
            }
            break;
        case 'delete':
            if (deleteTicket($_GET['id'], true)) {
                $message = "Тикет #{$_GET['id']} удалён";
            }
            break;
    }
}

// Получаем данные для отображения
$filter = $_GET['filter'] ?? 'all';
$tickets = getTickets($filter);
$stats = getStats();

// Начинаем вывод HTML
terminalStyle();
?>
<div class="container">
    <h1>Админ-панель</h1>
    <a href="?logout=1" class="btn logout" style="float: right;">Выйти</a>
    
    <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="admin-panel">
        <h2>📊 Статистика</h2>
        <div class="stats-grid">
            <div class="stat-card"><span class="stat-number"><?= $stats['total'] ?></span>Всего тикетов</div>
            <?php foreach ($GLOBALS['statuses'] as $key => $name): ?>
                <div class="stat-card"><span class="stat-number"><?= $stats[$key] ?? 0 ?></span><?= $name ?></div>
            <?php endforeach; ?>
        </div>

        <h2>📋 Список тикетов</h2>
        <div class="ticket-filters">
            <a href="?filter=all" class="filter-btn">Все</a>
            <?php foreach ($GLOBALS['statuses'] as $key => $name): ?>
                <a href="?filter=<?= $key ?>" class="filter-btn"><?= $name ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($tickets)): ?>
            <p>Тикетов не найдено</p>
        <?php else: ?>
            <?php foreach ($tickets as $ticket): ?>
                <?php
                $ticketId = $ticket['id'] ?? 'N/A';
                $status = $ticket['status'] ?? 'unknown';
                ?>
                <div class="ticket status-<?= htmlspecialchars($status) ?>">
                    <div class="ticket-header">
                        <h3>Тикет #<?= htmlspecialchars($ticketId) ?></h3>
                        <span class="ticket-status"><?= $GLOBALS['statuses'][$status] ?? 'Неизвестно' ?></span>
                    </div>
                    
                    <div class="ticket-body">
                        <p><strong>👤 От:</strong> <?= htmlspecialchars($ticket['name'] ?? 'Не указано') ?> (<?= htmlspecialchars($ticket['email'] ?? 'Не указано') ?>)</p>
                        <p><strong>📅 Создан:</strong> <?= htmlspecialchars($ticket['created_at'] ?? 'Неизвестно') ?></p>
                        <p><strong>💬 Сообщение:</strong> <?= nl2br(htmlspecialchars(substr($ticket['message'] ?? 'Нет сообщения', 0, 100))) ?><?= (strlen($ticket['message'] ?? '') > 100) ? '...' : '' ?></p>
                    </div>
                    
                    <div class="ticket-actions">
                        <a href="view.php?id=<?= htmlspecialchars($ticketId) ?>" class="btn">Просмотр</a>
                        <?php if ($status !== 'closed'): ?>
                            <a href="?action=close&id=<?= htmlspecialchars($ticketId) ?>" class="btn">Закрыть</a>
                        <?php endif; ?>
                        <?php if ($status !== 'in_progress'): ?>
                            <a href="?action=progress&id=<?= htmlspecialchars($ticketId) ?>" class="btn">В работу</a>
                        <?php endif; ?>
                        <a href="?action=delete&id=<?= htmlspecialchars($ticketId) ?>" class="btn danger" onclick="return confirm('Удалить этот тикет?')">Удалить</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h2>📝 Логи системы</h2>
        <div class="logs">
            <?php foreach (getLogs(10) as $log): ?>
                <div class="log-entry"><?= htmlspecialchars($log) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>