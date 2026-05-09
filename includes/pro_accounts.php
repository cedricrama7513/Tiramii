<?php
/**
 * Comptes professionnels : tables, session, helpers.
 */
declare(strict_types=1);

function tiramii_ensure_pro_account_tables(PDO $pdo): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS pro_accounts (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                restaurant_name VARCHAR(255) NOT NULL,
                first_name VARCHAR(80) NOT NULL,
                last_name VARCHAR(80) NOT NULL DEFAULT '',
                phone VARCHAR(32) NOT NULL,
                address_line VARCHAR(255) NOT NULL,
                zip VARCHAR(12) NOT NULL,
                city VARCHAR(80) NOT NULL,
                status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_pro_accounts_email (email),
                KEY idx_pro_accounts_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
        /* droits CREATE refusés */
    }

    try {
        $chk = $pdo->query("SHOW COLUMNS FROM products LIKE 'price_pro_eur'");
        if ($chk && $chk->fetch() === false) {
            $pdo->exec(
                'ALTER TABLE products ADD COLUMN price_pro_eur DECIMAL(6,2) NULL DEFAULT NULL COMMENT \'NULL = même prix que particuliers\' AFTER price_eur'
            );
        }
    } catch (Throwable) {
        /* */
    }

    try {
        $chkO = $pdo->query("SHOW COLUMNS FROM orders LIKE 'pro_account_id'");
        if ($chkO && $chkO->fetch() === false) {
            $pdo->exec(
                'ALTER TABLE orders ADD COLUMN pro_account_id INT UNSIGNED NULL DEFAULT NULL AFTER id'
            );
            try {
                $pdo->exec('ALTER TABLE orders ADD KEY idx_orders_pro_account (pro_account_id)');
            } catch (Throwable) {
                /* */
            }
        }
    } catch (Throwable) {
        /* */
    }
}

function tiramii_pro_session_account_id(): ?int
{
    $id = $_SESSION['pro_account_id'] ?? null;
    if ($id === null || $id === '') {
        return null;
    }
    $n = (int) $id;

    return $n > 0 ? $n : null;
}

/**
 * @return array<string, mixed>|null
 */
function tiramii_pro_account_by_id(PDO $pdo, int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT id, email, restaurant_name, first_name, last_name, phone, address_line, zip, city, status, created_at
         FROM pro_accounts WHERE id = ? LIMIT 1'
    );
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

/**
 * Compte connecté et actif, sinon null.
 *
 * @return array<string, mixed>|null
 */
function tiramii_pro_current_account(PDO $pdo): ?array
{
    $id = tiramii_pro_session_account_id();
    if ($id === null) {
        return null;
    }
    $row = tiramii_pro_account_by_id($pdo, $id);
    if ($row === null || ($row['status'] ?? '') !== 'active') {
        return null;
    }

    return $row;
}

function tiramii_pro_set_session(int $accountId): void
{
    $_SESSION['pro_account_id'] = $accountId;
}

function tiramii_pro_clear_session(): void
{
    unset($_SESSION['pro_account_id']);
}

function tiramii_orders_has_pro_account_id(PDO $pdo): bool
{
    try {
        $st = $pdo->query("SHOW COLUMNS FROM orders LIKE 'pro_account_id'");

        return $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Throwable) {
        return false;
    }
}
