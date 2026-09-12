<?php

declare(strict_types=1);

use App\Models\Agreement;

require_once __DIR__ . '/agreement_notify.php';

/**
 * Resolve registry table names (supports singular live schema and legacy plural names).
 *
 * @return array{agreement: ?string, partner: ?string, contact: ?string, campus: string, agreement_history: ?string}
 */
function registryTableNames(PDO $pdo): array
{
    static $names = null;

    if ($names !== null) {
        return $names;
    }

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $lower = array_map('strtolower', $tables);

    $resolve = static function (string $singular, string $plural) use ($tables, $lower): ?string {
        $singularKey = array_search(strtolower($singular), $lower, true);
        if ($singularKey !== false) {
            return $tables[$singularKey];
        }

        $pluralKey = array_search(strtolower($plural), $lower, true);

        return $pluralKey !== false ? $tables[$pluralKey] : null;
    };

    $names = [
        'agreement'         => $resolve('agreement', 'agreements'),
        'partner'           => $resolve('partner', 'partners'),
        'contact'           => $resolve('contact', 'contacts'),
        'campus'            => campusTableName($pdo),
        'agreement_history' => $resolve('agreement_history', 'agreement_history'),
    ];

    return $names;
}

function agreementTableName(PDO $pdo): ?string
{
    return registryTableNames($pdo)['agreement'];
}

function partnerTableName(PDO $pdo): ?string
{
    return registryTableNames($pdo)['partner'];
}

function contactTableName(PDO $pdo): ?string
{
    return registryTableNames($pdo)['contact'];
}

function partnerHasSoftDelete(PDO $pdo): bool
{
    static $has = null;

    if ($has !== null) {
        return $has;
    }

    $table = partnerTableName($pdo);

    if ($table === null) {
        $has = false;

        return $has;
    }

    $columns = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
    $has = in_array('Is_Deleted', $columns, true);

    return $has;
}

function badgeClass(string $status): string
{
    return match ($status) {
        Agreement::STATUS_ACTIVE         => 'active',
        Agreement::STATUS_EXPIRED        => 'expired',
        Agreement::STATUS_EXPIRING_SOON => 'soon',
        'Soon to Expire'                 => 'soon',
        default                          => 'soon',
    };
}

function computeAgreementStatus(string $expiryDate): string
{
    return Agreement::calculatedStatusFromExpiry($expiryDate);
}

function resolveAgreementDisplayStatus(?string $expiryDate, ?string $dbStatus = null): string
{
    if ($expiryDate !== null && $expiryDate !== '') {
        return Agreement::calculatedStatusFromExpiry($expiryDate);
    }

    return match ($dbStatus) {
        'Expired'        => Agreement::STATUS_EXPIRED,
        'Expiring Soon'  => Agreement::STATUS_EXPIRING_SOON,
        'Active'         => Agreement::STATUS_ACTIVE,
        default          => Agreement::STATUS_ACTIVE,
    };
}

function registryAgreementStatusFilterSql(string $alias = ''): string
{
    $quoted = implode(', ', array_map(
        static fn(string $status): string => "'" . str_replace("'", "''", $status) . "'",
        Agreement::registryLifecycleStatuses()
    ));

    $column = $alias !== '' ? "`{$alias}`.Status" : 'Status';

    return "{$column} IN ({$quoted})";
}

function enrichAgreementRow(array $row): array
{
    $model = Agreement::fromRow($row);

    return array_merge($row, $model->toListingArray());
}

function assignedCampusFormLabel(array $user): string
{
    $campus = trim((string) ($user['campus'] ?? ''));
    $map = [
        'Madang' => 'Madang (Main)',
        'Rabaul' => 'Rabaul',
        'Wewak' => 'Sepik (Wewak)',
        'Sepik' => 'Sepik (Wewak)',
        'Port Moresby' => 'Port Moresby',
        'POM' => 'Port Moresby',
        'Hagen' => 'Mt. Hagen',
        'Mt. Hagen' => 'Mt. Hagen',
    ];

    foreach ($map as $needle => $value) {
        if ($campus !== '' && stripos($campus, $needle) !== false) {
            return $value;
        }
    }

    return $campus;
}

/** @param array<string, mixed> $formData */
function lockCampusAdminFormCampus(array &$formData, array $user): void
{
    $campus = assignedCampusFormLabel($user);
    if ($campus === '') {
        return;
    }

    $formData['campus'] = $campus;
    $formData['submitter_campus'] = $campus;
}

function campusTableName(PDO $pdo): string
{
    static $name = null;

    if ($name !== null) {
        return $name;
    }

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $name = in_array('campus', $tables, true) ? 'campus' : 'campuses';

    return $name;
}

function fetchCampuses(PDO $pdo): array
{
    $table = campusTableName($pdo);
    $stmt = $pdo->query("SELECT Campus_ID, Name, Province FROM `{$table}` ORDER BY Name ASC");

    return $stmt->fetchAll();
}

function fetchPartners(PDO $pdo): array
{
    ensureAgreementEntryColumns($pdo);

    $partnerTable = partnerTableName($pdo);
    $campusTable = campusTableName($pdo);

    if ($partnerTable === null) {
        return [];
    }

    $deletedFilter = partnerHasSoftDelete($pdo) ? ' WHERE p.Is_Deleted = 0' : '';

    $contactTable = contactTableName($pdo);
    $contactSelect = 'NULL AS contact_email';
    $contactJoin = '';

    if ($contactTable !== null) {
        $contactJoin = "LEFT JOIN (
                            SELECT Partner_ID, MIN(Contact_ID) AS Contact_ID
                            FROM `{$contactTable}`
                            GROUP BY Partner_ID
                        ) first_contact ON p.Partner_ID = first_contact.Partner_ID
                        LEFT JOIN `{$contactTable}` ct ON ct.Contact_ID = first_contact.Contact_ID";
        $contactSelect = 'ct.Email AS contact_email';
    }

    $sql = "SELECT p.Partner_ID, p.Name, p.Country, p.Website, p.Address,
                   p.Mailing_Address, c.Name AS campus_name, c.Campus_ID, {$contactSelect}
            FROM `{$partnerTable}` p
            INNER JOIN `{$campusTable}` c ON p.Campus_ID = c.Campus_ID
            {$contactJoin}{$deletedFilter}
            ORDER BY p.Name ASC";

    return $pdo->query($sql)->fetchAll();
}

function fetchAgreements(PDO $pdo): array
{
    $tables = registryTableNames($pdo);
    $agreementTable = $tables['agreement'];
    $partnerTable = $tables['partner'];
    $contactTable = $tables['contact'];
    $campusTable = $tables['campus'];

    if ($agreementTable === null || $partnerTable === null) {
        return [];
    }

    $contactJoin = '';
    $contactSelect = "'N/A' AS contact";

    if ($contactTable !== null) {
        $contactJoin = "LEFT JOIN (
                            SELECT Partner_ID, MIN(Contact_ID) AS Contact_ID
                            FROM `{$contactTable}`
                            GROUP BY Partner_ID
                        ) first_contact ON p.Partner_ID = first_contact.Partner_ID
                        LEFT JOIN `{$contactTable}` ct ON ct.Contact_ID = first_contact.Contact_ID";
        $contactSelect = 'COALESCE(ct.Name, \'N/A\') AS contact';
    }

    $sql = "SELECT
                a.Agree_ID AS id,
                p.Name AS partner,
                a.Agreement_Type AS type,
                a.Partnership_Type AS Partnership_type,
                c.Name AS campus,
                c.Campus_ID,
                a.Status AS status,
                a.Expiry_Date AS expiry,
                a.Signed_Date AS signed_date,
                {$contactSelect}
            FROM `{$agreementTable}` a
            INNER JOIN `{$partnerTable}` p ON a.Partner_ID = p.Partner_ID
            INNER JOIN `{$campusTable}` c ON COALESCE(a.Campus_ID, p.Campus_ID) = c.Campus_ID
            {$contactJoin}
            WHERE " . registryAgreementStatusFilterSql('a') . "
            ORDER BY a.Agree_ID ASC";

    $rows = $pdo->query($sql)->fetchAll();

    foreach ($rows as &$row) {
        $row = enrichAgreementRow($row);
    }
    unset($row);

    return $rows;
}

function fetchAgreementCounts(PDO $pdo): array
{
    $counts = [
        'Active'         => 0,
        'Expiring Soon'  => 0,
        'Expired'        => 0,
        'Total'          => 0,
    ];

    $agreementTable = agreementTableName($pdo);

    if ($agreementTable === null) {
        return $counts;
    }

    $registryFilter = registryAgreementStatusFilterSql();
    $stmt = $pdo->query("SELECT Expiry_Date, Status FROM `{$agreementTable}` WHERE {$registryFilter}");

    while ($row = $stmt->fetch()) {
        $counts['Total']++;
        $status = resolveAgreementDisplayStatus($row['Expiry_Date'] ?? null, $row['Status'] ?? null);

        if (isset($counts[$status])) {
            $counts[$status]++;
        }
    }

    return $counts;
}

function fetchFilteredAgreements(PDO $pdo, ?string $status = null, ?string $type = null, ?int $campusId = null): array
{
    $tables = registryTableNames($pdo);
    $agreementTable = $tables['agreement'];
    $partnerTable = $tables['partner'];
    $campusTable = $tables['campus'];

    if ($agreementTable === null || $partnerTable === null) {
        return [];
    }

    $sql = "SELECT
                a.Agree_ID AS id,
                p.Name AS partner,
                a.Agreement_Type AS type,
                a.Partnership_Type AS Partnership_type,
                c.Name AS campus,
                c.Campus_ID,
                a.Status AS status,
                a.Expiry_Date AS expiry,
                a.Signed_Date AS signed_date,
                a.Document_Path AS document_path,
                a.Scope_Description AS scope
            FROM `{$agreementTable}` a
            INNER JOIN `{$partnerTable}` p ON a.Partner_ID = p.Partner_ID
            INNER JOIN `{$campusTable}` c ON COALESCE(a.Campus_ID, p.Campus_ID) = c.Campus_ID
            WHERE " . registryAgreementStatusFilterSql('a');
    $params = [];

    if ($type !== null && $type !== '' && $type !== 'ALL') {
        $sql .= ' AND a.Agreement_Type = :type';
        $params['type'] = $type;
    }

    if ($campusId !== null && $campusId > 0) {
        $sql .= ' AND c.Campus_ID = :campus_id';
        $params['campus_id'] = $campusId;
    }

    $sql .= ' ORDER BY a.Agree_ID DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row = enrichAgreementRow($row);
    }
    unset($row);

    if ($status !== null && $status !== '' && $status !== 'ALL') {
        $rows = array_values(array_filter(
            $rows,
            static fn(array $row): bool => ($row['status'] ?? '') === $status
        ));
    }

    return $rows;
}

function agreementHasDocument(array $agreement): bool
{
    return agreementDocumentAbsolutePath((string) ($agreement['document_path'] ?? '')) !== null;
}

function agreementDocumentAbsolutePath(?string $relativePath): ?string
{
    $relativePath = str_replace('\\', '/', trim((string) $relativePath));

    if ($relativePath === '' || str_contains($relativePath, '..')) {
        return null;
    }

    if (!str_starts_with($relativePath, 'uploads/agreements/')) {
        return null;
    }

    $absolute = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    return is_file($absolute) ? $absolute : null;
}

function agreementDownloadFilename(string $relativePath): string
{
    $base = basename($relativePath);
    $stripped = preg_replace('/^\d+_/', '', $base);

    return is_string($stripped) && $stripped !== '' ? $stripped : $base;
}

function agreementDownloadUrl(int $agreementId, bool $forceDownload = false): string
{
    $url = routePath('dashboard/agreement-document') . '?id=' . $agreementId;

    if ($forceDownload) {
        $url .= '&download=1';
    }

    return $url;
}

function agreementDocumentMimeType(string $path): string
{
    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

    return match ($extension) {
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        default => 'application/octet-stream',
    };
}

function userCanAccessRegistryAgreement(array $user, array $agreement): bool
{
    $role = (string) ($user['role'] ?? '');

    if (roleMatchesAllowed($role, [ROLE_PARTNERSHIP_DIRECTOR, ROLE_PRESIDENT, ROLE_EXECUTIVE_OFFICER])) {
        return true;
    }

    if (!roleMatchesAllowed($role, [ROLE_CAMPUS_ADMIN])) {
        return false;
    }

    $userCampusId = (int) ($user['campus_id'] ?? 0);
    $agreementCampusId = (int) ($agreement['Campus_ID'] ?? $agreement['campus_id'] ?? 0);

    return $userCampusId > 0 && $userCampusId === $agreementCampusId;
}

function fetchRegistryAgreementById(PDO $pdo, int $agreementId): ?array
{
    $tables = registryTableNames($pdo);
    $agreementTable = $tables['agreement'];
    $partnerTable = $tables['partner'];
    $contactTable = $tables['contact'];
    $campusTable = $tables['campus'];

    if ($agreementId <= 0 || $agreementTable === null || $partnerTable === null) {
        return null;
    }

    $contactJoin = '';
    $contactSelect = "'N/A' AS contact, NULL AS contact_designation, NULL AS contact_email, NULL AS contact_phone, NULL AS contact_fax";

    if ($contactTable !== null) {
        $contactJoin = "LEFT JOIN (
                            SELECT Partner_ID, MIN(Contact_ID) AS Contact_ID
                            FROM `{$contactTable}`
                            GROUP BY Partner_ID
                        ) first_contact ON p.Partner_ID = first_contact.Partner_ID
                        LEFT JOIN `{$contactTable}` ct ON ct.Contact_ID = first_contact.Contact_ID";
        $contactSelect = 'COALESCE(ct.Name, \'N/A\') AS contact,
                          ct.Designation AS contact_designation,
                          ct.Email AS contact_email,
                          ct.Phone_Number AS contact_phone,
                          ct.Fax AS contact_fax';
    }

    $userJoin = '';
    $userSelect = 'NULL AS registered_by';
    $tablesList = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $usersTable = in_array('users', $tablesList, true) ? 'users' : null;

    if ($usersTable !== null) {
        $meta = usersTableMeta($pdo);
        $firstCol = $meta['first_name_col'];
        $lastCol = $meta['last_name_col'];
        $userJoin = "LEFT JOIN `{$usersTable}` submitter ON a.Submitted_By = submitter.User_ID";
        $userSelect = "TRIM(CONCAT(COALESCE(submitter.`{$firstCol}`, ''), ' ', COALESCE(submitter.`{$lastCol}`, ''))) AS registered_by";
    }

    $sql = "SELECT
                a.Agree_ID AS id,
                p.Name AS partner,
                p.Country AS partner_country,
                p.Address AS partner_address,
                p.Website AS partner_website,
                a.Agreement_Type AS type,
                a.Partnership_Type AS Partnership_type,
                c.Name AS campus,
                c.Province AS campus_province,
                c.Campus_ID,
                a.Status AS status,
                a.Expiry_Date AS expiry,
                a.Signed_Date AS signed_date,
                a.Document_Path AS document_path,
                a.Scope_Description AS scope,
                a.Director_Comments AS director_comments,
                a.created_at AS registered_at,
                {$contactSelect},
                {$userSelect}
            FROM `{$agreementTable}` a
            INNER JOIN `{$partnerTable}` p ON a.Partner_ID = p.Partner_ID
            INNER JOIN `{$campusTable}` c ON COALESCE(a.Campus_ID, p.Campus_ID) = c.Campus_ID
            {$contactJoin}
            {$userJoin}
            WHERE a.Agree_ID = :id
              AND " . registryAgreementStatusFilterSql('a') . "
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $agreementId]);
    $row = $stmt->fetch();

    return $row ? enrichAgreementRow($row) : null;
}

function fetchAgreementHistory(PDO $pdo): array
{
    $tables = registryTableNames($pdo);
    $historyTable = $tables['agreement_history'];
    $agreementTable = $tables['agreement'];
    $partnerTable = $tables['partner'];

    if ($historyTable === null || $agreementTable === null || $partnerTable === null) {
        return [];
    }

    $sql = "SELECT
                ah.AgreeHis_ID,
                ah.Agree_ID,
                ah.Event_Type,
                ah.Event_Date,
                ah.Comments,
                p.Name AS partner_name,
                a.Agreement_Type
            FROM `{$historyTable}` ah
            INNER JOIN `{$agreementTable}` a ON ah.Agree_ID = a.Agree_ID
            INNER JOIN `{$partnerTable}` p ON a.Partner_ID = p.Partner_ID
            ORDER BY ah.Event_Date DESC, ah.AgreeHis_ID DESC";

    return $pdo->query($sql)->fetchAll();
}

function fetchAgreementHistoryForId(PDO $pdo, int $agreementId): array
{
    $tables = registryTableNames($pdo);
    $historyTable = $tables['agreement_history'];

    if ($agreementId <= 0 || $historyTable === null) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT AgreeHis_ID, Agree_ID, Event_Type, Event_Date, Comments
         FROM `{$historyTable}`
         WHERE Agree_ID = :id
         ORDER BY Event_Date DESC, AgreeHis_ID DESC"
    );
    $stmt->execute(['id' => $agreementId]);

    return $stmt->fetchAll();
}

function registryDashboardUrl(array $params = []): string
{
    $query = [];

    if (isset($params['status']) && $params['status'] !== null && $params['status'] !== '') {
        $query['status'] = (string) $params['status'];
    }

    if (isset($params['campus_id']) && (int) $params['campus_id'] > 0) {
        $query['campus_id'] = (int) $params['campus_id'];
    }

    if (isset($params['agreement_id']) && (int) $params['agreement_id'] > 0) {
        $query['agreement_id'] = (int) $params['agreement_id'];
    }

    $url = routePath('dashboard/registry');

    return $query === [] ? $url : $url . '?' . http_build_query($query);
}

function fetchPartnersWithContacts(PDO $pdo): array
{
    $tables = registryTableNames($pdo);
    $partnerTable = $tables['partner'];
    $contactTable = $tables['contact'];
    $campusTable = $tables['campus'];

    if ($partnerTable === null) {
        return [];
    }

    $deletedFilter = partnerHasSoftDelete($pdo) ? ' WHERE p.Is_Deleted = 0' : '';
    $contactJoin = '';
    $contactSelect = 'NULL AS contact_name, NULL AS contact_email, NULL AS contact_phone';

    if ($contactTable !== null) {
        $contactJoin = "LEFT JOIN (
                            SELECT Partner_ID, MIN(Contact_ID) AS Contact_ID
                            FROM `{$contactTable}`
                            GROUP BY Partner_ID
                        ) first_contact ON p.Partner_ID = first_contact.Partner_ID
                        LEFT JOIN `{$contactTable}` ct ON ct.Contact_ID = first_contact.Contact_ID";
        $contactSelect = 'ct.Name AS contact_name, ct.Email AS contact_email, ct.Phone_Number AS contact_phone';
    }

    $sql = "SELECT
                p.Partner_ID,
                p.Name AS partner_name,
                p.Country,
                p.Website,
                c.Name AS campus_name,
                {$contactSelect}
            FROM `{$partnerTable}` p
            INNER JOIN `{$campusTable}` c ON p.Campus_ID = c.Campus_ID
            {$contactJoin}{$deletedFilter}
            ORDER BY p.Name ASC";

    return $pdo->query($sql)->fetchAll();
}

function createAgreementWithHistory(
    PDO $pdo,
    int $partnerId,
    string $partnershipType,
    string $agreementType,
    string $signedDate,
    string $expiryDate,
    string $createdBy
): int {
    $partnerTable = partnerTableName($pdo);

    if ($partnerTable === null) {
        throw new RuntimeException('Partner registry table is not available.');
    }

    $partnerStmt = $pdo->prepare("SELECT Campus_ID FROM `{$partnerTable}` WHERE Partner_ID = :partner_id LIMIT 1");
    $partnerStmt->execute(['partner_id' => $partnerId]);
    $partner = $partnerStmt->fetch();

    if ($partner === false) {
        throw new InvalidArgumentException('Selected partner was not found.');
    }

    return registerActivePartnership($pdo, [
        'partner_mode'        => 'existing',
        'partner_id'          => $partnerId,
        'campus_id'           => (int) $partner['Campus_ID'],
        'agreement_title'     => $agreementType,
        'partnership_type'    => $partnershipType,
        'agreement_type'      => $agreementType,
        'signed_date'         => $signedDate,
        'expiry_date'         => $expiryDate,
        'scope_description'   => '',
        'document_path'       => null,
        'contact_name'        => 'Registry Contact',
        'contact_designation' => null,
        'contact_email'       => null,
        'contact_phone'       => null,
        'contact_fax'         => null,
    ], (int) ($_SESSION['user_id'] ?? 0), $createdBy);
}

/**
 * @param array{
 *     partner_mode: string,
 *     partner_id?: int,
 *     partner_name?: string,
 *     partner_country?: string,
 *     partner_address?: string,
 *     partner_website?: string,
 *     campus_id: int,
 *     contact_name: string,
 *     contact_designation?: string,
 *     contact_email?: string,
 *     contact_phone?: string,
 *     contact_fax?: string,
 *     partnership_type: string,
 *     agreement_type: string,
 *     signed_date: string,
 *     expiry_date: string,
 *     scope_description?: string,
 *     document_path?: ?string
 * } $data
 */
function registerActivePartnership(
    PDO $pdo,
    array $data,
    int $directorUserId,
    string $createdByLabel = ''
): int {
    $partnerMode = strtolower(trim((string) ($data['partner_mode'] ?? 'existing')));
    $campusId = (int) ($data['campus_id'] ?? 0);
    $partnershipType = trim((string) ($data['partnership_type'] ?? ''));
    $agreementType = trim((string) ($data['agreement_type'] ?? ''));
    $signedDate = trim((string) ($data['signed_date'] ?? ''));
    $expiryDate = trim((string) ($data['expiry_date'] ?? ''));
    $scopeDescription = trim((string) ($data['scope_description'] ?? ''));
    $documentPath = $data['document_path'] ?? null;
    $contactName = trim((string) ($data['contact_name'] ?? ''));
    $contactDesignation = trim((string) ($data['contact_designation'] ?? ''));
    $contactEmail = trim((string) ($data['contact_email'] ?? ''));
    $contactPhone = trim((string) ($data['contact_phone'] ?? ''));
    $contactFax = trim((string) ($data['contact_fax'] ?? ''));
    $agreementTitle = trim((string) ($data['agreement_title'] ?? ''));
    $physicalAddress = trim((string) ($data['physical_address'] ?? $data['partner_address'] ?? ''));
    $mailingAddress = trim((string) ($data['mailing_address'] ?? ''));
    $partnerEmail = trim((string) ($data['partner_email'] ?? $contactEmail));
    $directorEmail = trim((string) ($data['director_email'] ?? ''));

    if ($contactEmail === '' && $partnerEmail !== '') {
        $contactEmail = $partnerEmail;
    }

    if ($directorUserId <= 0) {
        throw new InvalidArgumentException('A valid director session is required to register agreements.');
    }

    if ($campusId <= 0) {
        throw new InvalidArgumentException('Please select the managing DWU campus.');
    }

    if ($agreementTitle === '') {
        $agreementTitle = $agreementType;
    }

    if ($agreementTitle === '') {
        throw new InvalidArgumentException('Agreement title is required.');
    }

    if ($partnershipType === '' || $agreementType === '' || $signedDate === '' || $expiryDate === '') {
        throw new InvalidArgumentException('Partnership type, agreement type, and agreement dates are required.');
    }

    if ($contactName === '') {
        throw new InvalidArgumentException('Primary contact person name is required.');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $signedDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiryDate)) {
        throw new InvalidArgumentException('Dates must be provided in YYYY-MM-DD format.');
    }

    if ($expiryDate < $signedDate) {
        throw new InvalidArgumentException('Expiry date must be on or after the signed date.');
    }

    $tables = registryTableNames($pdo);
    $agreementTable = $tables['agreement'];
    $partnerTable = $tables['partner'];
    $contactTable = $tables['contact'];
    $historyTable = $tables['agreement_history'];
    $campusTable = $tables['campus'];

    if ($agreementTable === null || $partnerTable === null || $contactTable === null) {
        throw new RuntimeException('Registry tables are not available.');
    }

    $campusStmt = $pdo->prepare("SELECT Campus_ID FROM `{$campusTable}` WHERE Campus_ID = :campus_id LIMIT 1");
    $campusStmt->execute(['campus_id' => $campusId]);

    if ($campusStmt->fetch() === false) {
        throw new InvalidArgumentException('Selected campus was not found.');
    }

    if ($partnerMode === 'new') {
        $partnerName = trim((string) ($data['partner_name'] ?? ''));
        $partnerCountry = trim((string) ($data['partner_country'] ?? ''));
        $partnerAddress = $physicalAddress !== '' ? $physicalAddress : trim((string) ($data['partner_address'] ?? ''));
        $partnerWebsite = trim((string) ($data['partner_website'] ?? ''));

        if ($partnerName === '' || $partnerCountry === '') {
            throw new InvalidArgumentException('Partner name and country are required for new partners.');
        }

        $partnerId = createPartnerRecord(
            $pdo,
            $campusId,
            $partnerName,
            $partnerCountry,
            $partnerAddress,
            $partnerWebsite,
            $mailingAddress
        );
    } else {
        $partnerId = (int) ($data['partner_id'] ?? 0);

        if ($partnerId <= 0) {
            throw new InvalidArgumentException('Please select an existing partner.');
        }

        $partnerSql = "SELECT Partner_ID FROM `{$partnerTable}` WHERE Partner_ID = :partner_id";
        if (partnerHasSoftDelete($pdo)) {
            $partnerSql .= ' AND Is_Deleted = 0';
        }
        $partnerSql .= ' LIMIT 1';

        $partnerStmt = $pdo->prepare($partnerSql);
        $partnerStmt->execute(['partner_id' => $partnerId]);

        if ($partnerStmt->fetch() === false) {
            throw new InvalidArgumentException('Selected partner was not found.');
        }
    }

    $pdo->beginTransaction();

    try {
        createContactRecord(
            $pdo,
            $partnerId,
            $contactName,
            $contactDesignation,
            $contactEmail,
            $contactPhone,
            $contactFax
        );

        ensureAgreementAccessTokenColumn($pdo);
        ensureAgreementEntryColumns($pdo);
        $accessToken = generateUniqueAgreementAccessToken($pdo);

        $insertAgreement = $pdo->prepare(
            "INSERT INTO `{$agreementTable}`
                (Partner_ID, Campus_ID, Submitted_By, Reviewed_By, Partnership_Type, Agreement_Type,
                 Agreement_Title, Scope_Description, Physical_Address, Mailing_Address, Partner_Email,
                 Director_Email, Status, Signed_Date, Expiry_Date, Document_Path, access_token)
             VALUES
                (:partner_id, :campus_id, :submitted_by, :reviewed_by, :partnership_type, :agreement_type,
                 :agreement_title, :scope_description, :physical_address, :mailing_address, :partner_email,
                 :director_email, :status, :signed_date, :expiry_date, :document_path, :access_token)"
        );

        $insertAgreement->execute([
            'partner_id'         => $partnerId,
            'campus_id'          => $campusId,
            'submitted_by'       => $directorUserId,
            'reviewed_by'        => $directorUserId,
            'partnership_type'   => $partnershipType,
            'agreement_type'     => $agreementType,
            'agreement_title'    => $agreementTitle,
            'scope_description'  => $scopeDescription !== '' ? $scopeDescription : null,
            'physical_address'   => $physicalAddress !== '' ? $physicalAddress : null,
            'mailing_address'    => $mailingAddress !== '' ? $mailingAddress : null,
            'partner_email'      => $partnerEmail !== '' ? $partnerEmail : null,
            'director_email'     => $directorEmail !== '' ? $directorEmail : null,
            'status'             => Agreement::STATUS_ACTIVE,
            'signed_date'        => $signedDate,
            'expiry_date'        => $expiryDate,
            'document_path'      => $documentPath,
            'access_token'       => $accessToken,
        ]);

        $agreementId = (int) $pdo->lastInsertId();

        if ($historyTable !== null) {
            $historyComment = $createdByLabel !== ''
                ? sprintf(
                    'Active partnership registered by %s via the Partnership Director dashboard.',
                    $createdByLabel
                )
                : 'Active partnership registered via the Partnership Director dashboard.';

            if ($scopeDescription !== '') {
                $historyComment .= ' Scope/funding notes recorded.';
            }

            $historyColumns = $pdo->query("SHOW COLUMNS FROM `{$historyTable}`")->fetchAll(PDO::FETCH_COLUMN);
            $historyHasLoggedBy = in_array('Logged_By', $historyColumns, true);

            if ($historyHasLoggedBy) {
                $insertHistory = $pdo->prepare(
                    "INSERT INTO `{$historyTable}`
                        (Agree_ID, Logged_By, Event_Type, Event_Date, Comments)
                     VALUES
                        (:agree_id, :logged_by, :event_type, :event_date, :comments)"
                );

                $insertHistory->execute([
                    'agree_id'    => $agreementId,
                    'logged_by'   => $directorUserId,
                    'event_type'  => 'Agreement Created',
                    'event_date'  => date('Y-m-d H:i:s'),
                    'comments'    => $historyComment,
                ]);
            } else {
                $insertHistory = $pdo->prepare(
                    "INSERT INTO `{$historyTable}`
                        (Agree_ID, Event_Type, Event_Date, Comments)
                     VALUES
                        (:agree_id, :event_type, :event_date, :comments)"
                );

                $insertHistory->execute([
                    'agree_id'    => $agreementId,
                    'event_type'  => 'Agreement Created',
                    'event_date'  => date('Y-m-d H:i:s'),
                    'comments'    => $historyComment,
                ]);
            }
        }

        $pdo->commit();

        if ($directorEmail === '') {
            $directorEmail = fetchUserEmailById($pdo, $directorUserId);
        }

        try {
            sendNewAgreementRegisteredEmail($pdo, $agreementId, $partnerEmail, $directorEmail);
        } catch (Throwable $mailException) {
            error_log('New agreement notification failed for #' . $agreementId . ': ' . $mailException->getMessage());
        }

        return $agreementId;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function createPartnerRecord(
    PDO $pdo,
    int $campusId,
    string $name,
    string $country,
    string $address = '',
    string $website = '',
    string $mailingAddress = ''
): int {
    $partnerTable = partnerTableName($pdo);

    if ($partnerTable === null) {
        throw new RuntimeException('Partner registry table is not available.');
    }

    $columns = $pdo->query("SHOW COLUMNS FROM `{$partnerTable}`")->fetchAll(PDO::FETCH_COLUMN);
    $fields = ['Campus_ID', 'Name', 'Country'];
    $values = [':campus_id', ':name', ':country'];
    $params = [
        'campus_id' => $campusId,
        'name'      => $name,
        'country'   => $country,
    ];

    if (in_array('Address', $columns, true)) {
        $fields[] = 'Address';
        $values[] = ':address';
        $params['address'] = $address !== '' ? $address : null;
    }

    if (in_array('Mailing_Address', $columns, true)) {
        $fields[] = 'Mailing_Address';
        $values[] = ':mailing_address';
        $params['mailing_address'] = $mailingAddress !== '' ? $mailingAddress : null;
    }

    if (in_array('Website', $columns, true)) {
        $fields[] = 'Website';
        $values[] = ':website';
        $params['website'] = $website !== '' ? $website : null;
    }

    if (in_array('Is_Deleted', $columns, true)) {
        $fields[] = 'Is_Deleted';
        $values[] = '0';
    }

    $sql = sprintf(
        'INSERT INTO `%s` (%s) VALUES (%s)',
        $partnerTable,
        implode(', ', $fields),
        implode(', ', $values)
    );

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $pdo->lastInsertId();
}

function createContactRecord(
    PDO $pdo,
    int $partnerId,
    string $name,
    string $designation = '',
    string $email = '',
    string $phone = '',
    string $fax = ''
): int {
    $contactTable = contactTableName($pdo);

    if ($contactTable === null) {
        throw new RuntimeException('Contact registry table is not available.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO `{$contactTable}`
            (Partner_ID, Name, Designation, Email, Phone_Number, Fax)
         VALUES
            (:partner_id, :name, :designation, :email, :phone_number, :fax)"
    );

    $stmt->execute([
        'partner_id'   => $partnerId,
        'name'         => $name,
        'designation'  => $designation !== '' ? $designation : null,
        'email'        => $email !== '' ? $email : null,
        'phone_number' => $phone !== '' ? $phone : null,
        'fax'          => $fax !== '' ? $fax : null,
    ]);

    return (int) $pdo->lastInsertId();
}

function storeUploadedAgreementPdf(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Unable to upload the agreement PDF. Please try again.');
    }

    $uploadDir = dirname(__DIR__) . '/uploads/agreements';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename((string) ($file['name'] ?? 'agreement.pdf')));
    $filename = time() . '_' . $safeName;
    $targetPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Failed to store the uploaded agreement PDF.');
    }

    return 'uploads/agreements/' . $filename;
}

function proposalStatusFromSlug(string $slug): string
{
    return match (strtolower($slug)) {
        'pending', 'submitted' => Agreement::STATUS_SUBMITTED,
        'approved'             => Agreement::STATUS_APPROVED,
        'rejected'             => Agreement::STATUS_REJECTED,
        default                => $slug,
    };
}

function buildProposalScopeDescription(array $formData): string
{
    $parts = [];

    $description = trim((string) ($formData['partnership_description'] ?? ''));
    if ($description !== '') {
        $parts[] = "Objectives/Rationale:\n" . $description;
    }

    $dwuCommitments = trim((string) ($formData['dwu_commitments'] ?? ''));
    if ($dwuCommitments !== '') {
        $parts[] = "DWU Commitments:\n" . $dwuCommitments;
    }

    $partnerContributions = trim((string) ($formData['partner_contributions'] ?? ''));
    if ($partnerContributions !== '') {
        $parts[] = "Partner Contributions:\n" . $partnerContributions;
    }

    return implode("\n\n", $parts);
}

function parseCountryFromLocation(string $location): string
{
    $location = trim($location);
    if ($location === '') {
        return 'Unknown';
    }

    $parts = preg_split('/[,;]/', $location, 2);

    return trim((string) ($parts[0] ?? '')) ?: 'Unknown';
}

function resolveCampusIdForProposal(PDO $pdo, array $formData, array $user): int
{
    if (!empty($user['campus_id'])) {
        return (int) $user['campus_id'];
    }

    $campusName = trim((string) ($formData['campus'] ?? $formData['submitter_campus'] ?? ''));
    if ($campusName === '') {
        throw new InvalidArgumentException('Campus selection is required.');
    }

    $campusTable = campusTableName($pdo);
    $stmt = $pdo->prepare("SELECT Campus_ID FROM `{$campusTable}` WHERE Name = :name LIMIT 1");
    $stmt->execute(['name' => $campusName]);
    $row = $stmt->fetch();

    if ($row === false) {
        throw new InvalidArgumentException('Selected campus was not found.');
    }

    return (int) $row['Campus_ID'];
}

function resolveOrCreateProposalPartner(PDO $pdo, array $formData, int $campusId): int
{
    $partnerName = trim((string) ($formData['partner_name'] ?? $formData['partner_legal_name'] ?? ''));
    if ($partnerName === '') {
        throw new InvalidArgumentException('Partner name is required.');
    }

    $partnerTable = partnerTableName($pdo);
    if ($partnerTable === null) {
        throw new RuntimeException('Partner registry table is not available.');
    }

    $sql = "SELECT Partner_ID FROM `{$partnerTable}` WHERE Name = :name AND Campus_ID = :campus_id";
    if (partnerHasSoftDelete($pdo)) {
        $sql .= ' AND Is_Deleted = 0';
    }
    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'name'      => $partnerName,
        'campus_id' => $campusId,
    ]);
    $existing = $stmt->fetch();

    if ($existing !== false) {
        return (int) $existing['Partner_ID'];
    }

    $location = trim((string) ($formData['partner_location'] ?? ''));
    $country = parseCountryFromLocation($location);
    $website = trim((string) ($formData['partner_website'] ?? ''));

    return createPartnerRecord($pdo, $campusId, $partnerName, $country, $location, $website);
}

function submitCampusProposal(PDO $pdo, array $formData, array $user): int
{
    $userId = (int) ($user['id'] ?? 0);
    if ($userId <= 0) {
        throw new InvalidArgumentException('A valid campus admin session is required.');
    }

    $campusId = resolveCampusIdForProposal($pdo, $formData, $user);
    $partnerId = resolveOrCreateProposalPartner($pdo, $formData, $campusId);

    $partnershipTypes = $formData['partnership_nature'] ?? $formData['partnership_types'] ?? [];
    if (!is_array($partnershipTypes)) {
        $partnershipTypes = [$partnershipTypes];
    }

    $partnershipType = implode(', ', array_filter(array_map('strval', $partnershipTypes)));
    $agreementType = trim((string) ($formData['agreement_type'] ?? 'MOU'));
    $scopeDescription = buildProposalScopeDescription($formData);

    $agreementTable = agreementTableName($pdo);
    if ($agreementTable === null) {
        throw new RuntimeException('Agreement table is not available.');
    }

    $pdo->beginTransaction();

    try {
        $insert = $pdo->prepare(
            "INSERT INTO `{$agreementTable}`
                (Partner_ID, Campus_ID, Submitted_By, Partnership_Type, Agreement_Type,
                 Scope_Description, Status)
             VALUES
                (:partner_id, :campus_id, :submitted_by, :partnership_type, :agreement_type,
                 :scope_description, :status)"
        );

        $insert->execute([
            'partner_id'         => $partnerId,
            'campus_id'          => $campusId,
            'submitted_by'       => $userId,
            'partnership_type'   => $partnershipType !== '' ? $partnershipType : null,
            'agreement_type'     => $agreementType,
            'scope_description'  => $scopeDescription !== '' ? $scopeDescription : null,
            'status'             => Agreement::STATUS_SUBMITTED,
        ]);

        $agreeId = (int) $pdo->lastInsertId();
        $submitterLabel = trim((string) ($formData['staff_name'] ?? $user['name'] ?? 'Campus Admin'));

        logAgreementHistory(
            $pdo,
            $agreeId,
            'Proposal Submitted',
            sprintf('Partnership proposal submitted by %s for director review.', $submitterLabel),
            $userId
        );

        $pdo->commit();

        return $agreeId;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

/** @return array<string, mixed> */
function formatProposalRow(array $row): array
{
    $submittedAt = $row['created_at'] ?? $row['updated_at'] ?? null;
    if ($submittedAt !== null && $submittedAt !== '') {
        $submittedAt = date('Y-m-d', strtotime((string) $submittedAt));
    } else {
        $submittedAt = date('Y-m-d');
    }

    $reviewedAt = $row['reviewed_at'] ?? $row['updated_at'] ?? null;
    if ($reviewedAt !== null && $reviewedAt !== '') {
        $reviewedAt = date('Y-m-d', strtotime((string) $reviewedAt));
    } else {
        $reviewedAt = $submittedAt;
    }

    return [
        'id'                  => (int) ($row['Agree_ID'] ?? $row['id'] ?? 0),
        'partner_name'        => (string) ($row['partner_name'] ?? ''),
        'partnership_type'    => (string) ($row['Partnership_Type'] ?? $row['partnership_type'] ?? ''),
        'agreement_type'      => (string) ($row['Agreement_Type'] ?? $row['agreement_type'] ?? ''),
        'campus'              => (string) ($row['campus_name'] ?? $row['campus'] ?? ''),
        'submitted_by'        => (string) ($row['submitter_name'] ?? $row['submitted_by'] ?? ''),
        'submitted_at'        => $submittedAt,
        'reviewed_at'         => $reviewedAt,
        'status'              => strtolower((string) ($row['Status'] ?? $row['status'] ?? '')),
        'rejection_comment'   => (string) ($row['Director_Comments'] ?? $row['rejection_comment'] ?? ''),
        'scope_description'   => (string) ($row['Scope_Description'] ?? $row['scope_description'] ?? ''),
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function fetchAgreementsByStatus(
    PDO $pdo,
    string $status,
    ?int $campusId = null,
    ?int $submittedBy = null
): array {
    $agreementTable = agreementTableName($pdo);
    $partnerTable = partnerTableName($pdo);
    $campusTable = campusTableName($pdo);

    if ($agreementTable === null || $partnerTable === null) {
        return [];
    }

    $meta = usersTableMeta($pdo);
    $first = $meta['first_name_col'];
    $last = $meta['last_name_col'];

    $sql = "SELECT
                a.Agree_ID,
                a.Partnership_Type,
                a.Agreement_Type,
                a.Scope_Description,
                a.Status,
                a.Director_Comments,
                a.Submitted_By,
                a.created_at,
                a.updated_at,
                p.Name AS partner_name,
                c.Name AS campus_name,
                CONCAT(u.`{$first}`, ' ', u.`{$last}`) AS submitter_name
            FROM `{$agreementTable}` a
            INNER JOIN `{$partnerTable}` p ON a.Partner_ID = p.Partner_ID
            INNER JOIN `{$campusTable}` c ON a.Campus_ID = c.Campus_ID
            LEFT JOIN users u ON a.Submitted_By = u.User_ID
            WHERE a.Status = :status";

    $params = ['status' => $status];

    if ($campusId !== null && $campusId > 0) {
        $sql .= ' AND a.Campus_ID = :campus_id';
        $params['campus_id'] = $campusId;
    }

    if ($submittedBy !== null && $submittedBy > 0) {
        $sql .= ' AND a.Submitted_By = :submitted_by';
        $params['submitted_by'] = $submittedBy;
    }

    $sql .= ' ORDER BY a.Agree_ID DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $proposals = [];
    while ($row = $stmt->fetch()) {
        $proposals[] = formatProposalRow($row);
    }

    return $proposals;
}

/** @return list<array<string, mixed>> */
function fetchSubmittedProposals(PDO $pdo): array
{
    return fetchAgreementsByStatus($pdo, Agreement::STATUS_SUBMITTED);
}

/** @return list<array<string, mixed>> */
function fetchApprovedProposals(PDO $pdo): array
{
    return fetchAgreementsByStatus($pdo, Agreement::STATUS_APPROVED);
}

function logAgreementHistory(PDO $pdo, int $agreeId, string $eventType, string $comments, int $loggedBy): void
{
    $historyTable = registryTableNames($pdo)['agreement_history'];
    if ($historyTable === null || $agreeId <= 0 || $loggedBy <= 0) {
        return;
    }

    $columns = $pdo->query("SHOW COLUMNS FROM `{$historyTable}`")->fetchAll(PDO::FETCH_COLUMN);
    $hasLoggedBy = in_array('Logged_By', $columns, true);

    if ($hasLoggedBy) {
        $stmt = $pdo->prepare(
            "INSERT INTO `{$historyTable}` (Agree_ID, Logged_By, Event_Type, Event_Date, Comments)
             VALUES (:agree_id, :logged_by, :event_type, :event_date, :comments)"
        );

        $stmt->execute([
            'agree_id'    => $agreeId,
            'logged_by'   => $loggedBy,
            'event_type'  => $eventType,
            'event_date'  => date('Y-m-d H:i:s'),
            'comments'    => $comments,
        ]);

        return;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO `{$historyTable}` (Agree_ID, Event_Type, Event_Date, Comments)
         VALUES (:agree_id, :event_type, :event_date, :comments)"
    );

    $stmt->execute([
        'agree_id'    => $agreeId,
        'event_type'  => $eventType,
        'event_date'  => date('Y-m-d H:i:s'),
        'comments'    => $comments,
    ]);
}

function approveProposal(PDO $pdo, int $agreeId, int $directorUserId, string $directorName = ''): bool
{
    $agreementTable = agreementTableName($pdo);
    if ($agreementTable === null || $agreeId <= 0 || $directorUserId <= 0) {
        return false;
    }

    $check = $pdo->prepare(
        "SELECT Agree_ID FROM `{$agreementTable}` WHERE Agree_ID = :id AND Status = :status LIMIT 1"
    );
    $check->execute([
        'id'     => $agreeId,
        'status' => Agreement::STATUS_SUBMITTED,
    ]);

    if ($check->fetch() === false) {
        return false;
    }

    $pdo->beginTransaction();

    try {
        $update = $pdo->prepare(
            "UPDATE `{$agreementTable}`
             SET Status = :status, Reviewed_By = :reviewed_by, updated_at = NOW()
             WHERE Agree_ID = :id AND Status = :current_status"
        );

        $update->execute([
            'status'          => Agreement::STATUS_APPROVED,
            'reviewed_by'     => $directorUserId,
            'id'              => $agreeId,
            'current_status'  => Agreement::STATUS_SUBMITTED,
        ]);

        $comment = 'Proposal approved for offline negotiation.';
        if ($directorName !== '') {
            $comment .= ' Approved by ' . $directorName . '.';
        }

        logAgreementHistory($pdo, $agreeId, 'Proposal Approved', $comment, $directorUserId);
        $pdo->commit();

        return true;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function rejectProposal(PDO $pdo, int $agreeId, int $directorUserId, string $reason, string $directorName = ''): bool
{
    $agreementTable = agreementTableName($pdo);
    $reason = trim($reason);

    if ($agreementTable === null || $agreeId <= 0 || $directorUserId <= 0 || $reason === '') {
        return false;
    }

    $check = $pdo->prepare(
        "SELECT Agree_ID FROM `{$agreementTable}` WHERE Agree_ID = :id AND Status = :status LIMIT 1"
    );
    $check->execute([
        'id'     => $agreeId,
        'status' => Agreement::STATUS_SUBMITTED,
    ]);

    if ($check->fetch() === false) {
        return false;
    }

    $pdo->beginTransaction();

    try {
        $update = $pdo->prepare(
            "UPDATE `{$agreementTable}`
             SET Status = :status, Reviewed_By = :reviewed_by, Director_Comments = :comments, updated_at = NOW()
             WHERE Agree_ID = :id AND Status = :current_status"
        );

        $update->execute([
            'status'          => Agreement::STATUS_REJECTED,
            'reviewed_by'     => $directorUserId,
            'comments'        => $reason,
            'id'              => $agreeId,
            'current_status'  => Agreement::STATUS_SUBMITTED,
        ]);

        $historyComment = 'Proposal rejected: ' . $reason;
        if ($directorName !== '') {
            $historyComment .= ' (Reviewed by ' . $directorName . ')';
        }

        logAgreementHistory($pdo, $agreeId, 'Proposal Rejected', $historyComment, $directorUserId);
        $pdo->commit();

        return true;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function proposalDraftTableName(PDO $pdo): string
{
    static $ensured = false;
    $table = 'proposal_draft';

    if ($ensured) {
        return $table;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `{$table}` (
            Draft_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
            User_ID INT NOT NULL,
            Campus_ID INT NULL DEFAULT NULL,
            Title VARCHAR(255) NOT NULL DEFAULT 'Untitled draft',
            Partner_Name VARCHAR(255) NULL DEFAULT NULL,
            Agreement_Type VARCHAR(255) NULL DEFAULT NULL,
            Campus_Name VARCHAR(255) NULL DEFAULT NULL,
            Form_Data LONGTEXT NOT NULL,
            Created_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            Updated_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (Draft_ID),
            KEY idx_proposal_draft_user (User_ID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $ensured = true;

    return $table;
}

/** @return array<string, mixed> */
function sanitizeProposalDraftFormData(array $formData): array
{
    unset($formData['form_action'], $formData['draft_id'], $formData['return_tab']);

    return $formData;
}

function proposalDraftTitle(array $formData): string
{
    $partner = trim((string) ($formData['partner_name'] ?? $formData['partner_legal_name'] ?? ''));

    return $partner !== '' ? $partner : 'Untitled draft';
}

function formatProposalDraftTimestamp(?string $value): string
{
    if ($value === null || $value === '') {
        return date('M j, Y g:i A');
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('M j, Y g:i A', $timestamp) : date('M j, Y g:i A');
}

/**
 * @return array<string, mixed>
 */
function formatProposalDraftRow(array $row, bool $includeForm = false): array
{
    $form = [];
    if ($includeForm) {
        $decoded = json_decode((string) ($row['Form_Data'] ?? ''), true);
        $form = is_array($decoded) ? $decoded : [];
    }

    return [
        'id'              => (int) ($row['Draft_ID'] ?? 0),
        'title'           => (string) ($row['Title'] ?? 'Untitled draft'),
        'partner_name'    => (string) ($row['Partner_Name'] ?? ''),
        'agreement_type'  => (string) ($row['Agreement_Type'] ?? ''),
        'campus'          => (string) ($row['Campus_Name'] ?? ''),
        'saved_at'        => formatProposalDraftTimestamp($row['Updated_At'] ?? null),
        'created_at'      => formatProposalDraftTimestamp($row['Created_At'] ?? null),
        'form'            => $form,
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function fetchCampusProposalDrafts(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $table = proposalDraftTableName($pdo);
    $stmt = $pdo->prepare(
        "SELECT Draft_ID, User_ID, Campus_ID, Title, Partner_Name, Agreement_Type, Campus_Name, Created_At, Updated_At
         FROM `{$table}`
         WHERE User_ID = :user_id
         ORDER BY Updated_At DESC, Draft_ID DESC"
    );
    $stmt->execute(['user_id' => $userId]);

    $drafts = [];
    while ($row = $stmt->fetch()) {
        $drafts[] = formatProposalDraftRow($row);
    }

    return $drafts;
}

/** @return array<string, mixed>|null */
function fetchCampusProposalDraftById(PDO $pdo, int $userId, int $draftId): ?array
{
    if ($userId <= 0 || $draftId <= 0) {
        return null;
    }

    $table = proposalDraftTableName($pdo);
    $stmt = $pdo->prepare(
        "SELECT Draft_ID, User_ID, Campus_ID, Title, Partner_Name, Agreement_Type, Campus_Name,
                Form_Data, Created_At, Updated_At
         FROM `{$table}`
         WHERE Draft_ID = :draft_id AND User_ID = :user_id
         LIMIT 1"
    );
    $stmt->execute([
        'draft_id' => $draftId,
        'user_id'  => $userId,
    ]);
    $row = $stmt->fetch();

    return $row === false ? null : formatProposalDraftRow($row, true);
}

function persistCampusProposalDraft(PDO $pdo, array $user, array $formData, ?int $draftId = null): int
{
    $userId = (int) ($user['id'] ?? 0);
    if ($userId <= 0) {
        throw new InvalidArgumentException('A valid campus admin session is required to save a draft.');
    }

    $clean = sanitizeProposalDraftFormData($formData);
    $payload = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $title = proposalDraftTitle($clean);
    $partnerName = trim((string) ($clean['partner_name'] ?? $clean['partner_legal_name'] ?? ''));
    $agreementType = trim((string) ($clean['agreement_type'] ?? ''));
    $campusName = trim((string) ($clean['campus'] ?? $clean['submitter_campus'] ?? $user['campus'] ?? ''));
    $campusId = isset($user['campus_id']) && (int) $user['campus_id'] > 0
        ? (int) $user['campus_id']
        : null;

    $table = proposalDraftTableName($pdo);

    if ($draftId !== null && $draftId > 0) {
        $existing = fetchCampusProposalDraftById($pdo, $userId, $draftId);
        if ($existing !== null) {
            $update = $pdo->prepare(
                "UPDATE `{$table}`
                 SET Title = :title,
                     Partner_Name = :partner_name,
                     Agreement_Type = :agreement_type,
                     Campus_Name = :campus_name,
                     Campus_ID = :campus_id,
                     Form_Data = :form_data,
                     Updated_At = NOW()
                 WHERE Draft_ID = :draft_id AND User_ID = :user_id"
            );
            $update->execute([
                'title'           => $title,
                'partner_name'    => $partnerName !== '' ? $partnerName : null,
                'agreement_type'  => $agreementType !== '' ? $agreementType : null,
                'campus_name'     => $campusName !== '' ? $campusName : null,
                'campus_id'       => $campusId,
                'form_data'       => $payload,
                'draft_id'        => $draftId,
                'user_id'         => $userId,
            ]);

            return $draftId;
        }
    }

    $insert = $pdo->prepare(
        "INSERT INTO `{$table}`
            (User_ID, Campus_ID, Title, Partner_Name, Agreement_Type, Campus_Name, Form_Data)
         VALUES
            (:user_id, :campus_id, :title, :partner_name, :agreement_type, :campus_name, :form_data)"
    );
    $insert->execute([
        'user_id'         => $userId,
        'campus_id'       => $campusId,
        'title'           => $title,
        'partner_name'    => $partnerName !== '' ? $partnerName : null,
        'agreement_type'  => $agreementType !== '' ? $agreementType : null,
        'campus_name'     => $campusName !== '' ? $campusName : null,
        'form_data'       => $payload,
    ]);

    return (int) $pdo->lastInsertId();
}

function deleteCampusProposalDraftRecord(PDO $pdo, int $userId, int $draftId): bool
{
    if ($userId <= 0 || $draftId <= 0) {
        return false;
    }

    $table = proposalDraftTableName($pdo);
    $stmt = $pdo->prepare(
        "DELETE FROM `{$table}` WHERE Draft_ID = :draft_id AND User_ID = :user_id"
    );
    $stmt->execute([
        'draft_id' => $draftId,
        'user_id'  => $userId,
    ]);

    return $stmt->rowCount() > 0;
}

function migrateLegacySessionProposalDraft(array $user): void
{
    $key = $user['staff_id'] ?? $user['email'] ?? 'default';
    $legacy = $_SESSION['proposal_drafts'][$key] ?? null;

    if (!is_array($legacy) || $legacy === []) {
        return;
    }

    $looksLikeForm = isset($legacy['partner_name'])
        || isset($legacy['partner_legal_name'])
        || isset($legacy['staff_name'])
        || isset($legacy['form_action'])
        || isset($legacy['agreement_type']);

    if ($looksLikeForm) {
        persistCampusProposalDraft($GLOBALS['pdo'], $user, $legacy);
    }

    unset($_SESSION['proposal_drafts'][$key]);
    if (empty($_SESSION['proposal_drafts'])) {
        unset($_SESSION['proposal_drafts']);
    }
}

function directorAgreementDraftTableName(PDO $pdo): string
{
    static $ensured = false;
    $table = 'agreement_draft';

    if ($ensured) {
        return $table;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `{$table}` (
            Draft_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
            User_ID INT NOT NULL,
            Campus_ID INT NULL DEFAULT NULL,
            Title VARCHAR(255) NOT NULL DEFAULT 'Untitled draft',
            Partner_Name VARCHAR(255) NULL DEFAULT NULL,
            Agreement_Type VARCHAR(255) NULL DEFAULT NULL,
            Campus_Name VARCHAR(255) NULL DEFAULT NULL,
            Form_Data LONGTEXT NOT NULL,
            Created_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            Updated_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (Draft_ID),
            KEY idx_agreement_draft_user (User_ID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $ensured = true;

    return $table;
}

/** @return array<string, mixed> */
function collectDirectorAgreementFormData(array $post, ?string $documentPath = null): array
{
    $data = [
        'partner_mode'        => (string) ($post['partner_mode'] ?? 'existing'),
        'partner_id'          => (int) ($post['partner_id'] ?? 0),
        'partner_name'        => trim((string) ($post['partner_name'] ?? '')),
        'partner_country'     => trim((string) ($post['partner_country'] ?? '')),
        'partner_website'     => trim((string) ($post['partner_website'] ?? '')),
        'campus_id'           => (int) ($post['campus_id'] ?? 0),
        'contact_name'        => trim((string) ($post['contact_name'] ?? '')),
        'contact_designation' => trim((string) ($post['contact_designation'] ?? '')),
        'contact_email'       => trim((string) ($post['contact_email'] ?? '')),
        'contact_phone'       => trim((string) ($post['contact_phone'] ?? '')),
        'contact_fax'         => trim((string) ($post['contact_fax'] ?? '')),
        'agreement_title'     => trim((string) ($post['agreement_title'] ?? '')),
        'physical_address'    => trim((string) ($post['physical_address'] ?? '')),
        'mailing_address'     => trim((string) ($post['mailing_address'] ?? '')),
        'partner_email'       => trim((string) ($post['partner_email'] ?? '')),
        'director_email'      => trim((string) ($post['director_email'] ?? '')),
        'partnership_type'    => trim((string) ($post['partnership_type'] ?? '')),
        'agreement_type'      => trim((string) ($post['agreement_type'] ?? '')),
        'signed_date'         => trim((string) ($post['signed_date'] ?? '')),
        'expiry_date'         => trim((string) ($post['expiry_date'] ?? '')),
        'scope_description'   => trim((string) ($post['scope_description'] ?? '')),
        'document_path'       => $documentPath,
    ];

    if ($data['document_path'] === null || $data['document_path'] === '') {
        $existing = trim((string) ($post['existing_document_path'] ?? ''));
        $data['document_path'] = $existing !== '' ? $existing : null;
    }

    return $data;
}

function directorAgreementDraftTitle(array $formData, PDO $pdo): string
{
    $title = trim((string) ($formData['agreement_title'] ?? ''));
    if ($title !== '') {
        return $title;
    }

    $partnerName = trim((string) ($formData['partner_name'] ?? ''));
    if ($partnerName === '') {
        $partnerId = (int) ($formData['partner_id'] ?? 0);
        if ($partnerId > 0) {
            $partnerTable = partnerTableName($pdo);
            if ($partnerTable !== null) {
                $stmt = $pdo->prepare("SELECT Name FROM `{$partnerTable}` WHERE Partner_ID = :id LIMIT 1");
                $stmt->execute(['id' => $partnerId]);
                $row = $stmt->fetch();
                $partnerName = is_array($row) ? trim((string) ($row['Name'] ?? '')) : '';
            }
        }
    }

    return $partnerName !== '' ? $partnerName : 'Untitled draft';
}

function directorAgreementDraftPartnerName(array $formData, PDO $pdo): string
{
    $partnerName = trim((string) ($formData['partner_name'] ?? ''));
    if ($partnerName !== '') {
        return $partnerName;
    }

    $partnerId = (int) ($formData['partner_id'] ?? 0);
    if ($partnerId <= 0) {
        return '';
    }

    $partnerTable = partnerTableName($pdo);
    if ($partnerTable === null) {
        return '';
    }

    $stmt = $pdo->prepare("SELECT Name FROM `{$partnerTable}` WHERE Partner_ID = :id LIMIT 1");
    $stmt->execute(['id' => $partnerId]);
    $row = $stmt->fetch();

    return is_array($row) ? trim((string) ($row['Name'] ?? '')) : '';
}

function directorAgreementDraftCampusName(array $formData, PDO $pdo): string
{
    $campusId = (int) ($formData['campus_id'] ?? 0);
    if ($campusId <= 0) {
        return '';
    }

    $campusTable = campusTableName($pdo);
    $stmt = $pdo->prepare("SELECT Name FROM `{$campusTable}` WHERE Campus_ID = :id LIMIT 1");
    $stmt->execute(['id' => $campusId]);
    $row = $stmt->fetch();

    return is_array($row) ? trim((string) ($row['Name'] ?? '')) : '';
}

/** @return array<string, mixed> */
function formatDirectorAgreementDraftRow(array $row, bool $includeForm = false): array
{
    $form = [];
    if ($includeForm) {
        $decoded = json_decode((string) ($row['Form_Data'] ?? ''), true);
        $form = is_array($decoded) ? $decoded : [];
    }

    return [
        'id'             => (int) ($row['Draft_ID'] ?? 0),
        'title'          => (string) ($row['Title'] ?? 'Untitled draft'),
        'partner_name'   => (string) ($row['Partner_Name'] ?? ''),
        'agreement_type' => (string) ($row['Agreement_Type'] ?? ''),
        'campus'         => (string) ($row['Campus_Name'] ?? ''),
        'saved_at'       => formatProposalDraftTimestamp($row['Updated_At'] ?? null),
        'form'           => $form,
    ];
}

/** @return list<array<string, mixed>> */
function fetchDirectorAgreementDrafts(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $table = directorAgreementDraftTableName($pdo);
    $stmt = $pdo->prepare(
        "SELECT Draft_ID, User_ID, Campus_ID, Title, Partner_Name, Agreement_Type, Campus_Name, Created_At, Updated_At
         FROM `{$table}`
         WHERE User_ID = :user_id
         ORDER BY Updated_At DESC, Draft_ID DESC"
    );
    $stmt->execute(['user_id' => $userId]);

    $drafts = [];
    while ($row = $stmt->fetch()) {
        $drafts[] = formatDirectorAgreementDraftRow($row);
    }

    return $drafts;
}

/** @return array<string, mixed>|null */
function fetchDirectorAgreementDraftById(PDO $pdo, int $userId, int $draftId): ?array
{
    if ($userId <= 0 || $draftId <= 0) {
        return null;
    }

    $table = directorAgreementDraftTableName($pdo);
    $stmt = $pdo->prepare(
        "SELECT Draft_ID, User_ID, Campus_ID, Title, Partner_Name, Agreement_Type, Campus_Name,
                Form_Data, Created_At, Updated_At
         FROM `{$table}`
         WHERE Draft_ID = :draft_id AND User_ID = :user_id
         LIMIT 1"
    );
    $stmt->execute([
        'draft_id' => $draftId,
        'user_id'  => $userId,
    ]);
    $row = $stmt->fetch();

    return $row === false ? null : formatDirectorAgreementDraftRow($row, true);
}

function persistDirectorAgreementDraft(PDO $pdo, array $user, array $formData, ?int $draftId = null): int
{
    $userId = (int) ($user['id'] ?? 0);
    if ($userId <= 0) {
        throw new InvalidArgumentException('A valid director session is required to save a draft.');
    }

    unset($formData['action'], $formData['draft_id']);
    $payload = json_encode($formData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $title = directorAgreementDraftTitle($formData, $pdo);
    $partnerName = directorAgreementDraftPartnerName($formData, $pdo);
    $agreementType = trim((string) ($formData['agreement_type'] ?? ''));
    $campusName = directorAgreementDraftCampusName($formData, $pdo);
    $campusId = (int) ($formData['campus_id'] ?? 0);
    $campusId = $campusId > 0 ? $campusId : null;

    $table = directorAgreementDraftTableName($pdo);

    if ($draftId !== null && $draftId > 0) {
        $existing = fetchDirectorAgreementDraftById($pdo, $userId, $draftId);
        if ($existing !== null) {
            $update = $pdo->prepare(
                "UPDATE `{$table}`
                 SET Title = :title,
                     Partner_Name = :partner_name,
                     Agreement_Type = :agreement_type,
                     Campus_Name = :campus_name,
                     Campus_ID = :campus_id,
                     Form_Data = :form_data,
                     Updated_At = NOW()
                 WHERE Draft_ID = :draft_id AND User_ID = :user_id"
            );
            $update->execute([
                'title'           => $title,
                'partner_name'    => $partnerName !== '' ? $partnerName : null,
                'agreement_type'  => $agreementType !== '' ? $agreementType : null,
                'campus_name'     => $campusName !== '' ? $campusName : null,
                'campus_id'       => $campusId,
                'form_data'       => $payload,
                'draft_id'        => $draftId,
                'user_id'         => $userId,
            ]);

            return $draftId;
        }
    }

    $insert = $pdo->prepare(
        "INSERT INTO `{$table}`
            (User_ID, Campus_ID, Title, Partner_Name, Agreement_Type, Campus_Name, Form_Data)
         VALUES
            (:user_id, :campus_id, :title, :partner_name, :agreement_type, :campus_name, :form_data)"
    );
    $insert->execute([
        'user_id'         => $userId,
        'campus_id'       => $campusId,
        'title'           => $title,
        'partner_name'    => $partnerName !== '' ? $partnerName : null,
        'agreement_type'  => $agreementType !== '' ? $agreementType : null,
        'campus_name'     => $campusName !== '' ? $campusName : null,
        'form_data'       => $payload,
    ]);

    return (int) $pdo->lastInsertId();
}

function deleteDirectorAgreementDraft(PDO $pdo, int $userId, int $draftId): bool
{
    if ($userId <= 0 || $draftId <= 0) {
        return false;
    }

    $table = directorAgreementDraftTableName($pdo);
    $stmt = $pdo->prepare(
        "DELETE FROM `{$table}` WHERE Draft_ID = :draft_id AND User_ID = :user_id"
    );
    $stmt->execute([
        'draft_id' => $draftId,
        'user_id'  => $userId,
    ]);

    return $stmt->rowCount() > 0;
}

function handleDirectorPartnershipEntryPost(PDO $pdo, array $user, string $returnPath): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    $action = (string) ($_POST['action'] ?? '');
    $draftId = (int) ($_POST['draft_id'] ?? 0);
    $userId = (int) ($user['id'] ?? 0);

    if ($action === 'delete_draft') {
        if ($draftId > 0 && deleteDirectorAgreementDraft($pdo, $userId, $draftId)) {
            setFlash('success', 'Draft deleted.');
        } else {
            setFlash('error', 'That draft could not be deleted.');
        }

        redirect($returnPath);
    }

    if ($action === 'save_draft') {
        try {
            $uploadedPath = storeUploadedAgreementPdf($_FILES['agreement_pdf'] ?? []);
            $formData = collectDirectorAgreementFormData($_POST, $uploadedPath);
            $savedId = persistDirectorAgreementDraft(
                $pdo,
                $user,
                $formData,
                $draftId > 0 ? $draftId : null
            );
            setFlash(
                'success',
                'Draft #' . $savedId . ' saved. You can enter another agreement now, or open Saved Drafts later to edit and register it.'
            );
        } catch (Throwable $exception) {
            setFlash('error', $exception->getMessage());
            if ($draftId > 0) {
                redirect($returnPath . (str_contains($returnPath, '?') ? '&' : '?') . 'draft=' . $draftId);
            }
        }

        redirect($returnPath);
    }

    if ($action === 'register_agreement') {
        try {
            $uploadedPath = storeUploadedAgreementPdf($_FILES['agreement_pdf'] ?? []);
            $formData = collectDirectorAgreementFormData($_POST, $uploadedPath);
            $agreementId = registerActivePartnership($pdo, $formData, $userId, (string) ($user['name'] ?? ''));

            if ($draftId > 0) {
                deleteDirectorAgreementDraft($pdo, $userId, $draftId);
            }

            setFlash(
                'success',
                'Agreement #' . $agreementId . ' registered successfully. Notification emails were sent to the partner and director with a secure access link.'
            );
        } catch (Throwable $exception) {
            setFlash('error', $exception->getMessage());
            if ($draftId > 0) {
                redirect($returnPath . (str_contains($returnPath, '?') ? '&' : '?') . 'draft=' . $draftId);
            }
        }

        redirect($returnPath);
    }
}
