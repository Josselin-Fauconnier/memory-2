<?php

class Card 
{
    private int $id;
    private string $imagePath;
    private bool $isFlipped;
    private bool $isMatched;
    private int $pairId;
    
    public function __construct(int $id, string $imagePath, int $pairId)
    {
        $this->id = $id;
        $this->imagePath = $this->sanitizeImagePath($imagePath);
        $this->pairId = $pairId;
        $this->isFlipped = false;
        $this->isMatched = false;
    }
    
    
    private function sanitizeImagePath(string $imagePath): string
    {
        $cleanPath = trim($imagePath);
        $cleanPath = preg_replace('/[^a-zA-Z0-9\-_.]/', '', $cleanPath);
        
        if (empty($cleanPath)) {
            throw new InvalidArgumentException("Le chemin de l'image ne peut pas être vide");
        }
        
        if (!preg_match('/\.svg$/i', $cleanPath)) {
            throw new InvalidArgumentException("Seuls les fichiers SVG sont autorisés");
        }
        
        return $cleanPath;
    }
    
    public function flip(): bool
    {
        if ($this->isMatched) {
            return false;
        }
        
        $this->isFlipped = !$this->isFlipped;
        return true;
    }
    
    public function hide(): void
    {
        if (!$this->isMatched) {
            $this->isFlipped = false;
        }
    }
    
    public function setMatched(): void
    {
        $this->isMatched = true;
        $this->isFlipped = true;
    }
    
    public function isPairWith(Card $otherCard): bool
    {
        return $this->pairId === $otherCard->getPairId() && $this->id !== $otherCard->getId();
    }
    
    public function renderHtml(): string
    {
        $cssClasses = ['card'];
        
        if ($this->isFlipped && !$this->isMatched) {
            $cssClasses[] = 'flipped';
        }
        
        if ($this->isMatched) {
            $cssClasses[] = 'matched';
        }
        
        if (!$this->isFlipped && !$this->isMatched) {
            $cssClasses[] = 'clickable';
        }
        
        $classAttribute = implode(' ', $cssClasses);
        $disabled = ($this->isFlipped || $this->isMatched) ? 'disabled' : '';
        
        $html = '<button class="card-button" data-card-id="' . $this->id . '" ' . $disabled . '>';
        $html .= '<div class="' . $classAttribute . '">';
        
        if ($this->isFlipped || $this->isMatched) {
            $html .= '<img src="images/' . htmlspecialchars($this->imagePath, ENT_QUOTES, 'UTF-8') . '" alt="Carte ' . $this->pairId . '">';
        }
        
        $html .= '</div>';
        $html .= '</button>';
        
        return $html;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'imagePath' => $this->imagePath,
            'pairId' => $this->pairId,
            'isFlipped' => $this->isFlipped,
            'isMatched' => $this->isMatched
        ];
    }
    
    public static function fromArray(array $data): Card
    {
        $card = new self($data['id'], $data['imagePath'], $data['pairId']);
        $card->isFlipped = $data['isFlipped'];
        $card->isMatched = $data['isMatched'];
        
        return $card;
    }
    

    // getters

    public function getId(): int
    {
        return $this->id;
    }
    
    public function getImagePath(): string
    {
        return $this->imagePath;
    }
    
    public function getPairId(): int
    {
        return $this->pairId;
    }
    
    public function isFlipped(): bool
    {
        return $this->isFlipped;
    }
    
    public function isMatched(): bool
    {
        return $this->isMatched;
    }
    

    // Setters

    public function setId(int $id): void
    {
        $this->id = $id;
    }
    
    public function setImagePath(string $imagePath): void
    {
        $this->imagePath = $this->sanitizeImagePath($imagePath);
    }
    
    public function setPairId(int $pairId): void
    {
        $this->pairId = $pairId;
    }
    
    public function setFlipped(bool $isFlipped): void
    {
        if (!$this->isMatched) {
            $this->isFlipped = $isFlipped;
        }
    }
    
    public function isClickable(): bool
    {
        return !$this->isFlipped && !$this->isMatched;
    }
    
    public function __toString(): string
    {
        $status = [];
        if ($this->isFlipped) $status[] = 'retournée';
        if ($this->isMatched) $status[] = 'trouvée';
        if (empty($status)) $status[] = 'cachée';
        
        return "Carte #{$this->id} (paire {$this->pairId}) : " . implode(', ', $status);
    }
}