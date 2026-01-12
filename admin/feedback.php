<?php
/**
 * Admin Feedback Dashboard
 * View and manage user feedback with filtering and statistics.
 */

require_once '../check_auth.php';
require_once '../db_mysql.php';

// Get database connection
try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

// Fetch stats
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN rating = 'positive' THEN 1 ELSE 0 END) as positive,
        SUM(CASE WHEN rating = 'negative' THEN 1 ELSE 0 END) as negative,
        SUM(CASE WHEN rating = 'neutral' THEN 1 ELSE 0 END) as neutral,
        SUM(CASE WHEN type = 'message_rating' THEN 1 ELSE 0 END) as message_ratings,
        SUM(CASE WHEN type = 'general' THEN 1 ELSE 0 END) as general_feedback
    FROM feedback
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Handle filters
$typeFilter = $_GET['type'] ?? '';
$ratingFilter = $_GET['rating'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$sql = "SELECT f.*, u.username, u.email, u.first_name, u.last_name 
        FROM feedback f 
        LEFT JOIN users u ON f.user_id = u.id 
        WHERE 1=1";
$params = [];

if ($typeFilter && in_array($typeFilter, ['message_rating', 'general'])) {
    $sql .= " AND f.type = ?";
    $params[] = $typeFilter;
}
if ($ratingFilter && in_array($ratingFilter, ['positive', 'negative', 'neutral'])) {
    $sql .= " AND f.rating = ?";
    $params[] = $ratingFilter;
}

// Get total count for pagination
$countSql = str_replace("SELECT f.*, u.username, u.email, u.first_name, u.last_name", "SELECT COUNT(*)", $sql);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalCount = $countStmt->fetchColumn();
$totalPages = ceil($totalCount / $limit);

// Get feedback
$sql .= " ORDER BY f.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$feedbackList = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Dashboard | TutorMind Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #7b3ff2;
            --primary-light: #9d6bf5;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg-main: #f9fafb;
            --bg-card: #ffffff;
            --text-primary: #1a1a1a;
            --text-secondary: #6b7280;
            --border: #e5e7eb;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        h1 {
            font-size: 1.75rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        h1 i { color: var(--primary); }
        
        .back-link {
            color: var(--text-secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s;
        }
        
        .back-link:hover { color: var(--primary); }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            text-align: center;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .stat-card .label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .stat-card.positive .value { color: var(--success); }
        .stat-card.negative .value { color: var(--danger); }
        .stat-card.neutral .value { color: var(--warning); }
        
        /* Filters */
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .filter-select {
            padding: 10px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            background: var(--bg-card);
            cursor: pointer;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        /* Feedback Table */
        .feedback-table {
            background: var(--bg-card);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        th {
            background: var(--bg-main);
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        tr:last-child td { border-bottom: none; }
        
        tr:hover td { background: rgba(123, 63, 242, 0.03); }
        
        .rating-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .rating-badge.positive { background: rgba(34, 197, 94, 0.1); color: var(--success); }
        .rating-badge.negative { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .rating-badge.neutral { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        
        .type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            background: rgba(123, 63, 242, 0.1);
            color: var(--primary);
        }
        
        .comment-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .user-cell {
            display: flex;
            flex-direction: column;
        }
        
        .user-cell .name { font-weight: 500; }
        .user-cell .email { font-size: 0.8rem; color: var(--text-secondary); }
        
        .date-cell {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 1.5rem;
        }
        
        .pagination a, .pagination span {
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            color: var(--text-secondary);
            background: var(--bg-card);
            border: 1px solid var(--border);
            transition: all 0.2s;
        }
        
        .pagination a:hover { border-color: var(--primary); color: var(--primary); }
        
        .pagination .active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }
        
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .feedback-table { overflow-x: auto; }
            th, td { padding: 10px 12px; font-size: 0.9rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-comment-dots"></i> Feedback Dashboard</h1>
            <a href="../chat" class="back-link"><i class="fas fa-arrow-left"></i> Back to Chat</a>
        </header>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="value"><?= $stats['total'] ?? 0 ?></div>
                <div class="label">Total Feedback</div>
            </div>
            <div class="stat-card positive">
                <div class="value"><?= $stats['positive'] ?? 0 ?></div>
                <div class="label">Positive</div>
            </div>
            <div class="stat-card negative">
                <div class="value"><?= $stats['negative'] ?? 0 ?></div>
                <div class="label">Negative</div>
            </div>
            <div class="stat-card neutral">
                <div class="value"><?= $stats['neutral'] ?? 0 ?></div>
                <div class="label">Neutral</div>
            </div>
            <div class="stat-card">
                <div class="value"><?= $stats['message_ratings'] ?? 0 ?></div>
                <div class="label">Message Ratings</div>
            </div>
            <div class="stat-card">
                <div class="value"><?= $stats['general_feedback'] ?? 0 ?></div>
                <div class="label">General Feedback</div>
            </div>
        </div>
        
        <!-- Filters -->
        <form class="filters" method="GET">
            <select name="type" class="filter-select" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="message_rating" <?= $typeFilter === 'message_rating' ? 'selected' : '' ?>>Message Ratings</option>
                <option value="general" <?= $typeFilter === 'general' ? 'selected' : '' ?>>General Feedback</option>
            </select>
            <select name="rating" class="filter-select" onchange="this.form.submit()">
                <option value="">All Ratings</option>
                <option value="positive" <?= $ratingFilter === 'positive' ? 'selected' : '' ?>>Positive</option>
                <option value="negative" <?= $ratingFilter === 'negative' ? 'selected' : '' ?>>Negative</option>
                <option value="neutral" <?= $ratingFilter === 'neutral' ? 'selected' : '' ?>>Neutral</option>
            </select>
        </form>
        
        <!-- Feedback Table -->
        <div class="feedback-table">
            <?php if (empty($feedbackList)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No feedback found</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Type</th>
                            <th>Rating</th>
                            <th>Comment</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedbackList as $fb): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <span class="name"><?= htmlspecialchars($fb['first_name'] ?? $fb['username'] ?? 'Unknown') ?></span>
                                        <span class="email"><?= htmlspecialchars($fb['email'] ?? '') ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="type-badge">
                                        <?= $fb['type'] === 'message_rating' ? '💬 Message' : '📝 General' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="rating-badge <?= $fb['rating'] ?>">
                                        <?php if ($fb['rating'] === 'positive'): ?>
                                            <i class="fas fa-thumbs-up"></i> Positive
                                        <?php elseif ($fb['rating'] === 'negative'): ?>
                                            <i class="fas fa-thumbs-down"></i> Negative
                                        <?php else: ?>
                                            <i class="fas fa-minus"></i> Neutral
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="comment-cell" title="<?= htmlspecialchars($fb['comment'] ?? '') ?>">
                                    <?= htmlspecialchars($fb['comment'] ?? '-') ?>
                                </td>
                                <td class="date-cell">
                                    <?= date('M j, Y g:ia', strtotime($fb['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&type=<?= urlencode($typeFilter) ?>&rating=<?= urlencode($ratingFilter) ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>&type=<?= urlencode($typeFilter) ?>&rating=<?= urlencode($ratingFilter) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&type=<?= urlencode($typeFilter) ?>&rating=<?= urlencode($ratingFilter) ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
