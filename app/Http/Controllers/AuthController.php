<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ], 201);
    }


    public function login(Request $request)
    {

        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {

            return response()->json([
                'error' => [
                    'message' => 'Invalid credentials'
                ]
            ], 401);

        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ], 200);
    }

}
