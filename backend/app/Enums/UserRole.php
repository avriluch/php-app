<?php

namespace App\Enums;

enum UserRole: string
{
    case Client = 'client';
    case Professional = 'professional';
    case Admin = 'admin';
}
