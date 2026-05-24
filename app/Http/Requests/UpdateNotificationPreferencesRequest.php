<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'notify_by_email'                => ['boolean'],
            'notify_by_sms'                  => ['boolean'],
            'phone_number'                   => ['nullable', 'string', 'max:30'],
            'sms_notification_preferences'   => ['nullable', 'array'],
            'sms_notification_preferences.*' => ['string', 'in:round_opened,round_result,bid_placed,invitation,account_event'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.max' => 'Le numéro de téléphone ne doit pas dépasser 30 caractères.',
        ];
    }
}
