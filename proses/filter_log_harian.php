<?php
function bind_params(mysqli_stmt $stmt, string $types, array &$params): void
{
    $refs = [];
    foreach ($params as $key => &$value) {
        $refs[$key] = &$value;
    }

    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function ambil_log_harian(mysqli $koneksi, string $search = '', int $page = 1, int $limit = 5): array
{
    $allowedLimits = [5, 10, 20, 50];
    if (!in_array($limit, $allowedLimits, true)) {
        $limit = 5;
    }

    $page = max(1, $page);
    $offset = ($page - 1) * $limit;
    $search = trim($search);
    $where = '';
    $params = [];
    $types = '';

    if ($search !== '') {
        $where = "WHERE DATE_FORMAT(lh.waktu, '%H:%i') LIKE ?
            OR DATE_FORMAT(lh.created_at, '%Y-%m-%d') LIKE ?
            OR lh.suhu LIKE ?
            OR lh.kelembaban LIKE ?
            OR lh.cahaya LIKE ?
            OR lh.level_air LIKE ?
            OR lh.lampu LIKE ?
            OR DAYNAME(lh.created_at) LIKE ?";
        $keyword = '%' . $search . '%';
        $params = [$keyword, $keyword, $keyword, $keyword, $keyword, $keyword, $keyword, $keyword];
        $types = 'ssssssss';
    }

    $countSql = "SELECT COUNT(*) AS total FROM log_harian lh $where";
    $countStmt = mysqli_prepare($koneksi, $countSql);
    if ($types !== '') {
        bind_params($countStmt, $types, $params);
    }
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    $total = (int) (mysqli_fetch_assoc($countResult)['total'] ?? 0);

    $totalPages = max(1, (int) ceil($total / $limit));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    $sql = "SELECT
            lh.*
        FROM log_harian lh
        $where
        ORDER BY lh.created_at DESC, lh.id DESC
        LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($koneksi, $sql);
    if ($types !== '') {
        $paramsWithLimit = array_merge($params, [$limit, $offset]);
        bind_params($stmt, $types . 'ii', $paramsWithLimit);
    } else {
        mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    return [
        'rows' => $rows,
        'total' => $total,
        'limit' => $limit,
        'page' => $page,
        'total_pages' => $totalPages,
        'search' => $search,
    ];
}
?>
