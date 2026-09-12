<?php

declare(strict_types=1);

/**
 * Daily expiry notifier — Active / Expiring Soon agreements within 30 days.
 *
 * Cron example:
 *   0 7 * * * php C:/xampp/htdocs/IS406_PartnershipRegistry/includes/check_expiring_agreements.php
 *
 * On this XAMPP install the director dashboard also runs the check about once an hour
 * so emails still go out when Windows Task Scheduler is not configured.
 */

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/agreement_notify.php';

const EXPIRY_NOTIFY_FROM_ADDRESS = 'gabrielleluckie20@gmail.com';
const EXPIRY_NOTIFY_FROM_NAME = 'DWU Partnership Office';
const EXPIRY_NOTIFY_SMTP_HOST = 'smtp.gmail.com';
const EXPIRY_NOTIFY_SMTP_USER = 'gabrielleluckie20@gmail.com';
const EXPIRY_NOTIFY_SMTP_PASSWORD = 'dsov lmqw prze zebv';
const EXPIRY_NOTIFY_SMTP_PORT = 587;
const EXPIRY_NOTIFY_SMTP_SECURE = 'tls';
const EXPIRY_NOTIFY_DAYS = 30;

/**
 * Load PHPMailer from Composer, then fall back to a manual includes/PHPMailer/ copy.
 */
function requireExpiryPhpMailer(): void
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

function expiryNotifyPrintEnabled(): bool
{
    if (defined('EXPIRY_NOTIFY_PRINT_RESULTS')) {
        return (bool) EXPIRY_NOTIFY_PRINT_RESULTS;
    }

    return PHP_SAPI !== 'cli';
}

function expiryNotifyPrint(string $message, string $level = 'info'): void
{
    if (!expiryNotifyPrintEnabled()) {
        return;
    }

    if (PHP_SAPI === 'cli') {
        fwrite($level === 'error' ? STDERR : STDOUT, $message . PHP_EOL);

        return;
    }

    $class = match ($level) {
        'success' => 'ok',
        'error'   => 'err',
        'warn'    => 'warn',
        default   => 'info',
    };

    echo '<p class="expiry-msg expiry-msg-' . $class . '">'
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        . '</p>' . PHP_EOL;
}

function expiryNotifyLogError(string $message): void
{
    error_log('Expiry notifier: ' . $message);
    expiryNotifyPrint($message, 'error');
}

/**
 * @return array{
 *     ok: bool,
 *     message: string,
 *     target_date: string,
 *     found: int,
 *     emails_attempted: int,
 *     emails_sent: int,
 *     via: string,
 *     agreements: list<array<string, mixed>>,
 *     errors: list<string>
 * }
 */
function runExpiringAgreementCheck(PDO $pdo): array
{
    $targetDate = (new DateTimeImmutable('today'))->modify('+' . EXPIRY_NOTIFY_DAYS . ' days')->format('Y-m-d');

    try {
        $mysqlTarget = $pdo->query('SELECT DATE_ADD(CURDATE(), INTERVAL 30 DAY) AS target_date')->fetch();
        if (is_array($mysqlTarget) && !empty($mysqlTarget['target_date'])) {
            $targetDate = (string) $mysqlTarget['target_date'];
        }
    } catch (Throwable $ignored) {
        // Keep the PHP-calculated fallback.
    }

    $result = [
        'ok'               => true,
        'message'          => '',
        'target_date'      => $targetDate,
        'found'            => 0,
        'emails_attempted' => 0,
        'emails_sent'      => 0,
        'via'              => 'smtp',
        'agreements'       => [],
        'errors'           => [],
    ];

    try {
        $agreements = fetchAgreementsExpiringInThirtyDays($pdo);
    } catch (Throwable $exception) {
        $result['ok'] = false;
        $result['message'] = 'Expiry check failed: ' . $exception->getMessage();
        $result['errors'][] = $exception->getMessage();
        expiryNotifyLogError($result['message']);

        return $result;
    }

    $result['agreements'] = $agreements;
    $result['found'] = count($agreements);

    if ($agreements === []) {
        $result['message'] = 'No agreements expiring within 30 days.';
        expiryNotifyPrint($result['message'], 'info');

        return $result;
    }

    try {
        $mail = createExpiryPhpMailer();
        configureExpiryPhpMailer($mail);
    } catch (Throwable $exception) {
        $result['ok'] = false;
        $result['message'] = 'PHPMailer setup failed: ' . $exception->getMessage();
        $result['errors'][] = $exception->getMessage();
        expiryNotifyLogError($result['message']);

        return $result;
    }

    foreach ($agreements as $agreement) {
        $title = agreementNotificationTitle($agreement);
        $partnerEmail = trim((string) ($agreement['partner_email'] ?? ''));
        $directorEmail = resolveDirectorNotifyEmail((string) ($agreement['director_email'] ?? ''));

        $recipients = [];

        if (isDeliverableNotifyEmail($partnerEmail)) {
            $recipients[] = $partnerEmail;
        }

        if ($directorEmail !== '' && strcasecmp($directorEmail, $partnerEmail) !== 0) {
            $recipients[] = $directorEmail;
        }

        if ($recipients === []) {
            $error = 'No valid partner_email or director_email for agreement: ' . $title;
            $result['errors'][] = $error;
            expiryNotifyLogError($error);
            continue;
        }

        $agreeId = (int) ($agreement['id'] ?? 0);
        $accessToken = $agreeId > 0
            ? assignAgreementAccessToken($pdo, $agreeId, (string) ($agreement['access_token'] ?? ''))
            : trim((string) ($agreement['access_token'] ?? ''));
        $viewUrl = isValidAgreementAccessToken($accessToken)
            ? agreementPublicViewUrl($accessToken)
            : '';

        $daysRemaining = (int) ($agreement['days_remaining'] ?? EXPIRY_NOTIFY_DAYS);
        $daysLabel = $daysRemaining === 1 ? '1 day' : $daysRemaining . ' days';
        $mail->Subject = 'URGENT: Partnership Agreement Expiring in ' . $daysLabel;
        $mail->Body    = buildExpiryNoticeHtml(
            $agreement,
            (string) ($agreement['expiry_date'] ?? $targetDate),
            $viewUrl,
            $daysRemaining
        );
        $mail->AltBody = trim(html_entity_decode(strip_tags($mail->Body), ENT_QUOTES, 'UTF-8'));

        $result['emails_attempted'] += count($recipients);
        $delivery = sendPdmisMailToRecipients($mail, $recipients);
        $result['emails_sent'] += $delivery['sent'];

        if ($delivery['sent'] > 0) {
            markExpiryAlertSent($pdo, $agreeId);
            expiryNotifyPrint(
                'SUCCESS: Email sent for agreement: ' . $title . ' (' . implode(', ', $delivery['recipients']) . ')',
                'success'
            );
        }

        foreach ($delivery['errors'] as $error) {
            $result['errors'][] = $error;
            expiryNotifyLogError('Email failed for agreement "' . $title . '": ' . $error);
        }
    }

    $result['ok'] = $result['emails_sent'] > 0 && $result['errors'] === [];
    $result['message'] = $result['ok']
        ? sprintf(
            'Sent %d of %d expiry notification(s) for %d agreement(s) expiring within 30 days (on or before %s).',
            $result['emails_sent'],
            $result['emails_attempted'],
            $result['found'],
            $targetDate
        )
        : sprintf(
            'Expiry notifications incomplete. Sent %d of %d email(s) for %d agreement(s).',
            $result['emails_sent'],
            $result['emails_attempted'],
            $result['found']
        );

    if (!$result['ok'] && $result['errors'] !== []) {
        expiryNotifyLogError($result['message']);
    }

    return $result;
}

/**
 * @return list<array<string, mixed>>
 */
function fetchAgreementsExpiringInThirtyDays(PDO $pdo): array
{
    $tables = expiryNotificationTableNames($pdo);
    $agreementTable = $tables['agreement'];
    $partnerTable = $tables['partner'];
    $contactTable = $tables['contact'];
    $usersTable = $tables['users'];

    if ($agreementTable === null || $partnerTable === null) {
        throw new RuntimeException('Agreement or partner table was not found.');
    }

    ensureAgreementAccessTokenColumn($pdo);
    ensureAgreementEntryColumns($pdo);

    $contactJoin = '';
    $partnerEmailSelect = 'a.Partner_Email AS partner_email';

    if ($contactTable !== null) {
        $contactJoin = "LEFT JOIN (
                            SELECT Partner_ID, MIN(Contact_ID) AS Contact_ID
                            FROM `{$contactTable}`
                            GROUP BY Partner_ID
                        ) first_contact ON p.Partner_ID = first_contact.Partner_ID
                        LEFT JOIN `{$contactTable}` ct ON ct.Contact_ID = first_contact.Contact_ID";
        $partnerEmailSelect = 'COALESCE(NULLIF(a.Partner_Email, \'\'), ct.Email) AS partner_email';
    }

    $directorJoin = '';
    $directorSelect = 'a.Director_Email AS director_email';

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
        $directorSelect = 'COALESCE(NULLIF(a.Director_Email, \'\'), reviewed_director.Email, submitted_director.Email, office_director.Email) AS director_email';
    }

    $alertFilter = expiryAlertUnsentSql($pdo, $agreementTable);

    $sql = "SELECT
                a.Agree_ID AS id,
                COALESCE(NULLIF(a.Agreement_Title, ''), a.Agreement_Type) AS title,
                p.Name AS partner_name,
                {$partnerEmailSelect},
                {$directorSelect},
                a.Expiry_Date AS expiry_date,
                DATEDIFF(a.Expiry_Date, CURDATE()) AS days_remaining,
                a.Status AS status,
                a.access_token
            FROM `{$agreementTable}` a
            INNER JOIN `{$partnerTable}` p ON a.Partner_ID = p.Partner_ID
            {$contactJoin}
            {$directorJoin}
            WHERE a.Expiry_Date IS NOT NULL
              AND DATE(a.Expiry_Date) BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                                          AND DATE_ADD(CURDATE(), INTERVAL " . EXPIRY_NOTIFY_DAYS . " DAY)
              AND a.Status IN ('Active', 'Expiring Soon')
              {$alertFilter}
            ORDER BY a.Expiry_Date ASC, p.Name ASC, a.Agree_ID ASC";

    return $pdo->query($sql)->fetchAll();
}

/**
 * @return array{agreement: ?string, partner: ?string, contact: ?string, users: ?string}
 */
function expiryNotificationTableNames(PDO $pdo): array
{
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $lower = array_map('strtolower', $tables);

    $pick = static function (string $singular, string $plural) use ($tables, $lower): ?string {
        foreach ([$singular, $plural] as $name) {
            $index = array_search(strtolower($name), $lower, true);

            if ($index !== false) {
                return $tables[$index];
            }
        }

        return null;
    };

    return [
        'agreement' => $pick('agreement', 'agreements'),
        'partner'   => $pick('partner', 'partners'),
        'contact'   => $pick('contact', 'contacts'),
        'users'     => $pick('user', 'users'),
    ];
}

function expiryAlertUnsentSql(PDO $pdo, string $agreementTable): string
{
    $columns = $pdo->query("SHOW COLUMNS FROM `{$agreementTable}`")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('Expiry_Alert_Sent_At', $columns, true)) {
        return '';
    }

    return 'AND a.Expiry_Alert_Sent_At IS NULL';
}

function markExpiryAlertSent(PDO $pdo, int $agreeId): void
{
    if ($agreeId <= 0) {
        return;
    }

    $tables = expiryNotificationTableNames($pdo);
    $table = $tables['agreement'];

    if ($table === null) {
        return;
    }

    $columns = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
    $sets = ["Status = 'Expiring Soon'"];

    if (in_array('Expiry_Alert_Sent_At', $columns, true)) {
        $sets[] = 'Expiry_Alert_Sent_At = NOW()';
    }

    $pdo->prepare(
        'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE Agree_ID = :id'
    )->execute(['id' => $agreeId]);
}

function maybeDispatchExpiringAgreementNotices(PDO $pdo): void
{
    static $ran = false;

    if ($ran) {
        return;
    }

    $ran = true;

    $lockDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    $lockFile = $lockDir . DIRECTORY_SEPARATOR . 'expiry_notify_last_run.txt';

    if (!is_dir($lockDir)) {
        @mkdir($lockDir, 0775, true);
    }

    $now = time();

    if (is_file($lockFile)) {
        $last = (int) trim((string) @file_get_contents($lockFile));

        if ($last > 0 && ($now - $last) < 3600) {
            return;
        }
    }

    if (!defined('EXPIRY_NOTIFY_PRINT_RESULTS')) {
        define('EXPIRY_NOTIFY_PRINT_RESULTS', false);
    }

    try {
        runExpiringAgreementCheck($pdo);
        @file_put_contents($lockFile, (string) $now);
    } catch (Throwable $exception) {
        expiryNotifyLogError($exception->getMessage());
    }
}

function expiryNotifyEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatExpiryNotifyDate(?string $date): string
{
    if ($date === null || $date === '') {
        return '—';
    }

    $timestamp = strtotime($date);

    return $timestamp === false ? $date : date('F j, Y', $timestamp);
}

function agreementNotificationTitle(array $agreement): string
{
    $title = trim((string) ($agreement['title'] ?? ''));

    if ($title !== '') {
        return $title;
    }

    $id = (int) ($agreement['id'] ?? 0);

    return $id > 0 ? 'Partnership Agreement #' . $id : 'Partnership Agreement';
}

function buildExpiryNoticeHtml(array $agreement, string $targetDate, string $viewUrl = '', int $daysRemaining = EXPIRY_NOTIFY_DAYS): string
{
    $title = expiryNotifyEscape(agreementNotificationTitle($agreement));
    $partner = expiryNotifyEscape((string) ($agreement['partner_name'] ?? '—'));
    $expiry = expiryNotifyEscape(formatExpiryNotifyDate((string) ($agreement['expiry_date'] ?? $targetDate)));
    $safeUrl = expiryNotifyEscape($viewUrl);
    $daysRemaining = max(1, $daysRemaining);
    $daysLabel = $daysRemaining === 1 ? '1 day' : $daysRemaining . ' days';
    $daysLabelEscaped = expiryNotifyEscape($daysLabel);
    $accessButton = $viewUrl !== ''
        ? <<<HTML
                            <p style="margin:22px 0 0;text-align:center;">
                                <a href="{$safeUrl}" style="display:inline-block;background:#006633;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-size:14px;font-weight:bold;">
                                    View Partnership Agreement
                                </a>
                            </p>
                            <p style="margin:16px 0 0;font-size:12px;line-height:1.5;color:#64748b;">
                                Open this link on the computer running XAMPP (Apache must be started).
                            </p>
                            <p style="margin:10px 0 0;font-size:12px;line-height:1.5;color:#64748b;word-break:break-all;">
                                Secure access link: {$safeUrl}
                            </p>
HTML
        : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partnership Agreement Expiring in 30 Days</title>
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
                            <p style="margin:8px 0 0;font-size:13px;color:#d1fae5;">Automated expiry notification</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <h2 style="margin:0 0 12px;font-size:18px;color:#064e3b;">URGENT: Partnership Agreement Expiring in {$daysLabelEscaped}</h2>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.55;color:#0f172a;">
                                This is a notice that a partnership agreement is expiring in {$daysLabelEscaped}.
                            </p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #d1fae5;border-radius:8px;overflow:hidden;">
                                <tr>
                                    <td style="background:#ecfdf5;padding:10px 12px;width:42%;font-size:13px;font-weight:bold;color:#064e3b;">Title</td>
                                    <td style="padding:10px 12px;font-size:14px;color:#0f172a;">{$title}</td>
                                </tr>
                                <tr>
                                    <td style="background:#ecfdf5;padding:10px 12px;font-size:13px;font-weight:bold;color:#064e3b;">Partner name</td>
                                    <td style="padding:10px 12px;font-size:14px;color:#0f172a;">{$partner}</td>
                                </tr>
                                <tr>
                                    <td style="background:#ecfdf5;padding:10px 12px;font-size:13px;font-weight:bold;color:#064e3b;">Expiration date</td>
                                    <td style="padding:10px 12px;font-size:14px;color:#0f172a;">{$expiry}</td>
                                </tr>
                                <tr>
                                    <td style="background:#ecfdf5;padding:10px 12px;width:42%;font-size:13px;font-weight:bold;color:#064e3b;">Days remaining</td>
                                    <td style="padding:10px 12px;font-size:14px;color:#0f172a;">{$daysLabelEscaped}</td>
                                </tr>
                            </table>
                            {$accessButton}
                            <p style="margin:16px 0 0;font-size:13px;line-height:1.5;color:#475569;">
                                Please contact the DWU Partnership Division if you wish to discuss renewal.
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

function createExpiryPhpMailer(): object
{
    requireExpiryPhpMailer();

    if (class_exists(PHPMailer::class)) {
        return new PHPMailer(true);
    }

    if (class_exists('PHPMailer')) {
        return new \PHPMailer(true);
    }

    throw new RuntimeException('PHPMailer class is not available after loading.');
}

function configureExpiryPhpMailer(object $mail): void
{
    $mail->isSMTP();
    $mail->Host       = EXPIRY_NOTIFY_SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = EXPIRY_NOTIFY_SMTP_USER;
    $mail->Password   = EXPIRY_NOTIFY_SMTP_PASSWORD;
    $mail->SMTPSecure = class_exists(PHPMailer::class)
        ? PHPMailer::ENCRYPTION_STARTTLS
        : EXPIRY_NOTIFY_SMTP_SECURE;
    $mail->Port       = EXPIRY_NOTIFY_SMTP_PORT;
    $mail->setFrom(EXPIRY_NOTIFY_FROM_ADDRESS, EXPIRY_NOTIFY_FROM_NAME);
    $mail->isHTML(true);
}

if (!defined('EXPIRY_NOTIFY_SKIP_AUTORUN')) {
    try {
        $expiryNotificationResult = runExpiringAgreementCheck($pdo);
    } catch (Throwable $e) {
        $expiryNotificationResult = [
            'ok'               => false,
            'message'          => $e->getMessage(),
            'target_date'      => '',
            'found'            => 0,
            'emails_attempted' => 0,
            'emails_sent'      => 0,
            'via'              => '',
            'agreements'       => [],
            'errors'           => [$e->getMessage()],
        ];
        expiryNotifyLogError($e->getMessage());
    }
}

$runningThisFile = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);

if (PHP_SAPI === 'cli' && $runningThisFile && isset($expiryNotificationResult) && is_array($expiryNotificationResult)) {
    fwrite(
        $expiryNotificationResult['ok'] ? STDOUT : STDERR,
        $expiryNotificationResult['message'] . PHP_EOL
    );
    exit($expiryNotificationResult['ok'] ? 0 : 1);
}
