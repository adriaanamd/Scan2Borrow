<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class BookMutationRequest
{
    /**
     * @param list<string> $keywords
     */
    public function __construct(
        public string $barcode = '',
        public string $title = '',
        public string $accessionNo = '',
        public string $isbn = '',
        public string $author = '',
        public string $publisher = '',
        public string $description = '',
        public string $coverFile = '',
        public string $categoryName = '',
        public string $floorNo = '',
        public string $sectionName = '',
        public string $shelfNo = '',
        public string $rowNo = '',
        public string $dueDate = '',
        public string $returnDate = '',
        public string $status = 'Available',
        public array $keywords = [],
        public int $quantity = 1,
        public int $actorId = 0,
    ) {
    }

    public function withStatus(string $status): self
    {
        return new self(
            $this->barcode,
            $this->title,
            $this->accessionNo,
            $this->isbn,
            $this->author,
            $this->publisher,
            $this->description,
            $this->coverFile,
            $this->categoryName,
            $this->floorNo,
            $this->sectionName,
            $this->shelfNo,
            $this->rowNo,
            $this->dueDate,
            $this->returnDate,
            $status,
            $this->keywords,
            $this->quantity,
            $this->actorId,
        );
    }
}
