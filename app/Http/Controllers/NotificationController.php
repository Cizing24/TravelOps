<?php

namespace App\Http\Controllers;

use App\Models\NotificationMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class NotificationController
{
    public function index(Request $request): View
    {
        $user = $this->recipient($request);

        return view('notifications.index', [
            'messages' => NotificationMessage::where('user_id', $user->id)->with('order')->latest('id')->paginate(15),
            'recipient' => $user,
        ]);
    }

    public function read(Request $request, int $message): RedirectResponse
    {
        $user = $this->recipient($request);
        $notice = NotificationMessage::where('user_id', $user->id)->findOrFail($message);
        NotificationMessage::whereKey($notice->id)->whereNull('read_at')->update(['read_at' => now()]);

        return redirect()->route('notifications.index');
    }

    public function preferences(Request $request): RedirectResponse
    {
        $user = $this->recipient($request);
        $request->validate([
            'notification_email' => 'sometimes|boolean', 'notification_line' => 'sometimes|boolean', 'remind_expiry' => 'sometimes|boolean',
        ]);
        $user->forceFill([
            'notification_email' => $request->boolean('notification_email'),
            'notification_line' => $request->boolean('notification_line'),
            'remind_expiry' => $request->boolean('remind_expiry'),
        ])->save();

        return redirect()->route('notifications.index')->with('notice', '通知偏好已儲存。');
    }

    private function recipient(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && ($user->role === 'customer' || $user->operates()), 403);

        return $user;
    }
}
