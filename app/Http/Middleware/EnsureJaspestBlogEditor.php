<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureJaspestBlogEditor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->guest(config('jaspest_blog.login_path', '/admin'));
        }

        abort_unless(Gate::allows('manage-blog'), 403);

        return $next($request);
    }
}
