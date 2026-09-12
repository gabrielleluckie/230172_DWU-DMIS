<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/agreement_notify.php';

ensureAgreementEntryColumns($pdo);

echo "Agreement entry columns are ready.\n";
echo "Migration complete.\n";
