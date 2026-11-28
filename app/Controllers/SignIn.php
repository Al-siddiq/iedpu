<?php
declare(strict_types=1);

namespace App\Controllers;

final class SignIn extends BaseController
{
    public function index(): string
    {
        $this->response
            ->setHeader('Cache-Control', 'no-store, private')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Security-Policy', "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
        return view('auth/sign_in');
    }
}
