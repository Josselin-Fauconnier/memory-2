<?php

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'User.php';

class AuthSystem {
    private $playerDAO;
    
    public function __construct() {
        $this->playerDAO = new PlayerDAO();
    }
    
    public function register($username, $password) {
        try {
            $user = new User($username, $password);
            
            if ($this->playerDAO->findByUsername($username)) {
                throw new Exception("Ce nom d'utilisateur existe déjà");
            }
            
            $userId = $this->playerDAO->createPlayer($username, $user->getPasswordHash());
            $user->setId($userId);
            
            return ['success' => true, 'message' => 'Compte créé avec succès !', 'user' => $user];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function login($username, $password) {
        try {
            $playerData = $this->playerDAO->findByUsername($username);
            
            if (!$playerData) {
                throw new Exception("Nom d'utilisateur ou mot de passe incorrect");
            }
           
            $tempUser = new User($username, 'temp_password', $playerData['id']);
            $tempUser->setPasswordHash($playerData['password_hash']);
            
            if (!password_verify($password, $playerData['password_hash'])) {
                throw new Exception("Nom d'utilisateur ou mot de passe incorrect");
            }
            
            $_SESSION['user_id'] = $playerData['id'];
            $_SESSION['username'] = $playerData['username'];
            $_SESSION['logged_in'] = true;
            
            return ['success' => true, 'message' => 'Connexion réussie !'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function logout() {
        session_destroy();
        return ['success' => true, 'message' => 'Déconnexion réussie'];
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function getCurrentUsername() {
        return $_SESSION['username'] ?? null;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
}


$auth = new AuthSystem();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'register':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if ($password !== $confirmPassword) {
                $error = "Les mots de passe ne correspondent pas";
            } else {
                $result = $auth->register($username, $password);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
            }
            break;
            
        case 'login':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            $result = $auth->login($username, $password);
            if ($result['success']) {
                header('Location: index.php');
                exit;
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'logout':
            $auth->logout();
            header('Location: login.php');
            exit;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memory Game - Connexion</title>
</head>
<body>
    <div class="auth-container">
        <h1> Memory des duelistes </h1>
        
        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div id="login-form" <?= isset($_GET['register']) ? 'style="display:none"' : '' ?>>
            <h2>Connexion</h2>
            <form method="POST">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label for="username">Nom d'utilisateur :</label>
                    <input type="text" name="username" id="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe :</label>
                    <input type="password" name="password" id="password" required>
                </div>
                
                <button type="submit"> Se connecter</button>
            </form>
            
            <div class="toggle-form">
                <p>Pas encore de compte ? <a href="?register=1">Créer un compte</a></p>
                <p><a href="leaderboard.php" style="color: #ccc;">Voir le classement sans inscription </a></p>
            </div>
        </div>
        
        <div id="register-form" <?= !isset($_GET['register']) ? 'style="display:none"' : '' ?>>
            <h2>Créer un compte</h2>
            <form method="POST">
                <input type="hidden" name="action" value="register">
                
                <div class="form-group">
                    <label for="reg_username">Nom d'utilisateur :</label>
                    <input type="text" name="username" id="reg_username" required>
                    <div class="password-requirements">
                        3-25 caractères, lettres, chiffres, tirets et underscores uniquement
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="reg_password">Mot de passe :</label>
                    <input type="password" name="password" id="reg_password" required>
                    <div class="password-requirements">
                        Minimum 12 caractères avec au moins :<br>
                        • Une lettre<br>
                        • Un chiffre<br>
                        • Un caractère spécial (!@#$%^&*...)
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe :</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>
                
                <button type="submit"> Créer le compte</button>
            </form>
            
            <div class="toggle-form">
                <p>Déjà un compte ? <a href="auth.php">Se connecter</a></p>
                <p><a href="leaderboard.php" class="leadB-color">Voir le classement </a></p>
            </div>
        </div>
        
        <div class="join-site">
            <p> Rejoignez le memory game des dueslsites </p>
        </div>
    </div>
</body>
</html>