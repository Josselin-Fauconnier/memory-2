<?php
require_once 'config/session.php';
require_once 'config/database.php';
require_once 'User.php';

class ProfilManager {
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
    
    public function getUserStats(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.username,
                p.created_at,
                COUNT(g.id) as total_games,
                COUNT(CASE WHEN g.status = 'completed' THEN 1 END) as completed_games,
                COUNT(CASE WHEN g.status = 'abandoned' THEN 1 END) as abandoned_games,
                MAX(g.score) as best_score,
                AVG(CASE WHEN g.status = 'completed' THEN g.score END) as average_score,
                MIN(CASE WHEN g.status = 'completed' THEN g.duration_seconds END) as best_time,
                AVG(CASE WHEN g.status = 'completed' THEN g.duration_seconds END) as average_time,
                AVG(CASE WHEN g.status = 'completed' THEN g.moves_count END) as average_moves
            FROM players p
            LEFT JOIN games g ON p.id = g.player_id
            WHERE p.id = :user_id
            GROUP BY p.id, p.username, p.created_at
        ");
        
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();
        
        if (!$result) {
            throw new Exception("Utilisateur introuvable");
        }
        
        return $result;
    }
    
    public function getRecentGames(int $userId, int $limit = 5): array {
        $stmt = $this->pdo->prepare("
            SELECT 
                pairs_count,
                moves_count,
                score,
                duration_seconds,
                status,
                start_time
            FROM games 
            WHERE player_id = :user_id 
            ORDER BY start_time DESC 
            LIMIT :limit
        ");
        
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function updateUsername(int $userId, string $newUsername, string $currentPassword): array {
        try {
            $stmt = $this->pdo->prepare("SELECT password_hash FROM players WHERE id = :user_id");
            $stmt->execute(['user_id' => $userId]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Mot de passe incorrect'];
            }
            
            if (!User::isValidUsername($newUsername)) {
                return ['success' => false, 'message' => 'Nom d\'utilisateur invalide'];
            }
            
            $stmt = $this->pdo->prepare("SELECT id FROM players WHERE username = :username AND id != :user_id");
            $stmt->execute(['username' => $newUsername, 'user_id' => $userId]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Ce nom d\'utilisateur est déjà utilisé'];
            }
           
            $stmt = $this->pdo->prepare("UPDATE players SET username = :username WHERE id = :user_id");
            $stmt->execute(['username' => $newUsername, 'user_id' => $userId]);
            
            $_SESSION['username'] = $newUsername;
            
            return ['success' => true, 'message' => 'Nom d\'utilisateur mis à jour avec succès'];
            
        } catch (Exception $e) {
            error_log("Erreur mise à jour username: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour'];
        }
    }
    
    public function updatePassword(int $userId, string $currentPassword, string $newPassword): array {
        try {
            $stmt = $this->pdo->prepare("SELECT password_hash FROM players WHERE id = :user_id");
            $stmt->execute(['user_id' => $userId]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Mot de passe actuel incorrect'];
            }
            
            if (!User::isValidPassword($newPassword)) {
                return ['success' => false, 'message' => 'Le nouveau mot de passe ne respecte pas les critères de sécurité'];
            }
            
            if (password_verify($newPassword, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Le nouveau mot de passe doit être différent de l\'ancien'];
            }
            
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $this->pdo->prepare("UPDATE players SET password_hash = :password_hash WHERE id = :user_id");
            $stmt->execute(['password_hash' => $newHash, 'user_id' => $userId]);
            
            return ['success' => true, 'message' => 'Mot de passe mis à jour avec succès'];
            
        } catch (Exception $e) {
            error_log("Erreur mise à jour password: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour'];
        }
    }
}


$userId = $_SESSION['user_id'] ?? 1;
$username = $_SESSION['username'] ?? 'Invité';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];


$activeTab = $_GET['tab'] ?? 'stats';
if (!in_array($activeTab, ['stats', 'history', 'settings'])) {
    $activeTab = 'stats';
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_username':
            $newUsername = trim($_POST['new_username'] ?? '');
            $currentPassword = $_POST['current_password'] ?? '';
            
            if (empty($newUsername) || empty($currentPassword)) {
                $error = "Tous les champs sont obligatoires";
            } else {
                $profilManager = new ProfilManager();
                $result = $profilManager->updateUsername($userId, $newUsername, $currentPassword);
                if ($result['success']) {
                    $message = $result['message'];
                    $username = $newUsername; 
                } else {
                    $error = $result['message'];
                }
            }
            $activeTab = 'settings'; 
            break;
            
        case 'update_password':
            $currentPassword = $_POST['current_password_pwd'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $error = "Tous les champs sont obligatoires";
            } elseif ($newPassword !== $confirmPassword) {
                $error = "Les nouveaux mots de passe ne correspondent pas";
            } else {
                $profilManager = new ProfilManager();
                $result = $profilManager->updatePassword($userId, $currentPassword, $newPassword);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
            }
            $activeTab = 'settings'; // Rester sur l'onglet paramètres
            break;
    }
}


$userStats = null;
$recentGames = [];

if ($isLoggedIn && $userId > 0) {
    try {
        if (!isset($profilManager)) {
            $profilManager = new ProfilManager();
        }
        $userStats = $profilManager->getUserStats($userId);
        $recentGames = $profilManager->getRecentGames($userId);
    } catch (Exception $e) {
        if (empty($error)) { // Ne pas écraser les erreurs de formulaire
            $error = "Erreur lors du chargement des données : " . $e->getMessage();
        }
        error_log("Erreur profil: " . $e->getMessage());
    }
}


if ($userStats) {
    $successRate = $userStats['total_games'] > 0 ? 
        round(($userStats['completed_games'] / $userStats['total_games']) * 100, 1) : 0;
    $level = max(1, (int)floor($userStats['completed_games'] / 10) + 1);
    $experienceToNextLevel = ($level * 10) - $userStats['completed_games'];
} else {
    $successRate = 0;
    $level = 1;
    $experienceToNextLevel = 10;
}

function formatDuration(?int $seconds): string {
    if ($seconds === null) return "N/A";
    
    $minutes = floor($seconds / 60);
    $remainingSeconds = $seconds % 60;
    
    if ($minutes > 0) {
        return sprintf("%dm %02ds", $minutes, $remainingSeconds);
    }
    return sprintf("%ds", $remainingSeconds);
}

echo "<!-- Debug: ActiveTab = $activeTab, UserStats loaded = " . ($userStats ? 'YES' : 'NO') . " -->";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil de dueliste</title>
    <link rel="stylesheet" href="assets/css/memoryStyle.css">
</head>
<body>
    <nav id="nav-bar">
    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
        <a href="index.php">Accueil</a>
        <a href="leaderboard.php">Classement</a>
        <a href="dueliste.php">Ton profil </a>
        <a href="deconnection.php">Déconnexion</a>
    <?php else: ?>
        <a href="index.php">Accueil</a>
        <a href="leaderboard.php">Classement</a>
        <a href="inscription-connextion.php">S'inscrire/se connecter</a>
    <?php endif; ?>
    </nav>

    <div class="container">
        <div class="header">
            <h1>Profil de <?= htmlspecialchars($username) ?></h1>
            <div class="rank-badge">
                <?php if ($userStats && $userStats['completed_games'] >= 10): ?>
                    Élève de l'Académie
                <?php elseif ($userStats && $userStats['completed_games'] >= 5): ?>
                    Débutant Prometteur
                <?php else: ?>
                    Nouveau Duelliste
                <?php endif; ?>
       
        <?php if ($activeTab === 'settings' && $isLoggedIn): ?>
        <div class="game-setup">
            <h2>Paramètres du compte</h2>

            <div class="auth-container" style="margin-bottom: 30px;">
                <h3 class="text-gold">Changer le nom d'utilisateur</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_username">
                    
                    <div class="form-group">
                        <label for="new_username">Nouveau nom d'utilisateur :</label>
                        <input type="text" name="new_username" id="new_username" 
                               autocomplete="username" required
                               value="<?= htmlspecialchars($username) ?>">
                        <div class="validation-info">
                            3-25 caractères, lettres, chiffres, tirets et underscores uniquement
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="current_password">Mot de passe actuel :</label>
                        <input type="password" name="current_password" id="current_password" 
                               autocomplete="current-password" required>
                    </div>
                    
                    <button type="submit">Mettre à jour le nom d'utilisateur</button>
                </form>
            </div>
    
            <div class="auth-container">
                <h3 class="text-gold">Changer le mot de passe</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_password">
                    
                    <div class="form-group">
                        <label for="current_password_pwd">Mot de passe actuel :</label>
                        <input type="password" name="current_password_pwd" id="current_password_pwd" 
                               autocomplete="current-password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Nouveau mot de passe :</label>
                        <input type="password" name="new_password" id="new_password" 
                               autocomplete="new-password" required>
                        <div class="validation-info">
                            Minimum 12 caractères avec au moins :<br>
                            • Une lettre • Un chiffre • Un caractère spécial (!@#$%^&*...)
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirmer le nouveau mot de passe :</label>
                        <input type="password" name="confirm_password" id="confirm_password" 
                               autocomplete="new-password" required>
                    </div>
                    
                    <button type="submit">Mettre à jour le mot de passe</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message theme-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message theme-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!$isLoggedIn): ?>
            <div class="message theme-info">
                <p>Connectez-vous pour accéder à toutes les fonctionnalités de votre profil.</p>
                <a href="inscription-connextion.php" class="btn-guest" style="display: inline-block; margin-top: 10px;">Se connecter</a>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin: 20px 0;">
            <a href="dueliste.php?tab=stats" class="nav-button <?= $activeTab === 'stats' ? 'nav-button-primary' : 'nav-button-secondary' ?>">
                Statistiques
            </a>
            <a href="dueliste.php?tab=history" class="nav-button <?= $activeTab === 'history' ? 'nav-button-primary' : 'nav-button-secondary' ?>">
                Historique
            </a>
            <?php if ($isLoggedIn): ?>
            <a href="dueliste.php?tab=settings" class="nav-button <?= $activeTab === 'settings' ? 'nav-button-primary' : 'nav-button-secondary' ?>">
                Paramètres
            </a>
            <?php endif; ?>
        </div>
        
        <?php if ($activeTab === 'stats'): ?>
        <div class="game-setup">
            <h2>Statistiques de jeu</h2>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-value"><?= $level ?></span>
                    <div class="stat-label">Niveau</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $experienceToNextLevel > 0 ? (1 - ($experienceToNextLevel / ($level * 10))) * 100 : 100 ?>%"></div>
                    </div>
                    <small class="text-secondary">
                        <?= $experienceToNextLevel > 0 ? "Encore $experienceToNextLevel parties pour le niveau suivant" : "Niveau maximum !" ?>
                    </small>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?= $userStats ? number_format($userStats['best_score']) : '0' ?></span>
                    <div class="stat-label">Meilleur Score</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?= $userStats ? $userStats['total_games'] : '0' ?></span>
                    <div class="stat-label">Parties Jouées</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?= $userStats ? $userStats['completed_games'] : '0' ?></span>
                    <div class="stat-label">Parties Terminées</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?= $successRate ?>%</span>
                    <div class="stat-label">Taux de Réussite</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $successRate ?>%"></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?= formatDuration($userStats['best_time'] ?? null) ?></span>
                    <div class="stat-label">Meilleur Temps</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?= $userStats && $userStats['average_score'] ? number_format($userStats['average_score']) : 'N/A' ?></span>
                    <div class="stat-label">Score Moyen</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?= formatDuration($userStats['average_time'] ?? null) ?></span>
                    <div class="stat-label">Temps Moyen</div>
                </div>
            </div>
            
            <?php if ($userStats): ?>
                <div class="stat-card">
                    <h3>Informations du compte</h3>
                    <p><strong>Membre depuis :</strong> <?= date('d/m/Y', strtotime($userStats['created_at'])) ?></p>
                    <p><strong>Parties abandonnées :</strong> <?= $userStats['abandoned_games'] ?></p>
                    <p><strong>Mouvements moyens :</strong> <?= $userStats['average_moves'] ? round($userStats['average_moves'], 1) : 'N/A' ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($activeTab === 'history'): ?>
        <div class="game-setup">
            <h2>Historique des parties</h2>
            
            <?php if (!empty($recentGames) && $isLoggedIn): ?>
                <table class="leaderboard-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Paires</th>
                            <th>Mouvements</th>
                            <th>Score</th>
                            <th>Durée</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentGames as $game): ?>
                        <tr>
                            <td><?= date('d/m H:i', strtotime($game['start_time'])) ?></td>
                            <td><?= $game['pairs_count'] ?> paires</td>
                            <td><?= $game['moves_count'] ?></td>
                            <td class="score-cell"><?= number_format($game['score']) ?> pts</td>
                            <td><?= formatDuration($game['duration_seconds']) ?></td>
                            <td>
                                <?php
                                $statusClass = $game['status'] === 'completed' ? 'theme-success' : 
                                              ($game['status'] === 'abandoned' ? 'theme-error' : 'theme-info');
                                $statusText = $game['status'] === 'completed' ? 'Terminée' : 
                                             ($game['status'] === 'abandoned' ? 'Abandonnée' : 'En cours');
                                ?>
                                <span class="<?= $statusClass ?>" style="padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">
                                    <?= $statusText ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($isLoggedIn): ?>
                <div class="empty-leaderboard">
                    <h3>Aucune partie jouée</h3>
                    <p>Commencez votre première partie pour voir votre historique !</p>
                    <a href="index.php" class="btn-guest">Jouer maintenant</a>
                </div>
            <?php else: ?>
                <div class="empty-leaderboard">
                    <h3>Connectez-vous</h3>
                    <p>Connectez-vous pour voir votre historique de parties</p>
                    <a href="inscription-connextion.php" class="btn-guest">Se connecter</a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
    </div>
</body>
</html>