<?php
function paginate($pdo, $table, $alias, $where, $orderBy, $page, $limit)
{
    $page = max(1, (int)$page);
    $offset = ($page - 1) * $limit;

    $countSql = "SELECT COUNT(*) FROM $table $alias $where";
    $total = $pdo->query($countSql)->fetchColumn();

    $totalPages = max(1, ceil($total / $limit));

    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    $sql = "
        SELECT *
        FROM $table $alias
        $where
        $orderBy
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $pdo->query($sql);

    return [
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'page' => $page,
        'totalPages' => $totalPages,
        'total' => $total
    ];
}
?>