<?php

namespace App\Enums;

enum ParticipantRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
}
