<?php
require_once 'config/session.php';
require_once 'config/database.php';
require_once 'User.php';

class AuthSystemPDO {
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
    
    public function register(string $username, string $password): array {
        try {
            $user = new User($username, $password);
            
            if ($this->findByUsername($username)) {
                throw new Exception("Ce nom d'utilisateur existe déjà");
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO players (username, password_hash, created_at) 
                VALUES (:username, :password_hash, NOW())
            ");
            
            $stmt->execute([
                'username' => $user->getUsername(),
                'password_hash' => $user->getPasswordHash()
            ]);
            
            $userId = $this->pdo->lastInsertId();
            $user->setId((int)$userId);
            
            return [
                'success' => true, 
                'message' => 'Compte créé avec succès !', 
                'user' => $user
            ];
            
        } catch (InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (Exception $e) {
            error_log("Erreur inscription: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de la création du compte'];
        }
    }
    
    public function login(string $username, string $password): array {
        try {
            $playerData = $this->findByUsername($username);
            
            if (!$playerData) {
                usleep(random_int(100000, 300000));
                throw new Exception("Nom d'utilisateur ou mot de passe incorrect");
            }
            
            if (!password_verify($password, $playerData['password_hash'])) {
                usleep(random_int(100000, 300000));
                throw new Exception("Nom d'utilisateur ou mot de passe incorrect");
            }
            
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $playerData['id'];
            $_SESSION['username'] = $playerData['username'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            return ['success' => true, 'message' => 'Connexion réussie !'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function findByUsername(string $username): ?array {
        $stmt = $this->pdo->prepare("SELECT id, username, password_hash FROM players WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }
    
    public function logout(): array {
        session_unset();
        session_destroy();
        session_start();
        session_regenerate_id(true);
        
        return ['success' => true, 'message' => 'Déconnexion réussie'];
    }
    
    public function isLoggedIn(): bool {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function getCurrentUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function getCurrentUsername(): ?string {
        return $_SESSION['username'] ?? null;
    }
    
    public function requireLogin(): void {
        if (!$this->isLoggedIn()) {
            header('Location: inscription.php');
            exit;
        }
    }
}

function checkRateLimit(string $key, int $maxAttempts = 5, int $timeWindow = 300): bool {
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }
    
    $now = time();
    $attempts = $_SESSION['rate_limit'][$key] ?? [];
    
    $attempts = array_filter($attempts, fn($time) => ($now - $time) < $timeWindow);
    
    if (count($attempts) >= $maxAttempts) {
        return false;
    }
    
    $attempts[] = $now;
    $_SESSION['rate_limit'][$key] = $attempts;
    
    return true;
}

$auth = new AuthSystemPDO();
$message = '';
$error = '';
$currentTab = 'login';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? '';
    
    if (!hash_equals($csrfToken, $submittedToken)) {
        $error = "Token de sécurité invalide";
    } else {
        switch ($action) {
            case 'register':
                $currentTab = 'register';
                $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                
                if (!checkRateLimit("register_$clientIP", 3, 900)) {
                    $error = "Trop de tentatives d'inscription. Réessayez dans 15 minutes.";
                    break;
                }
                
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if (empty($username) || empty($password) || empty($confirmPassword)) {
                    $error = "Tous les champs sont obligatoires";
                } elseif ($password !== $confirmPassword) {
                    $error = "Les mots de passe ne correspondent pas";
                } else {
                    $result = $auth->register($username, $password);
                    if ($result['success']) {
                        $message = $result['message'];
                        $currentTab = 'login';
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        $csrfToken = $_SESSION['csrf_token'];
                    } else {
                        $error = $result['message'];
                    }
                }
                break;
                
            case 'login':
                $currentTab = 'login';
                $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                
                if (!checkRateLimit("login_$clientIP", 5, 300)) {
                    $error = "Trop de tentatives de connexion. Réessayez dans 5 minutes.";
                    break;
                }
                
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                
                if (empty($username) || empty($password)) {
                    $error = "Nom d'utilisateur et mot de passe requis";
                } else {
                    $result = $auth->login($username, $password);
                    if ($result['success']) {
                        header('Location: index.php');
                        exit;
                    } else {
                        $error = $result['message'];
                    }
                }
                break;
                
            case 'switch_tab':
                $currentTab = $_POST['tab'] ?? 'login';
                break;
        }
    }
}

if (isset($_GET['tab']) && in_array($_GET['tab'], ['login', 'register'])) {
    $currentTab = $_GET['tab'];
}

if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$savedUsername = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $savedUsername = htmlspecialchars($_POST['username']);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memory Game - Inscription</title>
    <link rel="stylesheet" href="assets/css/memoryStyle.css">
</head>
<body>
 <nav id="nav-bar">
    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
        <a href="index.php">Accueil</a>
        <a href="leaderboard.php">Classement</a>
        <a href="dueliste.php">Ton profil  </a>
        <a href="deconnection.php">Déconnexion</a>
    <?php else: ?>
        <a href="index.php">Accueil</a>
        <a href="leaderboard.php">Classement</a>
        <a href="inscription-connextion.php">S'inscrire/se connecter</a>
    <?php endif; ?>
</nav>

    <div class="auth-wrapper">
        <div class="auth-tabs">
            <div class="auth-tab <?= $currentTab === 'login' ? 'active' : '' ?>">
                <form method="POST">
                    <input type="hidden" name="action" value="switch_tab">
                    <input type="hidden" name="tab" value="login">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <button type="submit">Connexion</button>
                </form>
            </div>
            <div class="auth-tab <?= $currentTab === 'register' ? 'active' : '' ?>">
                <form method="POST">
                    <input type="hidden" name="action" value="switch_tab">
                    <input type="hidden" name="tab" value="register">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <button type="submit">Inscription</button>
                </form>
            </div>
        </div>
        
        <div class="auth-container">
            <h1 class="text-gold shadow-text">Memory des Duelistes</h1>
            
            <?php if ($message): ?>
                <div class="message theme-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message theme-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($currentTab === 'login'): ?>
            <div class="form-container active">
                <form method="POST" autocomplete="on">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    
                    <div class="form-group">
                        <label for="login_username">Nom d'utilisateur :</label>
                        <input type="text" name="username" id="login_username" 
                               autocomplete="username" required
                               value="<?= $currentTab === 'login' ? $savedUsername : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="login_password">Mot de passe :</label>
                        <input type="password" name="password" id="login_password" 
                               autocomplete="current-password" required>
                    </div>
                    
                    <button type="submit">Se connecter</button>
                </form>
            </div>
            <?php endif; ?>
            
            <?php if ($currentTab === 'register'): ?>
            <div class="form-container active">
                <form method="POST" autocomplete="on">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    
                    <div class="form-group">
                        <label for="reg_username">Nom d'utilisateur :</label>
                        <input type="text" name="username" id="reg_username" 
                               autocomplete="username" required
                               value="<?= $currentTab === 'register' ? $savedUsername : '' ?>">
                        <div class="validation-info">
                            3-25 caractères, lettres, chiffres, tirets et underscores uniquement
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_password">Mot de passe :</label>
                        <input type="password" name="password" id="reg_password" 
                               autocomplete="new-password" required>
                        <div class="validation-info">
                            Minimum 12 caractères avec au moins :<br>
                            • Une lettre • Un chiffre • Un caractère spécial (!@#$%^&*...)
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirmer le mot de passe :</label>
                        <input type="password" name="confirm_password" id="confirm_password" 
                               autocomplete="new-password" required>
                    </div>
                    
                    <button type="submit">Créer le compte</button>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="guest-section">
                <p class="text-secondary">Ou jouer sans compte :</p>
                <a href="index.php" class="btn-guest">
                    Jouer en tant qu'invité
                </a>
            </div>
            
            <div class="toggle-form">
                <p><a href="leaderboard.php" class="leadB-color">Voir le classement</a></p>
            </div>
            
            <div class="join-site">
                <p>Rejoignez le Memory Game des Duelistes</p>
            </div>
        </div>
    </div>
</body>
</html>