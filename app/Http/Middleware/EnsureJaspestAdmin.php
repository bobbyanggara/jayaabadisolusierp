<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureJaspestAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sesi login berakhir. Silakan masuk kembali.'], 401);
            }

            return redirect()->guest(config('jaspest_admin.login_path', '/admin/login'));
        }

        if (! Gate::allows('manage-jaspest')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Akun Anda tidak memiliki akses ke dashboard JASPEST.'], 403);
            }

            abort(403, 'Akun ini tidak memiliki akses ke dashboard JASPEST.');
        }

        return $next($request);
    }
}
