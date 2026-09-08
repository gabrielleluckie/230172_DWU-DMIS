<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/agreement_notify.php';

ensureAgreementAccessTokenColumn($pdo);

echo "access_token column is ready on the agreement table.\n";
echo "Migration complete.\n";
