<?php

namespace App\Enums;

enum PaymentProcessor: string
{
    case Stripe = 'stripe';
    case AuthorizeNet = 'authorizenet';
    case Nmi = 'nmi';
    case Nuvei = 'nuvei';
    case Mes = 'mes';                 // Merchant E Solutions
    case PaymentCloud = 'paymentcloud';
    case Ems = 'ems';
    case Nexio = 'nexio';
    case Netevia = 'netevia';
    case Kurv = 'kurv';
}
