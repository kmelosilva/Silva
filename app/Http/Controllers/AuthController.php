<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{

    public function register(Request $request)
    {
        // A declaração de 18+ é exigida e não é gravada: nesta versão a conta é só para adultos.
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'adult' => 'accepted',
        ], [
            'name.required' => 'Diga como você se chama.',
            'name.max' => 'O nome pode ter até 255 caracteres.',
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Esse e-mail não parece válido.',
            'email.unique' => 'Já existe uma conta com esse e-mail. Entre com ele.',
            'password.required' => 'Crie uma senha.',
            'password.min' => 'A senha precisa de pelo menos 6 caracteres.',
            'adult.accepted' => 'Nesta versão, a Silva é só para quem tem 18 anos ou mais.',
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

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }
        
        return response()->json([
            'data' => [
                'message' => 'Logged out successfully'
            ]
        ], 200);
    }

}