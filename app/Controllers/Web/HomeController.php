<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\View\View;

final class HomeController
{
    public function __construct(private readonly View $view)
    {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('landing/home'));
    }
}
