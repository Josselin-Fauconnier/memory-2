<?php
require_once 'config/database.php';

class GameDAO {
    private PDO $pdo;
    
    public function __construct() {
        $config = getDbConfiguration($GLOBALS['db_configuration']);
        $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
        
        $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    }
    
    public function saveGame(Game $game): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO games (player_id, pairs_count, moves_count, start_time, end_time, 
                             duration_seconds, status, score) 
            VALUES (:player_id, :pairs_count, :moves_count, :start_time, :end_time, 
                    :duration_seconds, :status, :score)
        ");
        
        $stmt->execute([
            'player_id' => $game->getPlayerId(),
            'pairs_count' => $game->getPairsCount(),
            'moves_count' => $game->getMovesCount(),
            'start_time' => $game->getStartTime()->format('Y-m-d H:i:s'),
            'end_time' => $game->getEndTime()?->format('Y-m-d H:i:s'),
            'duration_seconds' => $game->getDurationSeconds(),
            'status' => $game->getStatus(),
            'score' => $game->getScore()
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }
}
?>