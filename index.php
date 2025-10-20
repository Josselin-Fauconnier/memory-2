<?php

// Inclure vos classes existantes
require_once 'Card.php';
require_once 'Game.php';
require_once 'User.php';
require_once 'GameDao.php';
require_once 'config/session.php';

// Variables pour messages et état
$message = '';
$error = '';

// Gestion des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'new_game':
            try {
                $pairsCount = (int)($_POST['pairs_count'] ?? 3);
                
                // Créer une nouvelle partie avec vos classes
                $game = new Game(1, $pairsCount); // Player ID = 1 pour le moment
                
                // Sauvegarder en session
                $_SESSION['current_game'] = $game->toArray();
                $message = "Nouvelle partie créée avec {$pairsCount} paires !";
                
            } catch (Exception $e) {
                $error = "Erreur lors de la création : " . $e->getMessage();
            }
            break;
            
        case 'flip_card':
            try {
                if (isset($_SESSION['current_game'])) {
                    // Restaurer le jeu depuis la session
                    $game = Game::fromArray($_SESSION['current_game']);
                    
                    $cardId = (int)$_POST['card_id'];
                    
                    // Utiliser votre méthode flipCard
                    $result = $game->flipCard($cardId);
                    
                    // Sauvegarder l'état mis à jour
                    $_SESSION['current_game'] = $game->toArray();
                    
                    // Afficher le message de résultat
                    $message = $result['message'];
                    
                    // Vérifier si le jeu est terminé
                    if (isset($result['gameCompleted']) && $result['gameCompleted']) {
   
                    $gameDAO = new GameDAO();
                    $gameDAO->saveGame($game);
    
                $message = " Félicitations ! Partie terminée en " . $game->getMovesCount() . " coups. Score: " . $game->getScore();
                }
                } else {
                    $error = "Aucune partie en cours.";
                }
            } catch (Exception $e) {
                $error = "Erreur: " . $e->getMessage();
            }
            break;
            
        case 'abandon':
            if (isset($_SESSION['current_game'])) {
                $game = Game::fromArray($_SESSION['current_game']);
                $game->abandonGame();
                $_SESSION['current_game'] = $game->toArray();
                $message = "Partie abandonnée.";
            }
            break;
            
        case 'reset':
            unset($_SESSION['current_game']);
            $message = "Nouvelle session créée.";
            break;
    }
}

// Récupérer l'état du jeu actuel
$currentGame = null;
$gameState = null;
if (isset($_SESSION['current_game'])) {
    try {
        $currentGame = Game::fromArray($_SESSION['current_game']);
        $gameState = $currentGame->getGameState();
    } catch (Exception $e) {
        $error = "Erreur lors du chargement du jeu: " . $e->getMessage();
        unset($_SESSION['current_game']);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memory Game version  Yu-Gi-Oh! </title>
    <link rel="stylesheet" href="assets/css/memoryStyle.css">
</head>
<body>
<nav id="nav-bar">
    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
        <a href="index.php">Accueil</a>
        <a href="leaderboard.php">Classement</a>
        <a href="dueliste.php"> Ton profil </a>
        <a href="deconnection.php">Déconnexion</a>
    <?php else: ?>
        <a href="index.php">Accueil</a>
        <a href="leaderboard.php">Classement</a>
        <a href="inscription-connextion.php">S'inscrire/se connecter</a>
    <?php endif; ?>
</nav>
    <div class="container">
        <div class="header">
            <h1> Memory duel </h1>
            <p>Arène des Duellistes </p>
        </div>

        <?php if ($message): ?>
            <div class="message <?= strpos($message, 'Erreur') !== false ? 'error' : (strpos($message, 'Félicitations') !== false ? 'success' : 'info') ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!$currentGame): ?>
            
            <div class="game-setup">
                <h2 style="color: #ffd700; text-align: center; margin-bottom: 20px;">🎯 Préparez votre duel</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="new_game">
                    <div class="form-group">
                        <label for="pairs_count">Choisissez le nombre de paires :</label>
                        <select name="pairs_count" id="pairs_count" required>
                            <option value="3">3 paires (6 cartes)</option>
                            <option value="6">6 paires (12 cartes)</option>
                            <option value="8">8 paires (16 cartes)</option>
                            <option value="10">10 paires (20 cartes)</option>
                            <option value="12">12 paires (24 cartes)</option>
                        </select>
                    </div>
                    <button type="submit"> Commencer le duel</button>
                </form>
            </div>
        <?php else: ?>
            <!-- Plateau de jeu -->
            <div class="game-board">
                <div class="game-info">
                    <div>Paires: <?= $gameState['matchedPairs'] ?>/<?= $gameState['pairsCount'] ?></div>
                    <div>Coups: <?= $gameState['movesCount'] ?></div>
                    <div>Score: <?= $gameState['score'] ?></div>
                    <div> Statut: <?= ucfirst($gameState['status']) ?></div>
                </div>

                <div class="cards-grid cards-<?= count($gameState['cards']) / 2 ?>">
                    <?php foreach ($gameState['cards'] as $card): ?>
                        <div class="card <?= $card['isFlipped'] ? 'flipped' : '' ?> <?= $card['isMatched'] ? 'matched' : '' ?>">
                            <?php if (!$card['isFlipped'] && !$card['isMatched'] && $gameState['status'] === 'playing'): ?>
                                <form method="POST" style="width: 100%; height: 100%;">
                                    <input type="hidden" name="action" value="flip_card">
                                    <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
                                    <button type="submit" class="card-button">
                                        <img src="assets/images/Dos.webp" alt="Carte cachée">
                                    </button>
                                </form>
                            <?php else: ?>
                                <img src="assets/images/<?= $card['image'] ?>" alt="Carte <?= $card['id'] ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="actions">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="reset">
                        <button type="submit"> Nouvelle partie</button>
                    </form>
                    
                    <?php if ($gameState['status'] === 'playing'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="abandon">
                            <button type="submit" class="btn-danger"> Abandonner</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>