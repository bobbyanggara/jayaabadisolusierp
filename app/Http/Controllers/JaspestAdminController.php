<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class JaspestAdminController extends Controller
{
    public function index(): Response
    {
        return response()->view('jaspest.admin.index', [
            'adminApiBase' => url(trim(config('jaspest_admin.prefix', 'admin/jaspest'), '/').'/api'),
            'adminLoginUrl' => url('/'.ltrim((string) config('jaspest_admin.login_path', '/admin/login'), '/')),
        ])->header('Cache-Control', 'no-store, private');
    }
}
