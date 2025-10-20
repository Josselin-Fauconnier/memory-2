<?php

class User {
    private int $id;
    private string $username;
    private string $passwordHash;
    private DateTime $createdAt;
    
  
    private int $totalGames;
    private int $completedGames;
    private int $bestScore;
    private ?int $averageScore;
    private ?int $bestDuration;

    public function __construct(string $username, string $password, ?int $id = null) {
        $this->validateUsername($username);
        $this->validatePassword($password);
        
        $this->id = $id ?? 0;
        $this->username = $this->sanitizeUsername($username);
        $this->passwordHash = $this->hashPassword($password);
        $this->createdAt = new DateTime();
    
        $this->totalGames = 0;
        $this->completedGames = 0;
        $this->bestScore = 0;
        $this->averageScore = null;
        $this->bestDuration = null;
    }

    // Sécurisation utilisateur 
    
    private function validateUsername(string $username): void {
        $cleanUsername = trim($username);
        
        if (empty($cleanUsername)) {
            throw new InvalidArgumentException("Le nom d'utilisateur ne peut pas être vide");
        }
        
        if (strlen($cleanUsername) < 3) {
            throw new InvalidArgumentException("Le nom d'utilisateur doit contenir au moins  3 caractères");
        }
        
        if (strlen($cleanUsername) > 25) {
            throw new InvalidArgumentException("Le nom d'utilisateur ne peut pas dépasser 25 caractères");
        }
        
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $cleanUsername)) {
            throw new InvalidArgumentException("Le nom d'utilisateur ne peut contenir que des lettres, chiffres, tirets et underscores");
        }
    }

    // sécurisation MDP

    private function validatePassword(string $password): void {
        if (strlen($password) < 12) {
            throw new InvalidArgumentException("Le mot de passe doit contenir au moins 12 caractères");
        }
        
        if (strlen($password) > 60) {
            throw new InvalidArgumentException("Le mot de passe ne peut pas dépasser 60 caractères");
        }
        
        
        if (!preg_match('/[A-Za-z]/', $password)) {
            throw new InvalidArgumentException("Le mot de passe doit contenir au moins une lettre");
        }
        
       
        if (!preg_match('/[0-9]/', $password)) {
            throw new InvalidArgumentException("Le mot de passe doit contenir au moins un chiffre");
        }
        
        
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            throw new InvalidArgumentException("Le mot de passe doit contenir au moins un caractère spécial (!@#$%^&*()_+-=[]{}|;':\",./<>?)");
        }
    }

    
    private function sanitizeUsername(string $username): string {
        return htmlspecialchars(trim($username), ENT_QUOTES, 'UTF-8');
    }

 
    private function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

   
    public function verifyPassword(string $password): bool {
        return password_verify($password, $this->passwordHash);
    }

    
    public function updatePassword(string $currentPassword, string $newPassword): bool {
        if (!$this->verifyPassword($currentPassword)) {
            throw new InvalidArgumentException("Mot de passe actuel incorrect");
        }
        
        $this->validatePassword($newPassword);
        $this->passwordHash = $this->hashPassword($newPassword);
        return true;
    }

    // stats 

    public function updateStats(Game $game): void {
        $this->totalGames++;
        
        if ($game->getStatus() === 'completed') {
            $this->completedGames++;
            
            
            if ($game->getScore() > $this->bestScore) {
                $this->bestScore = $game->getScore();
            }
            
            $duration = $game->getDurationSeconds();
            if ($duration !== null) {
                if ($this->bestDuration === null || $duration < $this->bestDuration) {
                    $this->bestDuration = $duration;
                }
            }
        }
    }

  
    public function calculateAverageStats(array $completedGames): void {
        if (empty($completedGames)) {
            $this->averageScore = null;
            return;
        }
        
        $totalScore = 0;
        $totalMoves = 0;
        $gameCount = count($completedGames);
        
        foreach ($completedGames as $gameData) {
            $totalScore += $gameData['score'] ?? 0;
            $totalMoves += $gameData['moves_count'] ?? 0;
        }
        
        $this->averageScore = (int)round($totalScore / $gameCount);
    }

    
    public function getSuccessRate(): float {
        if ($this->totalGames === 0) {
            return 0.0;
        }
        
        return round(($this->completedGames / $this->totalGames) * 100, 1);
    }

    
    public function getLevel(): int {
        
        return max(1, (int)floor($this->completedGames / 10) + 1);
    }

    public function getExperienceToNextLevel(): int {
        $currentLevel = $this->getLevel();
        $gamesForNextLevel = $currentLevel * 10;
        return max(0, $gamesForNextLevel - $this->completedGames);
    }

  
    public function getRank(): string {
        $level = $this->getLevel();
        $successRate = $this->getSuccessRate();
        $bestScore = $this->bestScore;
        
        
        if ($level >= 15 && $successRate >= 95 && $bestScore >= 2000) {
            return "Pharaon du Millénaire";
        } elseif ($level >= 12 && $successRate >= 90 && $bestScore >= 1800) {
            return "Roi des jeux";
        } elseif ($level >= 10 && $successRate >= 85 && $bestScore >= 1600) {
            return "Maître des cartes";
        }
        
        elseif ($level >= 8 && $successRate >= 80) {
            return "Champion de l'îte des duelistes ";
        } elseif ($level >= 6 && $successRate >= 75) {
            return "Champion Régional";
        } elseif ($level >= 5 && $successRate >= 70) {
            return "Duelliste Expert";
        }
        
        elseif ($level >= 4 && $successRate >= 65) {
            return "Duelliste Confirmé";
        } elseif ($level >= 3 && $successRate >= 60) {
            return "Apprenti Duelliste";
        }
        elseif ($this->completedGames >= 10) {
            return "Élève de l'Académie";
        } elseif ($this->completedGames >= 5) {
            return "Débutant Prometteur";
        } else {
            return "Nouveau Duelliste";
        }
    }

    // Gestion affichage profil 

    public function getProfile(): array {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'level' => $this->getLevel(),
            'rank' => $this->getRank(),
            'totalGames' => $this->totalGames,
            'completedGames' => $this->completedGames,
            'successRate' => $this->getSuccessRate(),
            'bestScore' => $this->bestScore,
            'averageScore' => $this->averageScore,
            'bestDuration' => $this->bestDuration,
            'experienceToNextLevel' => $this->getExperienceToNextLevel(),
        ];
    }

    
    public function toArray(): array {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'password_hash' => $this->passwordHash,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'total_games' => $this->totalGames,
            'completed_games' => $this->completedGames,
            'best_score' => $this->bestScore,
            'average_score' => $this->averageScore,
            'best_duration' => $this->bestDuration
        ];
    }

    
    public static function fromArray(array $data): User {
        $user = new self($data['username'], 'temporary_password', $data['id']);
        
        $user->passwordHash = $data['password_hash'];
        $user->createdAt = new DateTime($data['created_at']);
        
        
        $user->totalGames = $data['total_games'] ?? 0;
        $user->completedGames = $data['completed_games'] ?? 0;
        $user->bestScore = $data['best_score'] ?? 0;
        $user->averageScore = $data['average_score'] ?? null;
        $user->bestDuration = $data['best_duration'] ?? null;
        
        return $user;
    }

    
    public static function isValidUsername(string $username): bool {
        try {
            $tempUser = new self($username, 'TempPassword123!');
            return true;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    public static function isValidPassword(string $password): bool {
        try {
            $tempUser = new self('tempuser', $password);
            return true;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    
    public static function generateSecureToken(): string {
        return bin2hex(random_bytes(32));
    }

    // Compare deux utilisateurs pour classement
     
    public function compareTo(User $other): int {
        
        if ($this->bestScore !== $other->bestScore) {
            return $other->bestScore - $this->bestScore; 
        }
        
        $thisRate = $this->getSuccessRate();
        $otherRate = $other->getSuccessRate();
        if ($thisRate !== $otherRate) {
            return $otherRate <=> $thisRate; 
        }
        
        return $other->completedGames - $this->completedGames;
    }

    // Getters

    public function getId(): int { return $this->id; }
    public function getUsername(): string { return $this->username; }
    public function getPasswordHash(): string { return $this->passwordHash; }
    public function getCreatedAt(): DateTime { return $this->createdAt; }
    public function getTotalGames(): int { return $this->totalGames; }
    public function getCompletedGames(): int { return $this->completedGames; }
    public function getBestScore(): int { return $this->bestScore; }
    public function getAverageScore(): ?int { return $this->averageScore; }
    public function getBestDuration(): ?int { return $this->bestDuration; }

    // Setters pour base de données
    
    public function setId(int $id): void { $this->id = $id; }
    public function setTotalGames(int $count): void { $this->totalGames = $count; }
    public function setCompletedGames(int $count): void { $this->completedGames = $count; }
    public function setBestScore(int $score): void { $this->bestScore = $score; }
    public function setAverageScore(?int $score): void { $this->averageScore = $score; }
    public function setBestDuration(?int $duration): void { $this->bestDuration = $duration; }
}