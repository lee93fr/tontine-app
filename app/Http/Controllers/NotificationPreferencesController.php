<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotificationPreferencesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationPreferencesController extends Controller
{
    public function edit(): View
    {
        return view('notifications.preferences', [
            'user' => auth()->user(),
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $user = auth()->user();

        $user->update([
            'notify_by_email'              => $request->boolean('notify_by_email'),
            'notify_by_sms'                => $request->boolean('notify_by_sms'),
            'phone_number'                 => $request->input('phone_number'),
            'sms_notification_preferences' => array_values($request->input('sms_notification_preferences', [])),
        ]);

        return redirect()->back()->with('success', 'Préférences de notifications mises à jour.');
    }
}
