<?php

namespace App\Http\Controllers;

use App\Support\StatusPage;
use Illuminate\View\View;

/**
 * /status — the system's status page, like status.claude.com, open to anyone
 * at any time (no sign-in), including while Maintenance Mode is on.
 */
class StatusController extends Controller
{
    public function show(): View
    {
        return view('status', ['status' => StatusPage::build()]);
    }
}
