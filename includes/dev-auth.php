<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

/**
 * Seeded dev accounts exposed on the login page when APP_ENV=local.
 *
 * @return list<array{email: string, label: string, role: string}>
 */
function devQuickLoginAccounts(): array
{
    return [
        [
            'email' => 'admin.madang@dwu.ac.pg',
            'label' => 'Madang Campus Admin',
            'role'  => ROLE_CAMPUS_ADMIN,
        ],
        [
            'email' => 'admin.pom@dwu.ac.pg',
            'label' => 'Port Moresby Campus Admin',
            'role'  => ROLE_CAMPUS_ADMIN,
        ],
        [
            'email' => 'admin.wewak@dwu.ac.pg',
            'label' => 'Wewak Campus Admin',
            'role'  => ROLE_CAMPUS_ADMIN,
        ],
        [
            'email' => '230172student@dwu.ac.pg',
            'label' => 'Partnership Director',
            'role'  => ROLE_PARTNERSHIP_DIRECTOR,
        ],
        [
            'email' => 'exec.officer@dwu.ac.pg',
            'label' => 'Executive Officer',
            'role'  => ROLE_EXECUTIVE_OFFICER,
        ],
        [
            'email' => 'president@dwu.ac.pg',
            'label' => 'President',
            'role'  => ROLE_PRESIDENT,
        ],
    ];
}

function isDevAuthEnabled(): bool
{
    return isLocalEnvironment();
}

function isDevQuickLoginEmail(string $email): bool
{
    $email = strtolower(trim($email));

    foreach (devQuickLoginAccounts() as $account) {
        if (strtolower($account['email']) === $email) {
            return true;
        }
    }

    return false;
}

/**
 * Log in instantly as a seeded dev user (local only).
 */
function attemptDevQuickLogin(PDO $pdo, string $email): bool
{
    if (!isDevAuthEnabled()) {
        return false;
    }

    $email = strtolower(trim($email));

    if (!isDevQuickLoginEmail($email)) {
        return false;
    }

    $user = fetchUserByEmail($pdo, $email);

    if ($user === null) {
        return false;
    }

    loginUser($user);

    return true;
}
