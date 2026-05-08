<?php

namespace App\Controllers;

/**
 * Home Controller
 * app/Controllers/Home.php
 */
class Home extends BaseController
{
    public function index(): string
    {
        return view('home/index');
    }
}
