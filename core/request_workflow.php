<?php
declare(strict_types=1);

function request_relationship_options(): array
{
    return [
        'Wife' => 'Wife',
        'Husband' => 'Husband',
        'Son' => 'Son',
        'Daughter' => 'Daughter',
        'Mother' => 'Mother',
        'Father' => 'Father',
        'Brother' => 'Brother',
        'Sister' => 'Sister',
        'Grandmother' => 'Grandmother',
        'Grandfather' => 'Grandfather',
        'Grandson' => 'Grandson',
        'Granddaughter' => 'Granddaughter',
        'Aunt' => 'Aunt',
        'Uncle' => 'Uncle',
        'Niece' => 'Niece',
        'Nephew' => 'Nephew',
        'Cousin' => 'Cousin',
        'Guardian' => 'Guardian',
    ];
}

function request_purpose_options(): array
{
    return [
        'Bank' => 'Bank',
        'Police Clearance' => 'Police Clearance',
        'NBI' => 'NBI',
        'Electric/Water' => 'Electric/Water',
        'Loan - Car' => 'Loan - Car',
        'Loan - Motor' => 'Loan - Motor',
        'Loan - E-bike' => 'Loan - E-bike',
        'Loan - Cash Loan' => 'Loan - Cash Loan',
        'SSS' => 'SSS',
        'Pag-IBIG' => 'Pag-IBIG',
        'School' => 'School',
        'Driver’s License' => 'Driver’s License',
        'Postal ID' => 'Postal ID',
        'Passport/Visa' => 'Passport/Visa',
        'Firearm License' => 'Firearm License',
        'Clearance for Abroad' => 'Clearance for Abroad',
        'Business' => 'Business',
        'Renew' => 'Renew',
        'Others (specify)' => 'Others (specify)',
    ];
}

function request_names_match(string $left, string $right): bool
{
    $normalize = static function (string $name): string {
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
        return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    };

    return $normalize($left) === $normalize($right);
}

function request_display_name(?string $name, ?string $relationship): string
{
    $name = trim((string) $name);
    $relationship = trim((string) $relationship);

    if ($relationship === '' || strcasecmp($relationship, 'Self') === 0) {
        return $name;
    }

    return $name . ' - ' . strtoupper($relationship);
}

function ensure_request_workflow_schema(PDO $pdo): void
{
    $statusColumn = $pdo->query("SHOW COLUMNS FROM requests LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
    if (!$statusColumn) {
        throw new RuntimeException('The requests.status column is missing.');
    }

    $statusType = (string) ($statusColumn['Type'] ?? '');
    if (preg_match('/^enum\((.*)\)$/i', $statusType, $typeMatch) === 1) {
        preg_match_all("~'([^']*)'~", $typeMatch[1], $enumMatches);
        $statusValues = $enumMatches[1] ?? [];

        foreach (['To be Claim', 'Expired'] as $requiredStatus) {
            if (!in_array($requiredStatus, $statusValues, true)) {
                $statusValues[] = $requiredStatus;
            }
        }

        if (count($statusValues) !== count($enumMatches[1] ?? [])) {
            $enum = implode(', ', array_map(static fn(string $value): string => $pdo->quote($value), $statusValues));
            $pdo->exec("ALTER TABLE requests MODIFY status ENUM($enum) NOT NULL DEFAULT 'Pending'");
        }
    }

    foreach (['requests', 'archive_requests'] as $table) {
        if ($table === 'archive_requests' && !$pdo->query("SHOW TABLES LIKE 'archive_requests'")->fetchColumn()) {
            continue;
        }

        foreach ([
            'relationship' => 'VARCHAR(32) NULL',
            'document_name_parts' => 'LONGTEXT NULL',
        ] as $column => $definition) {
            $columnCheck = $pdo->prepare(
                'SELECT COLUMN_NAME
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?'
            );
            $columnCheck->execute([$table, $column]);
            if (!$columnCheck->fetch(PDO::FETCH_ASSOC)) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            }

            $columnCheck->execute([$table, $column]);
            if (!$columnCheck->fetchColumn()) {
                throw new RuntimeException("Unable to ensure `$table`.`$column` exists.");
            }
        }
    }
}
