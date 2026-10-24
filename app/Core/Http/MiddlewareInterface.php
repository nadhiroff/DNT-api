<?php

declare(strict_types=1);

namespace App\Core\Http;

interface MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $next): Response;
}
