<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;

final class ConsoleController
{
    public function __construct(private readonly Responder $responder)
    {
    }

    public function dashboard(Request $request): Response
    {
        return $this->responder->view($request, 'console/dashboard', ['user' => CurrentUser::from($request)]);
    }
}
