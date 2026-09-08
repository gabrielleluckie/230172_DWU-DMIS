<?php

declare(strict_types=1);

/**
 * Tokenized partner-view helpers and PHPMailer notifications
 * for newly registered partnership agreements.
 */

use PHPMailer\PHPMailer\Exception as PhpMailerException;
use PHPMailer\PHPMailer\PHPMailer;

if (!defined('PDMIS_MAIL_FROM_ADDRESS')) {
    define('PDMIS_MAIL_FROM_ADDRESS', 'gabrielleluckie20@gmail.com');
    define('PDMIS_MAIL_FROM_NAME', 'DWU Partnership Registry');
    define('PDMIS_MAIL_SMTP_HOST', 'smtp.gmail.com');
    define('PDMIS_MAIL_SMTP_USER', 'gabrielleluckie20@gmail.com');
    define('PDMIS_MAIL_SMTP_PASSWORD', 'motd uyeq fwfj nidb');
    define('PDMIS_MAIL_SMTP_PORT', 587);
    define('PDMIS_MAIL_SMTP_SECURE', 'tls');
}

function agreementNotifyTableName(PDO $pdo): string
{
    if (function_exists('agreementTableName')) {
        $table = agreementTableName($pdo);

        if (is_string($table) && $table !== '') {
            return $table;
        }
    }

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $lower = array_map('strtolower', $tables);

    foreach (['agreement', 'agreements'] as $name) {
        $index = array_search($name, $lower, true);

        if ($index !== false) {
            return (string) $tables[$index];
        }
    }

    throw new RuntimeException('Agreement table was not found.');
}

function ensureAgreementAccessTokenColumn(PDO $pdo): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $table = agreementNotifyTableName($pdo);
    $columns = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('access_token', $columns, true)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN access_token VARCHAR(64) NULL DEFAULT NULL");
    }

    $indexes = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Column_name = 'access_token'")->fetchAll();

    if ($indexes === []) {
        $pdo->exec("ALTER TABLE `{$table}` ADD UNIQUE KEY uq_agreement_access_token (access_token)");
    }

    $ensured = true;
}

function generateUniqueAgreementAccessToken(PDO $pdo): string
{
    ensureAgreementAccessTokenColumn($pdo);

    $table = agreementNotifyTableName($pdo);
    $stmt = $pdo->prepare("SELECT Agree_ID FROM `{$table}` WHERE access_token = :token LIMIT 1");

    do {
        $token = bin2hex(random_bytes(32));
        $stmt->execute(['token' => $token]);
    } while ($stmt->fetch() !== false);

    return $token;
}

function storeAgreementAccessToken(PDO $pdo, int $agreeId, string $token): void
{
    ensureAgreementAccessTokenColumn($pdo);

    $table = agreementNotifyTableName($pdo);
    $stmt = $pdo->prepare(
        "UPDATE `{$table}` SET access_token = :token WHERE Agree_ID = :id LIMIT 1"
    );
    $stmt->execute([
        'token' => $token,
        'id'    => $agreeId,
    ]);
}

function assignAgreementAccessToken(PDO $pdo, int $agreeId, ?string $existingToken = null): string
{
    $existingToken = trim((string) $existingToken);

    if (isValidAgreementAccessToken($existingToken)) {
        return $existingToken;
    }

    ensureAgreementAccessTokenColumn($pdo);

    $table = agreementNotifyTableName($pdo);
    $stmt = $pdo->prepare(
        "SELECT access_token FROM `{$table}` WHERE Agree_ID = :id LIMIT 1"
    );
    $stmt->execute(['id' => $agreeId]);
    $row = $stmt->fetch();
    $current = is_array($row) ? trim((string) ($row['access_token'] ?? '')) : '';

    if (isValidAgreementAccessToken($current)) {
        return $current;
    }

    $token = generateUniqueAgreementAccessToken($pdo);
    storeAgreementAccessToken($pdo, $agreeId, $token);

    return $token;
}

function isValidAgreementAccessToken(string $token): bool
{
    return preg_match('/^[a-f0-9]{64}$/i', $token) === 1;
}

function agreementPublicViewUrl(string $token): string
{
    $query = 'token=' . rawurlencode($token);

    if (function_exists('appUrl') && !empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) $_SERVER['HTTP_HOST'];

        return $scheme . '://' . $host . appUrl('view_agreement.php') . '?' . $query;
    }

    return 'http://localhost/IS406_PartnershipRegistry/view_agreement.php?' . $query;
}

function requirePdmisPhpMailer(): void
{
    if (class_exists(PHPMailer::class, false) || class_exists('PHPMailer', false)) {
        return;
    }

    $root = dirname(__DIR__);
    $composerAutoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

    if (is_file($composerAutoload)) {
        require_once $composerAutoload;

        if (class_exists(PHPMailer::class) || class_exists('PHPMailer')) {
            return;
        }
    }

    $manualBases = [
        __DIR__ . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'src',
        __DIR__ . DIRECTORY_SEPARATOR . 'PHPMailer',
        $root . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'src',
        $root . DIRECTORY_SEPARATOR . 'PHPMailer',
    ];

    foreach ($manualBases as $base) {
        $mailerFile = $base . DIRECTORY_SEPARATOR . 'PHPMailer.php';

        if (!is_file($mailerFile)) {
            continue;
        }

        $exceptionFile = $base . DIRECTORY_SEPARATOR . 'Exception.php';
        $smtpFile = $base . DIRECTORY_SEPARATOR . 'SMTP.php';

        if (is_file($exceptionFile)) {
            require_once $exceptionFile;
        }

        require_once $mailerFile;

        if (is_file($smtpFile)) {
            require_once $smtpFile;
        }

        return;
    }

    throw new RuntimeException(
        'PHPMailer was not found. Install it with Composer (vendor/autoload.php) or copy the library into includes/PHPMailer/.'
    );
}

function createPdmisPhpMailer(): object
{
    requirePdmisPhpMailer();

    if (class_exists(PHPMailer::class)) {
        $mail = new PHPMailer(true);
    } elseif (class_exists('PHPMailer')) {
        $mail = new \PHPMailer(true);
    } else {
        throw new RuntimeException('PHPMailer class is not available after loading.');
    }

    $mail->isSMTP();
    $mail->Host       = PDMIS_MAIL_SMTP_HOST;
    $mail->Port       = PDMIS_MAIL_SMTP_PORT;
    $mail->SMTPSecure = PDMIS_MAIL_SMTP_SECURE;
    $mail->SMTPAuth   = true;
    $mail->Username   = PDMIS_MAIL_SMTP_USER;
    $mail->Password   = PDMIS_MAIL_SMTP_PASSWORD;
    $mail->setFrom(PDMIS_MAIL_FROM_ADDRESS, PDMIS_MAIL_FROM_NAME);
    $mail->isHTML(true);

    return $mail;
}

function fetchOfficeDirectorEmail(PDO $pdo): string
{
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $lower = array_map('strtolower', $tables);
    $usersIndex = array_search('users', $lower, true);

    if ($usersIndex === false) {
        return '';
    }

    $usersTable = (string) $tables[$usersIndex];
    $stmt = $pdo->query(
        "SELECT Email
         FROM `{$usersTable}`
         WHERE Role IN ('Partnership Director', 'partnership_director')
         ORDER BY User_ID ASC
         LIMIT 1"
    );
    $row = $stmt->fetch();

    return is_array($row) ? trim((string) ($row['Email'] ?? '')) : '';
}

function fetchUserEmailById(PDO $pdo, int $userId): string
{
    if ($userId <= 0) {
        return '';
    }

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $lower = array_map('strtolower', $tables);
    $usersIndex = array_search('users', $lower, true);

    if ($usersIndex === false) {
        return '';
    }

    $usersTable = (string) $tables[$usersIndex];
    $stmt = $pdo->prepare("SELECT Email FROM `{$usersTable}` WHERE User_ID = :id LIMIT 1");
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();

    return is_array($row) ? trim((string) ($row['Email'] ?? '')) : '';
}

/**
 * @return array<string, mixed>|null
 */
function fetchAgreementByAccessToken(PDO $pdo, string $token): ?array
{
    if (!isValidAgreementAccessToken($token)) {
        return null;
    }

    ensureAgreementAccessTokenColumn($pdo);

    $agreementTable = agreementNotifyTableName($pdo);
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $lower = array_map('strtolower', $tables);

    $pick = static function (string $singular, string $plural) use ($tables, $lower): ?string {
        foreach ([$singular, $plural] as $name) {
            $index = array_search(strtolower($name), $lower, true);

            if ($index !== false) {
                return (string) $tables[$index];
            }
        }

        return null;
    };

    $partnerTable = $pick('partner', 'partners');
    $campusTable = $pick('campus', 'campuses');

    if ($partnerTable === null) {
        return null;
    }

    $campusSelect = $campusTable !== null ? 'c.Name AS campus_name' : "'' AS campus_name";
    $campusJoin = $campusTable !== null
        ? "LEFT JOIN `{$campusTable}` c ON COALESCE(a.Campus_ID, p.Campus_ID) = c.Campus_ID"
        : '';

    $sql = "SELECT
                a.Agree_ID AS id,
                a.Agreement_Type AS title,
                a.Partnership_Type AS partnership_type,
                a.Scope_Description AS scope,
                p.Name AS partner_name,
                a.Signed_Date AS signed_date,
                a.Expiry_Date AS expiry_date,
                a.Status AS status,
                a.access_token,
                {$campusSelect}
            FROM `{$agreementTable}` a
            INNER JOIN `{$partnerTable}` p ON a.Partner_ID = p.Partner_ID
            {$campusJoin}
            WHERE a.access_token = :token
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['token' => $token]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

/**
 * @return array<string, mixed>|null
 */
function fetchAgreementNotificationContext(PDO $pdo, int $agreeId): ?array
{
    $agreementTable = agreementNotifyTableName($pdo);
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $lower = array_map('strtolower', $tables);

    $pick = static function (string $singular, string $plural) use ($tables, $lower): ?string {
        foreach ([$singular, $plural] as $name) {
            $index = array_search(strtolower($name), $lower, true);

            if ($index !== false) {
                return (string) $tables[$index];
            }
        }

        return null;
    };

    $partnerTable = $pick('partner', 'partners');
    $contactTable = $pick('contact', 'contacts');
    $usersTable = $pick('user', 'users');

    if ($partnerTable === null) {
        return null;
    }

    $contactJoin = '';
    $contactSelect = 'NULL AS partner_email';

    if ($contactTable !== null) {
        $contactJoin = "LEFT JOIN (
                            SELECT Partner_ID, MIN(Contact_ID) AS Contact_ID
                            FROM `{$contactTable}`
                            GROUP BY Partner_ID
                        ) first_contact ON p.Partner_ID = first_contact.Partner_ID
                        LEFT JOIN `{$contactTable}` ct ON ct.Contact_ID = first_contact.Contact_ID";
        $contactSelect = 'ct.Email AS partner_email';
    }

    $directorJoin = '';
    $directorSelect = 'NULL AS director_email';

    if ($usersTable !== null) {
        $directorJoin = "LEFT JOIN `{$usersTable}` reviewed_director ON reviewed_director.User_ID = a.Reviewed_By
                        LEFT JOIN `{$usersTable}` submitted_director ON submitted_director.User_ID = a.Submitted_By
                        LEFT JOIN (
                            SELECT Email
                            FROM `{$usersTable}`
                            WHERE Role IN ('Partnership Director', 'partnership_director')
                            ORDER BY User_ID ASC
                            LIMIT 1
                        ) office_director ON 1 = 1";
        $directorSelect = 'COALESCE(reviewed_director.Email, submitted_director.Email, office_director.Email) AS director_email';
    }

    $tokenSelect = 'NULL AS access_token';

    try {
        ensureAgreementAccessTokenColumn($pdo);
        $tokenSelect = 'a.access_token';
    } catch (Throwable $ignored) {
        // Keep a null token if the column cannot be ensured.
    }

    $sql = "SELECT
                a.Agree_ID AS id,
                a.Agreement_Type AS title,
                a.Partnership_Type AS partnership_type,
                a.Scope_Description AS scope,
                p.Name AS partner_name,
                a.Signed_Date AS signed_date,
                a.Expiry_Date AS expiry_date,
                a.Status AS status,
                {$tokenSelect},
                {$contactSelect},
                {$directorSelect}
            FROM `{$agreementTable}` a
            INNER JOIN `{$partnerTable}` p ON a.Partner_ID = p.Partner_ID
            {$contactJoin}
            {$directorJoin}
            WHERE a.Agree_ID = :id
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $agreeId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function formatAgreementNotifyDate(?string $date): string
{
    if ($date === null || $date === '') {
        return '—';
    }

    $timestamp = strtotime($date);

    return $timestamp === false ? $date : date('F j, Y', $timestamp);
}

function agreementNotifyEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * @param array<string, mixed> $agreement
 */
function buildNewAgreementRegisteredHtml(array $agreement, string $viewUrl): string
{
    $title = agreementNotifyEscape(trim((string) ($agreement['title'] ?? 'Partnership Agreement')));
    $partner = agreementNotifyEscape((string) ($agreement['partner_name'] ?? '—'));
    $signed = agreementNotifyEscape(formatAgreementNotifyDate((string) ($agreement['signed_date'] ?? '')));
    $expiry = agreementNotifyEscape(formatAgreementNotifyDate((string) ($agreement['expiry_date'] ?? '')));
    $status = agreementNotifyEscape((string) ($agreement['status'] ?? 'Active'));
    $safeUrl = agreementNotifyEscape($viewUrl);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Partnership Agreement Registered</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #d1d5db;">
                    <tr>
                        <td style="background:#064e3b;padding:22px 28px;color:#ffffff;">
                            <p style="margin:0 0 6px;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#a7f3d0;">Divine Word University</p>
                            <h1 style="margin:0;font-size:22px;line-height:1.3;">Partnership Registry</h1>
                            <p style="margin:8px 0 0;font-size:13px;color:#d1fae5;">New agreement confirmation</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <h2 style="margin:0 0 12px;font-size:18px;color:#064e3b;">New Partnership Agreement Registered</h2>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.55;color:#0f172a;">
                                A partnership agreement has been registered in the DWU Partnership Database Management Information System.
                            </p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #d1fae5;border-radius:8px;overflow:hidden;">
                                <tr>
                                    <td style="background:#ecfdf5;padding:10px 12px;width:42%;font-size:13px;font-weight:bold;color:#064e3b;">Agreement title</td>
                                    <td style="padding:10px 12px;font-size:14px;color:#0f172a;">{$title}</td>
                                </tr>
                                <tr>
                                    <td style="background:#ecfdf5;padding:10px 12px;font-size:13px;font-weight:bold;color:#064e3b;">Partner</td>
                                    <td style="padding:10px 12px;font-size:14px;color:#0f172a;">{$partner}</td>
                                </tr>
                                <tr>
                                    <td style="background:#ecfdf5;padding:10px 12px;font-size:13px;font-weight:bold;color:#064e3b;">Signed date</td>
                                    <td style="padding:10px 12px;font-size:14px;color:#0f172a;">{$signed}</td>
                                </tr>
                                <tr>
                                    <td style="background:#ecfdf5;padding:10px 12px;font-size:13px;font-weight:bold;color:#064e3b;">Expiry date</td>
                                    <td style="padding:10px 12px;font-size:14px;color:#0f172a;">{$expiry}</td>
                                </tr>
                                <tr>
                                    <td style="background:#ecfdf5;padding:10px 12px;font-size:13px;font-weight:bold;color:#064e3b;">Status</td>
                                    <td style="padding:10px 12px;font-size:14px;color:#0f172a;">{$status}</td>
                                </tr>
                            </table>
                            <p style="margin:22px 0 0;text-align:center;">
                                <a href="{$safeUrl}" style="display:inline-block;background:#006633;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-size:14px;font-weight:bold;">
                                    View Partnership Agreement
                                </a>
                            </p>
                            <p style="margin:16px 0 0;font-size:12px;line-height:1.5;color:#64748b;word-break:break-all;">
                                Secure access link: {$safeUrl}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f8fafc;padding:16px 28px;font-size:12px;color:#64748b;border-top:1px solid #e2e8f0;">
                            This message was sent automatically by the DWU Partnership Registry. Please do not reply to this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

/**
 * @return array{ok: bool, sent: int, recipients: list<string>, errors: list<string>}
 */
function sendNewAgreementRegisteredEmail(
    PDO $pdo,
    int $agreeId,
    ?string $partnerEmail = null,
    ?string $directorEmail = null
): array {
    $result = [
        'ok'         => false,
        'sent'       => 0,
        'recipients' => [],
        'errors'     => [],
    ];

    $agreement = fetchAgreementNotificationContext($pdo, $agreeId);

    if ($agreement === null) {
        $result['errors'][] = 'Agreement #' . $agreeId . ' was not found for notification.';

        return $result;
    }

    $token = assignAgreementAccessToken($pdo, $agreeId, (string) ($agreement['access_token'] ?? ''));
    $agreement['access_token'] = $token;
    $viewUrl = agreementPublicViewUrl($token);

    $partnerEmail = trim((string) ($partnerEmail ?: ($agreement['partner_email'] ?? '')));
    $directorEmail = trim((string) ($directorEmail ?: ($agreement['director_email'] ?? '')));

    if ($directorEmail === '') {
        $directorEmail = fetchOfficeDirectorEmail($pdo);
    }

    $mail = createPdmisPhpMailer();
    $recipients = [];

    if (filter_var($partnerEmail, FILTER_VALIDATE_EMAIL)) {
        $mail->addAddress($partnerEmail);
        $recipients[] = $partnerEmail;
    }

    if (filter_var($directorEmail, FILTER_VALIDATE_EMAIL) && strcasecmp($directorEmail, $partnerEmail) !== 0) {
        $mail->addAddress($directorEmail);
        $recipients[] = $directorEmail;
    }

    if ($recipients === []) {
        $result['errors'][] = 'No valid partner_email or director_email for agreement #' . $agreeId;

        return $result;
    }

    $mail->Subject = 'New Partnership Agreement Registered - Divine Word University';
    $mail->Body    = buildNewAgreementRegisteredHtml($agreement, $viewUrl);
    $mail->AltBody = trim(html_entity_decode(strip_tags($mail->Body), ENT_QUOTES, 'UTF-8'));

    try {
        $mail->send();
        $result['ok'] = true;
        $result['sent'] = 1;
        $result['recipients'] = $recipients;
    } catch (PhpMailerException $exception) {
        $error = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $exception->getMessage();
        $result['errors'][] = $error;
        error_log('New agreement email failed: ' . $error);
    } catch (Throwable $exception) {
        $error = (isset($mail->ErrorInfo) && $mail->ErrorInfo !== '')
            ? $mail->ErrorInfo
            : $exception->getMessage();
        $result['errors'][] = $error;
        error_log('New agreement email failed: ' . $error);
    }

    return $result;
}
