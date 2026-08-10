<?php

namespace App\Http\Controllers;

/**
 * Every action here is scoped through `auth()->user()->notifications()` rather
 * than looking a row up by id and checking it afterwards. The relation IS the
 * authorization: a notification belonging to someone else simply is not in the
 * set, so there is no ownership check that can be forgotten on a new method.
 */
class NotificationController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()->notifications()->paginate(10);

        return response()->json($notifications);
    }

    public function markAsRead($id)
    {
        $notification = auth()->user()->unreadNotifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    public function getUnreadCount()
    {
        return response()->json(['count' => auth()->user()->unreadNotifications->count()]);
    }

    /**
     * Drop a single notification.
     *
     * Answers with the recalculated unread count rather than leaving the client
     * to decrement its own. The bell is seeded server-side on every page load
     * and refreshed on a timer, so a badge derived from local arithmetic would
     * disagree with the next poll whenever a delete raced anything else.
     */
    public function destroy($id)
    {
        $notification = auth()->user()->notifications()->where('id', $id)->first();

        if (! $notification) {
            return response()->json(['success' => false, 'message' => 'Notification not found.'], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'unread_count' => auth()->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Clear everything already read.
     *
     * Deliberately not "clear all". Unread notifications are the ones nobody
     * has looked at yet — a shift shortage, a rejected AI action, a delivery
     * that needs confirming — and a single button that discarded those unseen
     * would lose exactly the messages worth keeping. Reading one is the signal
     * that it can go.
     */
    public function destroyRead()
    {
        $deleted = auth()->user()->readNotifications()->delete();

        return response()->json([
            'success' => true,
            'deleted' => $deleted,
            'unread_count' => auth()->user()->unreadNotifications()->count(),
        ]);
    }
}
