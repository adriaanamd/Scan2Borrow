<?php

declare(strict_types=1);

namespace App\Domain\Auth;

enum Role: string
{
    case ADMIN = 'admin';
    case LIBRARIAN = 'librarian';
    case STUDENT = 'student';
    case TEACHER = 'teacher';
    case GUEST = 'guest';
}
