<?php
require_once 'config/session.php';
require_once 'config/database.php';

class LeaderboardManager {
    private PDO $pdo;
    
    public function __construct() {
        $config = getDbConfiguration($GLOBALS['db_configuration']);
        $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
        
        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            throw new Exception("Erreur de connexion à la base de données");
        }
    }
    
    public function getTopTenPlayers(): array {
        $stmt = $this->pdo->query("
            SELECT 
                p.username,
                MAX(g.score) as best_score,
                COUNT(g.id) as total_games,
                COUNT(CASE WHEN g.status = 'completed' THEN 1 END) as completed_games
            FROM players p
            INNER JOIN games g ON p.id = g.player_id
            WHERE g.status = 'completed'
            GROUP BY p.id, p.username
            ORDER BY best_score DESC
            LIMIT 10
        ");
        
        return $stmt->fetchAll();
    }
}

function getRankMedal(int $rank): string {
    switch ($rank) {
        case 1: return '🥇';
        case 2: return '🥈';
        case 3: return '🥉';
        default: return $rank . 'ème';
    }
}

$leaderboard = new LeaderboardManager();
$topPlayers = [];
$error = '';

try {
    $topPlayers = $leaderboard->getTopTenPlayers();
} catch (Exception $e) {
    $error = "Erreur lors du chargement du classement";
    error_log("Erreur leaderboard: " . $e->getMessage());
}

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memory Game - Top 10 Duelistes</title>
    <link rel="stylesheet" href="assets/css/memoryStyle.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1> Top 10 des Duelistes </h1>
            <p class="text-secondary">Les meilleurs joueurs du Memory Game</p>
        </div>
        
        <div class="game-setup">
            <?php if ($error): ?>
                <div class="message theme-error"><?= htmlspecialchars($error) ?></div>
            <?php elseif (!empty($topPlayers)): ?>
                <table class="leaderboard-table">
                    <thead>
                        <tr>
                            <th>Rang</th>
                            <th >Dueliste</th>
                            <th>Meilleur Score</th>
                            <th>Parties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topPlayers as $index => $player): ?>
                            <tr class="<?= $index < 3 ? 'podium-row' : '' ?>">
                                <td class="rank-cell">
                                    <?= getRankMedal($index + 1) ?>
                                </td>
                                <td class="player-name">
                                    <?= htmlspecialchars($player['username']) ?>
                                </td>
                                <td class="score-cell">
                                    <?= number_format($player['best_score']) ?> pts
                                </td>
                                <td class="games-cell">
                                    <?= $player['completed_games'] ?> / <?= $player['total_games'] ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-leaderboard">
                    <h3>Aucun dueliste classé</h3>
                    <p>Soyez le premier à terminer une partie et entrer dans la légende !</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="join-site">
            <p>Entraînez-vous, progressez et rejoignez le top 10 des plus grands duelistes !</p>
        </div>
    </div>
</body>
</html>