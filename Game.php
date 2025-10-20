<?php

require_once 'Card.php';

class Game {
    private int $id;
    private int $playerId;
    private int $pairsCount;
    private int $movesCount;
    private array $cards;
    private array $flippedCards;
    private DateTime $startTime;
    private ?DateTime $endTime;
    private ?int $durationSeconds;
    private string $status; 
    private int $score;
    private int $matchedPairs;

    public function __construct(int $playerId, int $pairsCount) {
        $this->validatePairsCount($pairsCount);
        
        $this->playerId = $playerId;
        $this->pairsCount = $pairsCount;
        $this->movesCount = 0;
        $this->startTime = new DateTime();
        $this->endTime = null;
        $this->durationSeconds = null;
        $this->status = 'playing';
        $this->score = 0;
        $this->matchedPairs = 0;
        $this->flippedCards = [];
        
        $this->initializeCards();
    }

    private function validatePairsCount(int $pairsCount): void {
        if ($pairsCount < 3 || $pairsCount > 12) {
            throw new InvalidArgumentException("Le nombre de paires doit être entre 3 et 12");
        }
    }

    // gestion mélange des cartes
     
    private function initializeCards(): void {
        $images = Card::getImagesForGame($this->pairsCount);
        $this->cards = [];
        $cardId = 0;

        
        foreach ($images as $image) {
            $this->cards[] = new Card($cardId++, $image);
            $this->cards[] = new Card($cardId++, $image);
        }

        $this->shuffleCards();
    }

    private function shuffleCards(): void {
       shuffle($this->cards);
    }

    // Gestion des cartes lors du jeu 

    public function getCard(int $cardId): ?Card {
        foreach ($this->cards as $card) {
            if ($card->getId() === $cardId) {
                return $card;
            }
        }
        return null;
    }

    
    public function flipCard(int $cardId): array {
        if ($this->status !== 'playing') {
            return ['success' => false, 'message' => 'Partie terminée'];
        }

        $card = $this->getCard($cardId);
        if (!$card) {
            return ['success' => false, 'message' => 'Carte introuvable'];
        }

        if (!$card->canBeFlipped()) {
            return ['success' => false, 'message' => 'Cette carte ne peut pas être retournée'];
        }

        if (count($this->flippedCards) >= 2) {
            $this->hideFlippedCards();
        }

        $card->flip();
        $this->flippedCards[] = $cardId;
        $this->movesCount++;

        if (count($this->flippedCards) === 2) {
            return $this->checkForMatch();
        }

        return ['success' => true, 'message' => 'Carte retournée'];
    }

    
    private function hideFlippedCards(): void {
        foreach ($this->flippedCards as $cardId) {
            $card = $this->getCard($cardId);
            if ($card && !$card->isMatched()) {
                $card->hide();
            }
        }
        $this->flippedCards = [];
    }

    
    private function checkForMatch(): array {
        if (count($this->flippedCards) !== 2) {
            return ['success' => false, 'message' => 'Erreur interne'];
        }

        $card1 = $this->getCard($this->flippedCards[0]);
        $card2 = $this->getCard($this->flippedCards[1]);

        if (!$card1 || !$card2) {
            return ['success' => false, 'message' => 'Erreur: carte introuvable'];
        }

        if ($card1->matches($card2)) {
            $card1->match();
            $card2->match();
            $this->matchedPairs++;
            $this->flippedCards = [];

            if ($this->matchedPairs === $this->pairsCount) {
                $this->completeGame();
                return [
                    'success' => true, 
                    'message' => 'Paire trouvée! Jeu terminé!',
                    'gameCompleted' => true,
                    'finalScore' => $this->score
                ];
            }

            return ['success' => true, 'message' => 'Paire trouvée!', 'match' => true];
        }

        return ['success' => true, 'message' => 'Pas de match', 'match' => false];
    }


    // Gestion fin de partie 
   
    private function completeGame(): void {
        $this->endTime = new DateTime();
        $this->durationSeconds = $this->endTime->getTimestamp() - $this->startTime->getTimestamp();
        $this->status = 'completed';
        $this->calculateScore();
    }

   
    private function calculateScore(): void {
        if ($this->status !== 'completed') {
            $this->score = 0;
            return;
        }

        $baseScore = 100;

        $minMoves = $this->pairsCount * 2;
        
        $movePenalty = max(0, ($this->movesCount - $minMoves) * 10);

        $timeBonus = 0;
        if ($this->durationSeconds > 0) {
            $referenceTime = $this->pairsCount * 2;
            if ($this->durationSeconds <= $referenceTime) {
                $timeBonus = min(200, (($referenceTime - $this->durationSeconds) / $referenceTime) * 200);
            }
        }

        $difficultyBonus = ($this->pairsCount - 3) * 25;

        $this->score = max(0, (int)($baseScore - $movePenalty + $timeBonus + $difficultyBonus));
    }

   
    public function abandonGame(): void {
        if ($this->status === 'playing') {
            $this->endTime = new DateTime();
            $this->durationSeconds = $this->endTime->getTimestamp() - $this->startTime->getTimestamp();
            $this->status = 'abandoned';
            $this->score = 0;
        }
    }

  
    public function getGameState(): array {
        return [
            'playerId' => $this->playerId,
            'pairsCount' => $this->pairsCount,
            'movesCount' => $this->movesCount,
            'matchedPairs' => $this->matchedPairs,
            'status' => $this->status,
            'score' => $this->score,
            'duration' => $this->durationSeconds,
            'cards' => array_map(fn($card) => $card->toArray(), $this->cards),
            'flippedCardsCount' => count($this->flippedCards)
        ];
    }

    public function toArray(): array {
        return [
            'id' => $this->id ?? 0,
            'playerId' => $this->playerId,
            'pairsCount' => $this->pairsCount,
            'movesCount' => $this->movesCount,
            'startTime' => $this->startTime->format('Y-m-d H:i:s'),
            'endTime' => $this->endTime?->format('Y-m-d H:i:s'),
            'durationSeconds' => $this->durationSeconds,
            'status' => $this->status,
            'score' => $this->score,
            'matchedPairs' => $this->matchedPairs,
            'flippedCards' => $this->flippedCards,
            'cards' => array_map(fn($card) => $card->toArray(), $this->cards)
        ];
    }

   
    public static function fromArray(array $data): Game {
        $game = new self($data['playerId'], $data['pairsCount']);
        
        $game->id = $data['id'] ?? 0;
        $game->movesCount = $data['movesCount'];
        $game->startTime = new DateTime($data['startTime']);
        $game->endTime = $data['endTime'] ? new DateTime($data['endTime']) : null;
        $game->durationSeconds = $data['durationSeconds'];
        $game->status = $data['status'];
        $game->score = $data['score'];
        $game->matchedPairs = $data['matchedPairs'];
        $game->flippedCards = $data['flippedCards'] ?? [];
        
       
        $game->cards = [];
        foreach ($data['cards'] as $cardData) {
            $game->cards[] = Card::fromArray($cardData);
        }
        
        return $game;
    }

   
    public function getId(): int { return $this->id ?? 0; }
    public function getPlayerId(): int { return $this->playerId; }
    public function getPairsCount(): int { return $this->pairsCount; }
    public function getMovesCount(): int { return $this->movesCount; }
    public function getStatus(): string { return $this->status; }
    public function getScore(): int { return $this->score; }
    public function getMatchedPairs(): int { return $this->matchedPairs; }
    public function getCards(): array { return $this->cards; }
    public function getDurationSeconds(): ?int { return $this->durationSeconds; }
    public function getStartTime(): DateTime { return $this->startTime; }
    public function getEndTime(): ?DateTime { return $this->endTime; }

  
    public function setId(int $id): void { $this->id = $id; }
}