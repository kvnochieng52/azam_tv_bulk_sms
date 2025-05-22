<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class IsActive
{
    // public function handle(Request $request, Closure $next): Response
    // {
    //     if (Auth::check() && !Auth::user()->is_active) {
    //         Auth::logout();

    //         $request->session()->forget('error');
    //         $request->session()->flash('error', 'Your account is inactive. Please contact the administrator.');



    //         return redirect()->route('login');

    //         // return redirect()->route('login')
    //         //     ->with('error', 'Your account is inactive. Please contact the administrator.');

    //         // return Inertia::location(route('login', [
    //         //     'error' => 'Your account is inactive. Please contact the administrator.'
    //         // ]));
    //     }

    //     return $next($request);
    // }

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && !Auth::user()->is_active) {
            // Store the error message before clearing session
            $errorMessage = 'Your account is inactive. Please contact the administrator.';

            // Logout the user
            Auth::logout();

            // Clear the current session
            $request->session()->invalidate();

            // Regenerate the session ID for security
            $request->session()->regenerate();

            // Flash the error message to the new session
            $request->session()->flash('error', $errorMessage);

            return redirect()->route('login');
        }

        return $next($request);
    }
}
