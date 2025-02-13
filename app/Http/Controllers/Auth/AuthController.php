<?php

namespace App\Http\Controllers\Auth;

use Log;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Auth as FirebaseAuth;

class AuthController extends Controller
{
    protected $auth;

    public function __construct(FirebaseAuth $auth)
    {
        $this->auth = $auth;
    }

    public function register(Request $request)
    {
        // Check if it's a Google registration
        if ($request->has('idToken')) {
            return $this->handleGoogleRegister($request);
        }

        // Regular email/password registration
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        try {
            // Create user in Firebase
            $userProperties = [
                'email' => $request->email,
                'emailVerified' => false,
                'password' => $request->password,
                'displayName' => $request->name,
            ];

            $firebaseUser = $this->auth->createUser($userProperties);

            // Create user in your database
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'firebase_uid' => $firebaseUser->uid
            ]);

            // Create a custom token for the user
            $customToken = $this->auth->createCustomToken($firebaseUser->uid);

            return response()->json([
                'message' => 'Successfully registered',
                'user' => $user,
                'firebase_token' => $customToken->toString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Registration failed',
                'message' => $e->getMessage()
            ], 401);
        }
    }

    protected function handleGoogleRegister(Request $request)
    {
        try {
            // Verify the Firebase token
            $idTokenString = $request->input('idToken');
            $verifiedIdToken = $this->auth->verifyIdToken($idTokenString);

            $uid = $verifiedIdToken->claims()->get('sub');
            $firebaseUser = $this->auth->getUser($uid);

            // Check if user already exists
            $existingUser = User::where('email', $firebaseUser->email)->first();

            if ($existingUser) {
                return response()->json([
                    'message' => 'User already exists',
                    'user' => $existingUser,
                    'access_token' => $idTokenString
                ]);
            }

            // Create new user
            $user = User::create([
                'name' => $firebaseUser->displayName,
                'email' => $firebaseUser->email,
                'password' => Hash::make(Str::random(16)),
                'email_verified_at' => now(),
                'firebase_uid' => $uid
            ]);

            return response()->json([
                'message' => 'Successfully registered',
                'user' => $user,
                'access_token' => $idTokenString
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Registration failed',
                'message' => $e->getMessage()
            ], 401);
        }
    }

    public function login(Request $request)
    {
        // Check if it's a Google login
        if ($request->has('idToken')) {
            return $this->handleGoogleLogin($request);
        }

        // Regular email/password login
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        try {
            // Find user in your database
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'error' => 'Invalid credentials'
                ], 401);
            }

            // Get Firebase UID
            if (!$user->firebase_uid) {
                // Create Firebase user if not exists
                $firebaseUser = $this->auth->createUser([
                    'email' => $request->email,
                    'password' => $request->password,
                ]);

                $user->firebase_uid = $firebaseUser->uid;
                $user->save();
            }

            // Generate custom token for Firebase
            $customToken = $this->auth->createCustomToken($user->firebase_uid);

            return response()->json([
                'user' => $user,
                'firebase_token' => $customToken->toString(),
                'message' => 'Successfully logged in'
            ]);
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Login failed',
                'message' => $e->getMessage()
            ], 401);
        }
    }

    protected function handleGoogleLogin(Request $request)
    {
        try {
            $idTokenString = $request->input('idToken');

            // Verify the Firebase token
            $verifiedIdToken = $this->auth->verifyIdToken($idTokenString);

            $uid = $verifiedIdToken->claims()->get('sub');
            $email = $verifiedIdToken->claims()->get('email');
            $name = $verifiedIdToken->claims()->get('name');

            // Get or create user
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name ?? explode('@', $email)[0],
                    'password' => Hash::make(Str::random(16)),
                    'firebase_uid' => $uid,
                    'email_verified_at' => now(),
                ]
            );

            return response()->json([
                'user' => $user,
                'access_token' => $idTokenString,
                'message' => 'Successfully logged in'
            ]);
        } catch (\Exception $e) {
            Log::error('Google login error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Unauthorized',
                'message' => $e->getMessage()
            ], 401);
        }
    }


    public function user(Request $request)
    {
        try {
            $firebase_uid = $request->firebase_uid;
            $firebaseUser = $this->auth->getUser($firebase_uid);

            // Get user from your database
            $user = User::where('firebase_uid', $firebase_uid)->first();

            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            // Add profile image URL if exists
            if ($user->profile_image) {
                $user->profile_image_url = Storage::url($user->profile_image);
            }

            return response()->json([
                'user' => $user,
                'firebase_uid' => $firebase_uid
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'User not found', 'message' => $e->getMessage()], 404);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'firebase_uid' => 'required|string',
                'name' => 'sometimes|string|max:255',
                'password' => 'sometimes|string|min:8',
                'date_of_birth' => 'sometimes|date',
                'country_id' => 'sometimes|integer',
                'profile_image' => 'sometimes|image|mimes:jpeg,png,jpg'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'messages' => $validator->errors()
                ], 422);
            }

            // Get user
            $user = User::where('firebase_uid', $request->firebase_uid)->first();
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            // Update Firebase user if email or password is changed
            $firebaseUpdates = [];
            if ($request->has('email')) {
                $firebaseUpdates['email'] = $request->email;
            }
            if ($request->has('password')) {
                $firebaseUpdates['password'] = $request->password;
            }
            if ($request->has('name')) {
                $firebaseUpdates['displayName'] = $request->name;
            }

            if (!empty($firebaseUpdates)) {
                $this->auth->updateUser($user->firebase_uid, $firebaseUpdates);
            }

            // Handle profile image upload
            if ($request->hasFile('profile_image')) {
                // Delete old image if exists
                if ($user->profile_image) {
                    Storage::delete($user->profile_image);
                }

                // Store new image
                $path = $request->file('profile_image')->store('profile-images', 'public');
                $user->profile_image = $path;
            }

            // Update user data
            $user->fill($request->only([
                'name',
                'date_of_birth',
                'country_id',
            ]));

            if ($request->has('password')) {
                $user->password = Hash::make($request->password);
            }

            $user->save();

            // Add profile image URL to response
            if ($user->profile_image) {
                $user->profile_image_url = Storage::url($user->profile_image);
            }

            return response()->json([
                'message' => 'Profile updated successfully',
                'user' => $user
            ]);

        } catch (\Exception $e) {
            Log::error('Profile update error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to update profile',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateProfileImage(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'firebase_uid' => 'required|string',
                'profile_image' => 'required|image|mimes:jpeg,png,jpg'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'messages' => $validator->errors()
                ], 422);
            }

            $user = User::where('firebase_uid', $request->firebase_uid)->first();
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            // Delete old image if exists
            if ($user->profile_image) {
                Storage::delete($user->profile_image);
            }

            // Store new image
            $path = $request->file('profile_image')->store('profile-images', 'public');
            $user->profile_image = $path;
            $user->save();

            return response()->json([
                'message' => 'Profile image updated successfully',
                'profile_image_url' => Storage::url($path)
            ]);

        } catch (\Exception $e) {
            Log::error('Profile image update error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to update profile image',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
