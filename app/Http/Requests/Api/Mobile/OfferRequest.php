<?php

namespace App\Http\Requests\Api\Mobile;

use App\Http\Requests\StoreOfferRequest;
use Illuminate\Validation\Rule;

class OfferRequest extends StoreOfferRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['amount_dollars']);
        $rules['amount_cents'] = [Rule::requiredIf(fn () => $this->input('kind') === 'offer'), 'nullable', 'integer', 'min:100', 'max:9999999900'];
        $rules['check_in'][] = 'required_with:check_out';
        $rules['check_out'][] = 'required_with:check_in';

        return $rules;
    }

    public function amountCents(): ?int
    {
        return $this->input('kind') === 'offer' ? $this->integer('amount_cents') : null;
    }
}
