<?php

namespace App\Http\Middleware;

use Closure;
use Kreait\Firebase\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class VerifyFirebaseToken
{
    protected $auth;

    public function __construct(FirebaseAuth $auth)
    {
        $this->auth = $auth;
    }

    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'No token provided'], 401);
        }

        try {
            $verifiedIdToken = $this->auth->verifyIdToken($token);
            $uid = $verifiedIdToken->claims()->get('sub');

            // Add user info to request for later use
            $request->merge(['firebase_uid' => $uid]);

            return $next($request);
        } catch (FailedToVerifyToken $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
    }
}
