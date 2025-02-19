<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class ForgetPasswordController extends Controller
{
    public function forgetPassword()
    {
        return view("auth.forget-password");
    }

    public function forgetPasswordPost(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);
    
        $token = Str::random(64);
    
        $user = User::where('email', $request->email)->first();
        $user->token = $token;
        $user->token_created_at = Carbon::now();
    
        Log::info('User before save: ' . json_encode($user->toArray()));
    
        if ($user->save()) {
            Log::info('Token stored successfully for user: ' . $request->email);
        } else {
            Log::error('Failed to store token for user: ' . $request->email);
            Log::error('User data: ' . json_encode($user->toArray()));
        }
    
        try {
            $resetLink = route('reset.password', ['token' => $token, 'email' => $request->email]);
    
            Mail::send('emails.forget-password', ['resetLink' => $resetLink], function ($message) use ($request) {
                $message->to($request->email);
                $message->subject('Reset Password');
            });
    
            return redirect()->route('forget.password')->with('success', 'We have sent an email to reset your password.');
        } catch (\Exception $e) {
            Log::error('Mail sending failed: ' . $e->getMessage());
            return redirect()->route('forget.password')->with('error', 'Failed to send reset email. Please try again later.');
        }
    }
    
    public function resetPassword($token, Request $request)
    {
        $email = $request->query('email');
        return view('auth.new-password', compact('token', 'email'));
    }

    public function resetPasswordPost(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required',
            'token' => 'required'
        ]);
    
        Log::info('Reset Password Post Request', [
            'email' => $request->email,
            'token' => $request->token
        ]);
    
        $user = User::where([
            'email' => $request->email,
            'token' => $request->token
        ])->first();
    
        if (!$user) {
            Log::error('Invalid token or email', [
                'email' => $request->email,
                'token' => $request->token
            ]);
            return redirect()->route('reset.password', ['token' => $request->token, 'email' => $request->email])->with('error', 'Invalid token or email.');
        }
    
        $user->password = Hash::make($request->password);
        $user->token = null; // 重置 token
        $user->token_created_at = null; // 重置 token 生成时间
        $user->save();
    
        return redirect()->route('login')->with('success', 'Password reset successful.');
    }
    
}
