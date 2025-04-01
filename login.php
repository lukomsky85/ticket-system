<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['last_activity'] = time();
        header('Location: admin.php');
        exit;
    } else {
        $error = "Неверный пароль!";
    }
}

require_once 'functions.php';
terminalStyle();
?>
<div class="container">
    <h1>Авторизация</h1>
    <?php if (isset($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="POST">
        <p><label>Пароль: <input type="password" name="password" required></label></p>
        <button type="submit">Войти</button>
    </form>
</div>