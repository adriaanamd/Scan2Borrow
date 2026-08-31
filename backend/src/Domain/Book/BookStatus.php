<?php

declare(strict_types=1);

namespace App\Domain\Book;

enum BookStatus: string
{
    case AVAILABLE = 'Available';
    case BORROWED = 'Borrowed';
    case RESERVED = 'Reserved';
    case LOST = 'Lost';
    case DAMAGED = 'Damaged';
}
