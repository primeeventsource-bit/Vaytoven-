<?php

namespace App\Enums;

enum Surface: string
{
    case Web = 'web';
    case AppIos = 'app_ios';
    case AppAndroid = 'app_android';
    case Admin = 'admin';
}
