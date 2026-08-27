<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'contentEncoding' => 'nullable|string',
        ]);

        $user = $request->user();
        $user->updatePushSubscription(
            $request->input('endpoint'),
            $request->input('keys.p256dh'),
            $request->input('keys.auth'),
            $request->input('contentEncoding')
        );

        return response()->json(['message' => 'Subscription saved'], 200);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string',
        ]);

        $user = $request->user();
        $user->deletePushSubscription($request->input('endpoint'));

        return response()->json(['message' => 'Subscription deleted'], 200);
    }

    public function latest(Request $request)
    {
        $notification = $request->user()->notifications()->latest()->first();
        if (! $notification) return response()->json(null);
        return response()->json([
            'id' => $notification->id,
            'data' => $notification->data,
            'created_at' => $notification->created_at,
        ]);
    }
}
