<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\CompletesSignIn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PinLoginRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PinLoginController extends Controller
{
    use CompletesSignIn;

    public function store(PinLoginRequest $request): SymfonyResponse
    {
        $request->authenticate();

        return $this->completeSignIn($request);
    }
}
