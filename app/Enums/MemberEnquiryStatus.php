<?php

namespace App\Enums;

enum MemberEnquiryStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Converted = 'converted';
    case Declined = 'declined';
}
