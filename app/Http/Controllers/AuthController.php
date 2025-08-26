<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $email = $request->email;
        $password = $request->password;

        try {
            // Validasi email & password sesuai ENV
            if (
                $email !== env('IMAP_USERNAME') ||
                $password !== env('IMAP_PASSWORD')
            ) {
                return response()->json(['status' => 'error', 'message' => 'Email or password does not match'], 401);
            }

            // Tes koneksi ke IMAP dengan error handling
            try {
                $imap = @imap_open(
                    '{mx.kirimemail.com:993/imap/ssl}INBOX',
                    $email,
                    $password
                );
                if (!$imap) {
                    $imapErrors = imap_errors();
                    $errorMsg = $imapErrors ? implode('; ', $imapErrors) : 'Invalid email or password';
                    return response()->json(['status' => 'error', 'message' => $errorMsg], 401);
                }
                imap_close($imap);
            } catch (\Exception $imapEx) {
                return response()->json(['status' => 'error', 'message' => 'IMAP connection failed', 'error' => $imapEx->getMessage()], 500);
            }

            // Custom claims (tanpa User model)
            $payload = JWTFactory::customClaims([
                'sub'      => $email,     // subject wajib
                'email'    => $email,
                'iat'      => time(),
                'exp'      => time() + 3600
            ])->make();

            $token = JWTAuth::encode($payload)->get();

            return response()->json([
                'status' => 'success',
                'user'   => [
                    'email' => $email,
                ],
                'token'  => $token,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Login failed', 'error' => $e->getMessage()], 500);
        }
    }

    public function me()
    {
        $payload = JWTAuth::parseToken()->getPayload();
        return response()->json($payload->toArray());
    }

    public function logout(Request $request)
    {
        try {
            // Ambil token dari Authorization Header
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'status'  => 'success',
                'message' => 'Logout berhasil, token sudah invalid'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal logout atau token tidak valid'
            ], 500);
        }
    }
}
