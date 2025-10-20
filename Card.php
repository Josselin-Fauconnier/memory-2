<?php

class Card {
    private int $id;
    private string $image;

    private static array $availableImages = [
        'DinomorphiaAlert.webp',
        'DinomorphiaBrute.webp',
        'DinomorphiaDiplos.webp',
        'DinomorphiaDomain.webp',
        'DinomorphiaFrenzy.webp',
        'DinomorphiaIntact.webp',
        'DinomorphiaKentregina.webp',
        'DinomorphiaReversion.webp',
        'DinomorphiaRexterm.webp',
        'DinomorphiaShell.webp',
        'DinomorphiaSonic.webp',
        'DinomorphiaStealthbergia.webp'
    ];
    

    private bool $isFlipped = false;
    private bool $isMatched = false;

    public function __construct( int $id, string $image){
        if($id < 0){
            throw new InvalidArgumentException("l'id doit être un entier positif");
        }

        $this->id = $id;
        $this->image = $this->validateImage($image);
        $this->isFlipped = false;
        $this->isMatched = false;
    }

    private function validateImage(string $image) : string{
         $cleanImage = preg_replace('/[^a-zA-Z0-9._-]/', '', $image);
        
        if (in_array($cleanImage, self::$availableImages)) {
            return $cleanImage;
        }
        
        error_log("Image non autorisée tentée: '{$image}'. Images autorisées: " . implode(', ', self::$availableImages));
        return self::$availableImages[0]; 
    }

    public function flip() : bool {
        if($this->isMatched){
            return false;
        }
        $this->isFlipped = !$this->isFlipped;
        return true;
    }

    public function match() : void {
        $this->isMatched = true;
        $this->isFlipped = true;
    }

    public function hide() : bool {
        if($this->isMatched){
            return false;
        }
        $this->isFlipped = false;
        return true;
    }

    //getters

    public function getId() : int {
        return $this->id;
    }

    public function getImage() : string {
        return $this->image;
    }

    public function isFlipped() : bool {
        return $this->isFlipped;
    }

    public function isMatched() : bool {
        return $this->isMatched;
    }

    public function getImagePath() : string {
        return "assets/images/" . $this->image;
    }
    
    public function matches(Card $otherCard) : bool {
        return $this->image === $otherCard->getImage();
    }

     public function canBeFlipped(): bool 
    {
        return !$this->isMatched && !$this->isFlipped;
    }


    // Pour HTLM/CSS

  public function renderHtml(): string 
    {
        $cssClass = $this->getCssClasses();
        $imageSrc = $this->getDisplayImage();
        $clickable = $this->canBeFlipped() ? '' : 'disabled';
        
        return sprintf(
            '<div class="card %s" data-card-id="%d" %s>
                <img src="%s" alt="Carte %d" />
            </div>',
            $cssClass,
            $this->id,
            $clickable,
            $imageSrc,
            $this->id
        );
    }

    private function getCssClasses() : string {
        $classes = [];

        if($this->isFlipped){
            $classes[] = 'flipped';
        }

        if($this->isMatched){
            $classes[] = 'matched';
        }

        if($this->canBeFlipped()){
            $classes[] = 'clickable';
        }

        return implode('', $classes);
    }

    private function getDisplayImage() : string {
        if($this->isFlipped || $this->isMatched){
            return $this->getImagePath();
        }else{
            return "assets/images/Dos.webp";
        }
    }



      public function renderClickableForm(): string 
    {
        if (!$this->canBeFlipped()) {
            return $this->renderHtml(); 
        }
        
        return sprintf(
            '<form method="POST" action="index.php" style="display:inline;">
                <input type="hidden" name="action" value="flip_card">
                <input type="hidden" name="card_id" value="%d">
                <button type="submit" class="card-button">
                    %s
                </button>
            </form>',
            $this->id,
            $this->renderHtml()
        );
    }
    


    // gestion Array
    public function toArray() : array {
        return [
            'id' => $this->id,
            'image' => $this->image,
            'isFlipped' => $this->isFlipped,
            'isMatched' => $this->isMatched,
        ];
    }

    public static function fromArray(array $data) : Card {
        if(isset($data['id']) && !isset($data['image'])){
            throw new InvalidArgumentException("donnés invalides pour la restituion des cartes");
        }

        $card = new self($data['id'],$data['image']);
        $card->isFlipped = $data['isFlipped'] ?? false;
        $card->isMatched = $data['isMatched'] ?? false;

        return $card;
    }


    // gestion des images 

    public static function getAvailableImages() : array {
        return self::$availableImages;
    }

    public static function getRandomImage(): string 
    {
        $randomIndex = random_int(0, count(self::$availableImages) - 1);
        return self::$availableImages[$randomIndex];
    }

    public static function getImagesForGame(int $count): array 
    {
        if ($count > count(self::$availableImages)) {
            throw new InvalidArgumentException(
                "Pas assez d'images disponibles. Demandé: {$count}, Disponible: " . count(self::$availableImages)
            );
        }
        
        return array_slice(self::$availableImages, 0, $count);
    }

     public static function addImage(string $imageName): bool 
    {
        $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '', $imageName);
        
        if (!empty($cleanName) && !in_array($cleanName, self::$availableImages)) {
            self::$availableImages[] = $cleanName;
            return true;
        }
        
        return false;
    }
}
