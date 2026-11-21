<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        helper('url');

        return view('portal_shell', [
            'portalBaseUrl' => base_url(),
        ]);
    }
}
