<?php
namespace Book100\Services\Database;

use Book100\Core\Database;
use Book100\Core\Env;
use Book100\Core\Paths;
use PDO;

final class Installer
{
    public static function install(bool $seed = true): array
    {
        self::ensureDirectories();
        $pdo = Database::pdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        self::createTables($pdo, $driver);
        self::migrateCurrentSchema($pdo, $driver);
        self::ensureIndexes($pdo, $driver);
        $admin = self::ensureAdmin($pdo);
        self::ensureSettings($pdo);
        $books = $seed ? self::seedBooks($pdo) : 0;
        $pages = $seed ? self::seedContentPages($pdo) : 0;
        self::ensureDefaultRegistrationForm($pdo);
        (new \Book100\Repository\AuthorRepository())->assignLegacyAuthors();
        self::ensureDefaultAuthorProfiles($pdo);
        return [
            'driver' => $driver,
            'admin' => $admin,
            'books_seeded' => $books,
            'pages_seeded' => $pages,
        ];
    }

    private static function ensureDirectories(): void
    {
        $root = dirname(__DIR__, 3);
        foreach ([
            'storage/cache',
            'storage/uploads',
            'storage/products',
            'storage/ebooks',
            'storage/logs',
            'storage/labels',
            'storage/reports',
        ] as $relative) {
            $path = $root . '/' . $relative;
            if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
                throw new \RuntimeException('Nie można utworzyć katalogu: ' . $relative);
            }
        }
        foreach (['uploads/products', 'uploads/authors', 'uploads/pages', 'uploads/events'] as $relative) {
            $path = Paths::publicRoot() . '/' . $relative;
            if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
                throw new \RuntimeException('Nie można utworzyć katalogu publicznego: ' . $relative);
            }
        }
    }

    public static function resetAndInstall(bool $seed = true): array
    {
        $pdo = Database::pdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        self::dropTables($pdo);
        return self::install($seed);
    }

    private static function createTables(PDO $pdo, string $driver): void
    {
        $auto = $driver === 'mysql' ? 'INTEGER PRIMARY KEY AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $json = $driver === 'mysql' ? 'JSON' : 'TEXT';
        $tiny = $driver === 'mysql' ? 'TINYINT(1)' : 'INTEGER';
        $longText = $driver === 'mysql' ? 'LONGTEXT' : 'TEXT';

        $sql = [
            "CREATE TABLE IF NOT EXISTS admins (id $auto, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role VARCHAR(40) NOT NULL DEFAULT 'owner', totp_secret_encrypted TEXT NULL, totp_pending_secret_encrypted TEXT NULL, totp_enabled_at DATETIME NULL, totp_last_counter BIGINT NULL, created_at DATETIME NOT NULL)",
            "CREATE TABLE IF NOT EXISTS authors (id $auto, name VARCHAR(190) NOT NULL, slug VARCHAR(190) NOT NULL UNIQUE, photo VARCHAR(255) NULL, short_bio TEXT NULL, publications_url VARCHAR(255) NULL, status VARCHAR(30) NOT NULL DEFAULT 'active', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)",
            "CREATE TABLE IF NOT EXISTS books (id $auto, old_wp_id INTEGER NULL, sku VARCHAR(100) NULL, slug VARCHAR(190) NOT NULL UNIQUE, title VARCHAR(255) NOT NULL, author_id INTEGER NULL, author VARCHAR(255) NULL, short_description TEXT NULL, description $longText NULL, price_gross DECIMAL(10,2) NOT NULL DEFAULT 0.00, currency CHAR(3) NOT NULL DEFAULT 'PLN', product_type VARCHAR(20) NOT NULL DEFAULT 'paper', status VARCHAR(30) NOT NULL DEFAULT 'draft', release_date DATE NULL, stock_qty INTEGER NOT NULL DEFAULT 0, manage_stock $tiny NOT NULL DEFAULT 1, weight_kg DECIMAL(8,3) NULL, length_cm DECIMAL(8,2) NULL, width_cm DECIMAL(8,2) NULL, height_cm DECIMAL(8,2) NULL, isbn VARCHAR(40) NULL, publisher VARCHAR(190) NULL, publication_year INTEGER NULL, pages INTEGER NULL, format VARCHAR(80) NULL, attributes_json $json NULL, cover_image VARCHAR(255) NULL, cover_original_url TEXT NULL, ebook_file_path VARCHAR(255) NULL, ebook_original_url TEXT NULL, seo_title VARCHAR(255) NULL, seo_description TEXT NULL, seo_keywords TEXT NULL, canonical_url VARCHAR(255) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)",
            "CREATE TABLE IF NOT EXISTS registration_forms (id $auto, name VARCHAR(190) NOT NULL, recipient_email VARCHAR(190) NOT NULL, email_subject VARCHAR(255) NULL, intro_text TEXT NULL, submit_label VARCHAR(100) NOT NULL DEFAULT 'Wyślij zgłoszenie', success_message TEXT NULL, fields_json $json NULL, status VARCHAR(30) NOT NULL DEFAULT 'active', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)",
            "CREATE TABLE IF NOT EXISTS content_pages (id $auto, old_wp_id INTEGER NULL, slug VARCHAR(190) NOT NULL UNIQUE, title VARCHAR(255) NOT NULL, author_id INTEGER NULL, registration_form_id INTEGER NULL, excerpt TEXT NULL, content $longText NULL, status VARCHAR(30) NOT NULL DEFAULT 'draft', featured_image VARCHAR(255) NULL, seo_title VARCHAR(255) NULL, seo_description TEXT NULL, canonical_url VARCHAR(255) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)",
            "CREATE TABLE IF NOT EXISTS events (id $auto, slug VARCHAR(190) NOT NULL UNIQUE, title VARCHAR(255) NOT NULL, author_id INTEGER NULL, excerpt TEXT NULL, content $longText NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NULL, location VARCHAR(255) NULL, organizer VARCHAR(190) NULL, featured_image VARCHAR(255) NULL, registration_form_id INTEGER NULL, status VARCHAR(30) NOT NULL DEFAULT 'draft', seo_title VARCHAR(255) NULL, seo_description TEXT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)",
            "CREATE TABLE IF NOT EXISTS registrations (id $auto, form_id INTEGER NOT NULL, content_page_id INTEGER NULL, event_id INTEGER NULL, source_label VARCHAR(255) NULL, person_name VARCHAR(255) NULL, email VARCHAR(190) NULL, phone VARCHAR(80) NULL, data_json $json NULL, status VARCHAR(30) NOT NULL DEFAULT 'new', admin_note TEXT NULL, consent_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)",
            "CREATE TABLE IF NOT EXISTS orders (id $auto, old_wp_id INTEGER NULL, order_number VARCHAR(80) NOT NULL UNIQUE, order_token VARCHAR(120) NULL UNIQUE, legacy_source VARCHAR(40) NULL, legacy_status VARCHAR(80) NULL, status VARCHAR(50) NOT NULL DEFAULT 'pending', customer_email VARCHAR(190) NOT NULL, customer_name VARCHAR(190) NOT NULL, customer_phone VARCHAR(60) NULL, billing_address_json $json NULL, shipping_address_json $json NULL, delivery_method VARCHAR(50) NOT NULL DEFAULT 'inpost_locker', inpost_point VARCHAR(80) NULL, subtotal_gross DECIMAL(10,2) NOT NULL DEFAULT 0.00, discount_gross DECIMAL(10,2) NOT NULL DEFAULT 0.00, shipping_gross DECIMAL(10,2) NOT NULL DEFAULT 0.00, total_gross DECIMAL(10,2) NOT NULL DEFAULT 0.00, currency CHAR(3) NOT NULL DEFAULT 'PLN', payment_provider VARCHAR(40) NULL, payment_status VARCHAR(40) NOT NULL DEFAULT 'created', shipment_status VARCHAR(40) NOT NULL DEFAULT 'not_created', stock_state VARCHAR(30) NOT NULL DEFAULT 'not_required', admin_note TEXT NULL, integration_notes TEXT NULL, terms_accepted_at DATETIME NULL, terms_snapshot $longText NULL, digital_content_consent_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, paid_at DATETIME NULL, shipped_at DATETIME NULL, completed_at DATETIME NULL, cancelled_at DATETIME NULL, refunded_at DATETIME NULL)",
            "CREATE TABLE IF NOT EXISTS order_items (id $auto, order_id INTEGER NOT NULL, book_id INTEGER NULL, old_product_id INTEGER NULL, sku VARCHAR(100) NULL, title VARCHAR(255) NOT NULL, quantity INTEGER NOT NULL DEFAULT 1, unit_price_gross DECIMAL(10,2) NOT NULL, total_gross DECIMAL(10,2) NOT NULL, ebook_file_path VARCHAR(255) NULL, sale_mode VARCHAR(30) NULL, release_date DATE NULL)",
            "CREATE TABLE IF NOT EXISTS payments (id $auto, order_id INTEGER NOT NULL, provider VARCHAR(40) NOT NULL, provider_session_id VARCHAR(190) NULL, provider_payment_id VARCHAR(190) NULL, status VARCHAR(40) NOT NULL DEFAULT 'created', amount_gross DECIMAL(10,2) NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'PLN', raw_event_json $json NULL, refund_id VARCHAR(190) NULL, refund_raw_json $json NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, confirmed_at DATETIME NULL, verified_at DATETIME NULL, refunded_at DATETIME NULL)",
            "CREATE TABLE IF NOT EXISTS shipments (id $auto, order_id INTEGER NOT NULL, provider VARCHAR(40) NOT NULL DEFAULT 'inpost', provider_shipment_id VARCHAR(190) NULL, method VARCHAR(60) NULL, receiver_name VARCHAR(190) NULL, receiver_email VARCHAR(190) NULL, receiver_phone VARCHAR(60) NULL, tracking_number VARCHAR(190) NULL, status VARCHAR(40) NOT NULL DEFAULT 'created', label_path VARCHAR(255) NULL, inpost_point VARCHAR(80) NULL, raw_response_json $json NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, sent_at DATETIME NULL, delivered_at DATETIME NULL)",
            "CREATE TABLE IF NOT EXISTS subscribers (id $auto, email VARCHAR(190) NOT NULL UNIQUE, name VARCHAR(190) NULL, source VARCHAR(60) NOT NULL DEFAULT 'footer', consent_marketing $tiny NOT NULL DEFAULT 0, consent_date DATETIME NULL, status VARCHAR(30) NOT NULL DEFAULT 'active', unsubscribe_token VARCHAR(120) NULL, created_at DATETIME NOT NULL)",
            "CREATE TABLE IF NOT EXISTS mailing_campaigns (id $auto, subject VARCHAR(255) NOT NULL, body $longText NULL, status VARCHAR(40) NOT NULL DEFAULT 'draft', recipients_count INTEGER NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, sent_at DATETIME NULL)",
            "CREATE TABLE IF NOT EXISTS mailing_recipients (id $auto, campaign_id INTEGER NOT NULL, subscriber_id INTEGER NULL, email VARCHAR(190) NOT NULL, status VARCHAR(40) NOT NULL DEFAULT 'queued', created_at DATETIME NOT NULL, sent_at DATETIME NULL)",
            "CREATE TABLE IF NOT EXISTS email_logs (id $auto, to_email VARCHAR(190) NOT NULL, reply_to VARCHAR(190) NULL, subject VARCHAR(255) NOT NULL, template VARCHAR(100) NULL, order_id INTEGER NULL, customer_name VARCHAR(190) NULL, body TEXT NULL, status VARCHAR(40) NOT NULL DEFAULT 'queued', last_error TEXT NULL, attempts INTEGER NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, sent_at DATETIME NULL)",
            "CREATE TABLE IF NOT EXISTS webhook_logs (id $auto, provider VARCHAR(40) NOT NULL, event_type VARCHAR(120) NULL, order_id INTEGER NULL, payload_json $json NULL, headers_json $json NULL, status VARCHAR(40) NOT NULL DEFAULT 'received', message TEXT NULL, created_at DATETIME NOT NULL)",
            "CREATE TABLE IF NOT EXISTS settings (id $auto, name VARCHAR(120) NOT NULL UNIQUE, value TEXT NULL, is_secret $tiny NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL)",
            "CREATE TABLE IF NOT EXISTS sales_reports (id $auto, period_year INTEGER NOT NULL, period_month INTEGER NOT NULL, period_start DATE NOT NULL, period_end DATE NOT NULL, file_path VARCHAR(255) NULL, status VARCHAR(30) NOT NULL DEFAULT 'generating', recipient_email VARCHAR(190) NULL, send_status VARCHAR(30) NOT NULL DEFAULT 'not_sent', email_log_id INTEGER NULL, orders_count INTEGER NOT NULL DEFAULT 0, units_count INTEGER NOT NULL DEFAULT 0, sales_net DECIMAL(12,2) NOT NULL DEFAULT 0.00, sales_vat DECIMAL(12,2) NOT NULL DEFAULT 0.00, sales_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00, shipping_net DECIMAL(12,2) NOT NULL DEFAULT 0.00, shipping_vat DECIMAL(12,2) NOT NULL DEFAULT 0.00, shipping_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00, discount_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00, refund_net DECIMAL(12,2) NOT NULL DEFAULT 0.00, refund_vat DECIMAL(12,2) NOT NULL DEFAULT 0.00, refund_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00, final_net DECIMAL(12,2) NOT NULL DEFAULT 0.00, final_vat DECIMAL(12,2) NOT NULL DEFAULT 0.00, final_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00, last_error TEXT NULL, generated_at DATETIME NULL, sent_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, UNIQUE(period_year, period_month))",
            "CREATE TABLE IF NOT EXISTS book_images (id $auto, book_id INTEGER NULL, old_wp_id INTEGER NULL, type VARCHAR(40) NOT NULL DEFAULT 'cover', source_url TEXT NULL, path VARCHAR(255) NOT NULL, alt_text VARCHAR(255) NULL, sort_order INTEGER NOT NULL DEFAULT 0, created_at DATETIME NOT NULL)",
        ];
        foreach ($sql as $statement) $pdo->exec($statement);
    }

    private static function migrateCurrentSchema(PDO $pdo, string $driver): void
    {
        $json = $driver === 'mysql' ? 'JSON' : 'TEXT';
        self::addColumnIfMissing($pdo, $driver, 'admins', 'totp_secret_encrypted', 'TEXT NULL');
        self::addColumnIfMissing($pdo, $driver, 'admins', 'totp_pending_secret_encrypted', 'TEXT NULL');
        self::addColumnIfMissing($pdo, $driver, 'admins', 'totp_enabled_at', 'DATETIME NULL');
        self::addColumnIfMissing($pdo, $driver, 'admins', 'totp_last_counter', 'BIGINT NULL');
        self::addColumnIfMissing($pdo, $driver, 'subscribers', 'unsubscribe_token', 'VARCHAR(120) NULL');
        self::addColumnIfMissing($pdo, $driver, 'books', 'author_id', 'INTEGER NULL');
        self::addColumnIfMissing($pdo, $driver, 'books', 'release_date', 'DATE NULL');
        self::addColumnIfMissing($pdo, $driver, 'books', 'canonical_url', 'VARCHAR(255) NULL');
        self::addColumnIfMissing($pdo, $driver, 'books', 'cover_original_url', 'TEXT NULL');
        self::addColumnIfMissing($pdo, $driver, 'books', 'publisher', 'VARCHAR(190) NULL');
        self::addColumnIfMissing($pdo, $driver, 'books', 'publication_year', 'INTEGER NULL');
        self::addColumnIfMissing($pdo, $driver, 'books', 'pages', 'INTEGER NULL');
        self::addColumnIfMissing($pdo, $driver, 'books', 'format', 'VARCHAR(80) NULL');
        self::addColumnIfMissing($pdo, $driver, 'books', 'attributes_json', "$json NULL");
        self::addColumnIfMissing($pdo, $driver, 'books', 'ebook_file_path', 'VARCHAR(255) NULL');
        self::addColumnIfMissing($pdo, $driver, 'books', 'ebook_original_url', 'TEXT NULL');
        self::addColumnIfMissing($pdo, $driver, 'books', 'seo_keywords', 'TEXT NULL');
        self::addColumnIfMissing($pdo, $driver, 'content_pages', 'author_id', 'INTEGER NULL');
        self::addColumnIfMissing($pdo, $driver, 'content_pages', 'registration_form_id', 'INTEGER NULL');
        self::addColumnIfMissing($pdo, $driver, 'events', 'author_id', 'INTEGER NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'legacy_source', 'VARCHAR(40) NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'legacy_status', 'VARCHAR(80) NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'discount_gross', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'vat_rate', 'DECIMAL(5,2) NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'subtotal_net', 'DECIMAL(10,2) NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'subtotal_vat', 'DECIMAL(10,2) NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'discount_net', 'DECIMAL(10,2) NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'discount_vat', 'DECIMAL(10,2) NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'shipping_net', 'DECIMAL(10,2) NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'shipping_vat', 'DECIMAL(10,2) NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'total_net', 'DECIMAL(10,2) NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'total_vat', 'DECIMAL(10,2) NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'stock_state', "VARCHAR(30) NOT NULL DEFAULT 'not_required'");
        self::addColumnIfMissing($pdo, $driver, 'orders', 'admin_note', 'TEXT NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'integration_notes', 'TEXT NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'terms_accepted_at', 'DATETIME NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'terms_snapshot', $driver === 'mysql' ? 'LONGTEXT NULL' : 'TEXT NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'digital_content_consent_at', 'DATETIME NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'updated_at', 'DATETIME NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'cancelled_at', 'DATETIME NULL');
        self::addColumnIfMissing($pdo, $driver, 'orders', 'refunded_at', 'DATETIME NULL');
        self::addColumnIfMissing($pdo, $driver, 'order_items', 'ebook_file_path', 'VARCHAR(255) NULL');
        self::addColumnIfMissing($pdo, $driver, 'order_items', 'sale_mode', 'VARCHAR(30) NULL');
        self::addColumnIfMissing($pdo, $driver, 'order_items', 'release_date', 'DATE NULL');
        self::addColumnIfMissing($pdo, $driver, 'order_items', 'vat_rate', 'DECIMAL(5,2) NULL');
        self::addColumnIfMissing($pdo, $driver, 'order_items', 'unit_price_net', 'DECIMAL(10,2) NULL');
        self::addColumnIfMissing($pdo, $driver, 'order_items', 'unit_vat', 'DECIMAL(10,2) NULL');
        self::addColumnIfMissing($pdo, $driver, 'order_items', 'total_net', 'DECIMAL(10,2) NULL');
        self::addColumnIfMissing($pdo, $driver, 'order_items', 'total_vat', 'DECIMAL(10,2) NULL');
        self::addColumnIfMissing($pdo, $driver, 'payments', 'refund_id', 'VARCHAR(190) NULL');
        self::addColumnIfMissing($pdo, $driver, 'payments', 'refund_raw_json', $driver === 'mysql' ? 'JSON NULL' : 'TEXT NULL');
        self::addColumnIfMissing($pdo, $driver, 'payments', 'verified_at', 'DATETIME NULL');
        self::addColumnIfMissing($pdo, $driver, 'payments', 'refunded_at', 'DATETIME NULL');
        self::addColumnIfMissing($pdo, $driver, 'email_logs', 'body', 'TEXT NULL');
        self::addColumnIfMissing($pdo, $driver, 'email_logs', 'reply_to', 'VARCHAR(190) NULL');
        self::addColumnIfMissing($pdo, $driver, 'email_logs', 'last_error', 'TEXT NULL');
        self::addColumnIfMissing($pdo, $driver, 'email_logs', 'attempts', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumnIfMissing($pdo, $driver, 'email_logs', 'order_id', 'INTEGER NULL');
        self::addColumnIfMissing($pdo, $driver, 'email_logs', 'customer_name', 'VARCHAR(190) NULL');
        self::addColumnIfMissing($pdo, $driver, 'email_logs', 'attachments_json', "$json NULL");
    }

    private static function addColumnIfMissing(PDO $pdo, string $driver, string $table, string $column, string $definition): void
    {
        try {
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
                $stmt->execute([$table, $column]);
                if ((int)$stmt->fetchColumn() === 0) $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            } else {
                $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $col) if (($col['name'] ?? '') === $column) return;
                $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            }
        } catch (\Throwable) {
            // Migracje startowe nie mogą zatrzymać prostego installera.
        }
    }

    private static function ensureIndexes(PDO $pdo, string $driver): void
    {
        $indexes = [
            'idx_books_status' => ['books', ['status']],
            'idx_books_product_type' => ['books', ['product_type']],
            'idx_books_author_id' => ['books', ['author_id']],
            'idx_authors_status' => ['authors', ['status']],
            'idx_content_pages_author_id' => ['content_pages', ['author_id']],
            'idx_content_pages_registration_form_id' => ['content_pages', ['registration_form_id']],
            'idx_registration_forms_status' => ['registration_forms', ['status']],
            'idx_events_status_starts_at' => ['events', ['status', 'starts_at']],
            'idx_events_author_id' => ['events', ['author_id']],
            'idx_events_registration_form_id' => ['events', ['registration_form_id']],
            'idx_registrations_form_id' => ['registrations', ['form_id']],
            'idx_registrations_event_id' => ['registrations', ['event_id']],
            'idx_registrations_content_page_id' => ['registrations', ['content_page_id']],
            'idx_registrations_status' => ['registrations', ['status']],
            'idx_orders_status' => ['orders', ['status']],
            'idx_orders_created_at' => ['orders', ['created_at']],
            'idx_orders_payment_status' => ['orders', ['payment_status']],
            'idx_orders_shipment_status' => ['orders', ['shipment_status']],
            'idx_orders_paid_at' => ['orders', ['paid_at']],
            'idx_orders_refunded_at' => ['orders', ['refunded_at']],
            'idx_order_items_order_id' => ['order_items', ['order_id']],
            'idx_order_items_book_id' => ['order_items', ['book_id']],
            'idx_payments_order_id' => ['payments', ['order_id']],
            'idx_payments_status' => ['payments', ['status']],
            'idx_shipments_order_id' => ['shipments', ['order_id']],
            'idx_shipments_status' => ['shipments', ['status']],
            'idx_email_logs_status' => ['email_logs', ['status']],
            'idx_email_logs_order_id' => ['email_logs', ['order_id']],
            'idx_webhook_logs_order_id' => ['webhook_logs', ['order_id']],
            'idx_sales_reports_email_log_id' => ['sales_reports', ['email_log_id']],
            'idx_book_images_book_id' => ['book_images', ['book_id']],
        ];

        foreach ($indexes as $name => [$table, $columns]) {
            $columnList = implode(', ', $columns);
            if ($driver === 'mysql') {
                $statement = $pdo->prepare(
                    'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
                );
                $statement->execute([$table, $name]);
                if ((int)$statement->fetchColumn() === 0) {
                    $pdo->exec("CREATE INDEX {$name} ON {$table} ({$columnList})");
                }
                continue;
            }
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$name} ON {$table} ({$columnList})");
        }
    }

    private static function dropTables(PDO $pdo): void
    {
        $tables = ['integration_test_runs','stage94_test_runs','migration_checkpoints','legacy_post_import_audits','legacy_validation_runs','legacy_media_import_runs','legacy_import_runs','migration_reports','cache_events','seo_pages','book_category_links','book_categories','book_images','sales_reports','registrations','events','content_pages','registration_forms','mailing_recipients','mailing_campaigns','webhook_logs','email_logs','subscribers','shipments','payments','order_items','orders','customers','users','books','authors','settings','admins'];
        foreach ($tables as $table) {
            try { $pdo->exec('DROP TABLE IF EXISTS ' . $table); } catch (\Throwable) {}
        }
    }

    private static function ensureAdmin(PDO $pdo): string
    {
        $email = Env::get('ADMIN_EMAIL', 'biuro@arka-pojednanie.pl');
        $password = Env::get('ADMIN_PASSWORD_CHANGE_ME', 'change-this-password');
        $exists = $pdo->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
        $exists->execute([$email]);
        if ($exists->fetchColumn()) return 'exists';
        $stmt = $pdo->prepare('INSERT INTO admins (email, password_hash, role, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), 'owner', date('Y-m-d H:i:s')]);
        return 'created';
    }


    private static function ensureSettings(PDO $pdo): void
    {
        $defaults = (new \Book100\Services\Storefront\StorefrontSettingsService())->defaults();
        $now = date('Y-m-d H:i:s');
        foreach ($defaults as $name => $value) {
            $exists = $pdo->prepare('SELECT id FROM settings WHERE name = ? LIMIT 1');
            $exists->execute([$name]);
            if ($exists->fetchColumn()) continue;
            $stmt = $pdo->prepare('INSERT INTO settings (name, value, is_secret, updated_at) VALUES (?, ?, 0, ?)');
            $stmt->execute([$name, $value, $now]);
        }
    }

    private static function ensureDefaultAuthorProfiles(PDO $pdo): void
    {
        $stmt = $pdo->prepare('SELECT id, photo, short_bio, publications_url FROM authors WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $stmt->execute(['Maciej Karwacki-Niecewicz']);
        $author = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$author) return;

        $photo = trim((string)($author['photo'] ?? ''));
        $bio = trim((string)($author['short_bio'] ?? ''));
        $publications = trim((string)($author['publications_url'] ?? ''));
        if ($bio === '') {
            $bio = 'Autor książki „Grzechy przeciwne nadziei”, prowadzący Rekolekcje Pojednania i współpracownik Rodziny Świętego Pawła.';
        }
        if ($publications === '') {
            $publications = '/rekolekcje-pojednania';
        }
        $pdo->prepare(
            'UPDATE authors SET photo = ?, short_bio = ?, publications_url = ?, updated_at = ? WHERE id = ?'
        )->execute([$photo ?: null, $bio, $publications, date('Y-m-d H:i:s'), (int)$author['id']]);
    }

    private static function ensureDefaultRegistrationForm(PDO $pdo): void
    {
        $name = 'Rekolekcje Pojednania';
        $find = $pdo->prepare('SELECT id FROM registration_forms WHERE name = ? LIMIT 1');
        $find->execute([$name]);
        $formId = (int)($find->fetchColumn() ?: 0);
        if ($formId === 0) {
            $now = date('Y-m-d H:i:s');
            $fields = [
                ['key' => 'first_name', 'label' => 'Imię', 'type' => 'text', 'enabled' => true, 'required' => true],
                ['key' => 'last_name', 'label' => 'Nazwisko', 'type' => 'text', 'enabled' => true, 'required' => true],
                ['key' => 'email', 'label' => 'E-mail', 'type' => 'email', 'enabled' => true, 'required' => true],
                ['key' => 'phone', 'label' => 'Telefon', 'type' => 'tel', 'enabled' => true, 'required' => true],
            ];
            $stmt = $pdo->prepare(
                'INSERT INTO registration_forms
                 (name, recipient_email, email_subject, intro_text, submit_label, success_message, fields_json, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $name,
                'rekolekcje@arka-pojednanie.pl',
                'Nowe zgłoszenie — Rekolekcje Pojednania',
                'Wypełnij krótki formularz. Skontaktujemy się w sprawie szczegółów.',
                'Wyślij zgłoszenie',
                'Dziękujemy. Twoje zgłoszenie zostało przyjęte.',
                json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'active',
                $now,
                $now,
            ]);
            $formId = (int)$pdo->lastInsertId();
        }
        if ($formId > 0) {
            $pdo->prepare(
                "UPDATE content_pages SET registration_form_id = ?
                 WHERE slug = 'rekolekcje-pojednania' AND registration_form_id IS NULL"
            )->execute([$formId]);
        }
    }

    private static function seedBooks(PDO $pdo): int
    {
        $path = dirname(__DIR__, 3) . '/seeds/books.json';
        if (!is_file($path)) return 0;
        $books = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $count = 0;
        $stmt = $pdo->prepare(
            'INSERT INTO books
            (old_wp_id, sku, slug, title, author, short_description, description, price_gross,
             currency, product_type, status, release_date, stock_qty, manage_stock, publisher,
             publication_year, pages, format, isbn, cover_image, seo_title, seo_description,
             created_at, updated_at)
            VALUES
            (:old_wp_id,:sku,:slug,:title,:author,:short_description,:description,:price_gross,
             :currency,:product_type,:status,:release_date,:stock_qty,:manage_stock,:publisher,
             :publication_year,:pages,:format,:isbn,:cover_image,:seo_title,:seo_description,
             :created_at,:updated_at)'
        );
        foreach ($books as $book) {
            $exists = $pdo->prepare('SELECT id FROM books WHERE slug = ? LIMIT 1');
            $exists->execute([$book['slug']]);
            if ($exists->fetchColumn()) continue;
            $now = date('Y-m-d H:i:s');
            $stmt->execute([
                ':old_wp_id' => $book['old_wp_id'] ?? null,
                ':sku' => $book['sku'] ?? null,
                ':slug' => $book['slug'],
                ':title' => $book['title'],
                ':author' => $book['author'] ?? 'Wydawnictwo Katolickie ARKA',
                ':short_description' => $book['short_description'] ?? null,
                ':description' => $book['description'] ?? $book['short_description'] ?? null,
                ':price_gross' => $book['price_gross'] ?? 0,
                ':currency' => $book['currency'] ?? 'PLN',
                ':product_type' => $book['product_type'] ?? 'paper',
                ':status' => $book['status'] ?? 'draft',
                ':release_date' => $book['release_date'] ?? null,
                ':stock_qty' => $book['stock_qty'] ?? 0,
                ':manage_stock' => !empty($book['manage_stock']) ? 1 : 0,
                ':publisher' => $book['publisher'] ?? 'Wydawnictwo Katolickie ARKA',
                ':publication_year' => $book['publication_year'] ?? null,
                ':pages' => $book['pages'] ?? null,
                ':format' => $book['format'] ?? null,
                ':isbn' => $book['isbn'] ?? null,
                ':cover_image' => $book['cover_image'] ?? null,
                ':seo_title' => $book['seo_title'] ?? (($book['title'] ?? '') . ' — ARKA'),
                ':seo_description' => $book['seo_description'] ?? $book['short_description'] ?? null,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $count++;
        }
        return $count;
    }

    private static function seedContentPages(PDO $pdo): int
    {
        $path = dirname(__DIR__, 3) . '/seeds/pages.json';
        if (!is_file($path)) return 0;
        $pages = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $count = 0;
        $stmt = $pdo->prepare(
            'INSERT INTO content_pages
            (old_wp_id, slug, title, author_id, excerpt, content, status, featured_image, seo_title,
             seo_description, canonical_url, created_at, updated_at)
            VALUES
            (:old_wp_id,:slug,:title,:author_id,:excerpt,:content,:status,:featured_image,:seo_title,
             :seo_description,:canonical_url,:created_at,:updated_at)'
        );
        foreach ($pages as $page) {
            $exists = $pdo->prepare('SELECT id FROM content_pages WHERE slug = ? LIMIT 1');
            $exists->execute([$page['slug']]);
            if ($exists->fetchColumn()) continue;
            $now = date('Y-m-d H:i:s');
            $stmt->execute([
                ':old_wp_id' => $page['old_wp_id'] ?? null,
                ':slug' => $page['slug'],
                ':title' => $page['title'],
                ':author_id' => !empty($page['author_id']) ? (int)$page['author_id'] : null,
                ':excerpt' => $page['excerpt'] ?? null,
                ':content' => $page['content'] ?? null,
                ':status' => $page['status'] ?? 'draft',
                ':featured_image' => $page['featured_image'] ?? null,
                ':seo_title' => $page['seo_title'] ?? null,
                ':seo_description' => $page['seo_description'] ?? $page['excerpt'] ?? null,
                ':canonical_url' => $page['canonical_url'] ?? null,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $count++;
        }
        return $count;
    }
}
