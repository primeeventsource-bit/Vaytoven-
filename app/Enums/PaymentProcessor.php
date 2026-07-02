<?php

namespace App\Enums;

enum PaymentProcessor: string
{
    /**
     * @deprecated Stripe was replaced by NMI (2026-07). The case remains so
     * historical rows with processor='stripe' still cast; no new writes.
     */
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
