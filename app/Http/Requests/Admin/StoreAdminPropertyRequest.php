<?php

namespace App\Http\Requests\Admin;

use App\Enums\PropertyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminPropertyRequest extends FormRequest
{
    /**
     * The states a listing may be created in. Deliberately excludes active.
     *
     * @var list<string>
     */
    public const CREATABLE_STATUSES = ['draft', 'pending_review'];

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('properties.create') ?? false;
    }

    public function rules(): array
    {
        // Two ways to say whose listing this is: pick an existing account, or
        // supply an email and have one created.
        $forNewOwner = $this->input('owner_mode') === 'new';

        return [
            'owner_mode'      => ['required', 'in:existing,new'],

            'host_id'         => [Rule::requiredIf(! $forNewOwner), 'nullable', 'exists:users,id'],

            'owner_email'     => [Rule::requiredIf($forNewOwner), 'nullable', 'email:rfc', 'max:255'],
            'owner_first_name'=> [Rule::requiredIf($forNewOwner), 'nullable', 'string', 'max:80'],
            'owner_last_name' => [Rule::requiredIf($forNewOwner), 'nullable', 'string', 'max:80'],
            'owner_phone'     => ['nullable', 'string', 'max:40'],

            'title'           => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string', 'max:5000'],

            'city'            => ['nullable', 'string', 'max:128'],
            'region'          => ['nullable', 'string', 'max:128'],
            'country'         => ['nullable', 'string', 'size:2'],
            'address_line'    => ['nullable', 'string', 'max:255'],
            'postal_code'     => ['nullable', 'string', 'max:32'],

            'capacity'        => ['required', 'integer', 'min:1', 'max:99'],
            'bedrooms'        => ['required', 'integer', 'min:0', 'max:99'],
            'beds'            => ['required', 'integer', 'min:0', 'max:99'],
            'bathrooms'       => ['required', 'numeric', 'min:0', 'max:99'],

            // Dollars in the form, converted to integer cents before storage.
            'price_dollars'   => ['required', 'numeric', 'min:0', 'max:100000000'],
            'listing_type'    => ['required', Rule::enum(\App\Enums\ListingType::class)],

            'minimum_nights'  => ['nullable', 'integer', 'min:1', 'max:365'],

            // A new listing cannot be created live.
            //
            // Photos are attached after the listing exists — there is nowhere
            // to put them until it has an id — so "create as active" published
            // an advertisement with no pictures every single time. Six real
            // members' ads went out that way before anybody noticed.
            //
            // Going live is the transition endpoint's job, which already
            // refuses until ListingReadiness is satisfied. This makes creation
            // incapable of skipping that check rather than trusting nobody
            // will pick the wrong option.
            'status'          => ['required', Rule::in(self::CREATABLE_STATUSES)],
            'listing_source'  => ['required', 'in:host,managed'],

            'notify_owner'    => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'host_id.required'      => 'Choose the account this listing belongs to.',
            'owner_email.required'  => 'Enter the email address for the new account.',
            'country.size'          => 'Use the two-letter country code, e.g. US.',
            'status.in'             => 'A new listing cannot be created as active. Create it as a draft, '
                .'add the photos and availability, then activate it from the listing page — that check '
                .'is what stops an advertisement going out with no pictures.',
        ];
    }
}
