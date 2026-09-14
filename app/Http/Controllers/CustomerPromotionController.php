<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use App\Support\PromotionEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerPromotionController extends Controller
{
    public function view(Request $request, Promotion $promotion, PromotionEventRecorder $recorder): Response
    {
        abort_unless($promotion->status->value === 'active' && $promotion->image_path, 404);

        $recorder->recordView($promotion, $request->session()->getId());

        return response()->noContent();
    }

    public function click(Request $request, Promotion $promotion, PromotionEventRecorder $recorder): RedirectResponse
    {
        abort_unless(
            $promotion->status->value === 'active'
                && $promotion->image_path
                && filled($promotion->banner_cta_url),
            404,
        );

        $recorder->recordClick($promotion, $request->session()->getId());

        return redirect()->away($promotion->banner_cta_url);
    }
}
