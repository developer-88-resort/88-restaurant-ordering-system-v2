<?php

namespace App\Services\Assistant;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google's Gemini API (generateContent) for the Superadmin's assistant.
 * The key and model come from config/services.php (GEMINI_API_KEY,
 * GEMINI_MODEL in .env), never from the browser.
 *
 * Gemini answers 503 "high demand" / 429 now and then. A busy model is not
 * waited on: the fallback model is tried at once, then each again after a
 * short pause. Lite models (default gemini-flash-lite-latest) answer a data
 * question in about 2 s; the full Flash models were often overloaded or took
 * 60 s+ (measured 2026-10-13), and gemini-2.5-* no longer take new users.
 */
class GeminiClient
{
    protected const FALLBACK_MODEL = 'gemini-3.5-flash-lite';

    protected const BUSY = [429, 500, 503];

    /**
     * Seconds all calls of one chat request may take together (one question
     * can take several calls). Kept under PHP's 60 s max_execution_time: a
     * request killed by that limit answers with a crash instead of the
     * polite "not available", and on `php artisan serve` it froze the server.
     */
    protected const BUDGET_SECONDS = 45;

    /** When this request's budget runs out (set by the first call). */
    protected ?float $deadline = null;

    public function generate(array $contents, array $functionDeclarations, string $systemInstruction): array
    {
        $key = config('services.gemini.key');
        if (! $key) {
            throw new RuntimeException('GEMINI_API_KEY is not set.');
        }
        $this->deadline ??= microtime(true) + self::BUDGET_SECONDS;

        $body = [
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'contents' => $contents,
            'tools' => [['functionDeclarations' => $functionDeclarations]],
            'generationConfig' => ['temperature' => 0.3],
        ];

        $models = array_values(array_unique([config('services.gemini.model'), self::FALLBACK_MODEL]));
        $lastError = 'no answer';

        // main, fallback, then both once more after a short pause.
        $attempts = [[$models[0], 0], [$models[1] ?? $models[0], 0], [$models[0], 1000], [$models[1] ?? $models[0], 0]];

        foreach ($attempts as [$model, $waitMs]) {
            $left = $this->deadline - microtime(true) - $waitMs / 1000;
            if ($left < 3) {
                throw new RuntimeException('Gemini took too long ('.$lastError.').');
            }
            if ($waitMs) {
                usleep($waitMs * 1000);
            }

            try {
                $response = Http::timeout((int) min(20, floor($left)))
                    ->withHeaders(['x-goog-api-key' => $key])
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", $body);
            } catch (ConnectionException) {
                $lastError = 'Gemini timed out ('.$model.').';

                continue;
            }

            if ($response->successful()) {
                // The model's turn as returned (keeps any thought signatures
                // Gemini wants echoed back on the next call).
                $content = $response->json('candidates.0.content');
                if (is_array($content) && ! empty($content['parts'])) {
                    // A call without arguments arrives as {} and decodes to
                    // []; sent back as [] Gemini refuses it (400), so keep {}.
                    foreach ($content['parts'] as $i => $part) {
                        if (isset($part['functionCall']) && empty($part['functionCall']['args'])) {
                            $content['parts'][$i]['functionCall']['args'] = (object) [];
                        }
                    }

                    return $content;
                }
                $lastError = 'Gemini returned no answer ('.($response->json('candidates.0.finishReason') ?? 'no candidate').').';

                continue;
            }

            $lastError = 'Gemini '.$response->status().' ('.$model.'): '.mb_substr((string) $response->json('error.message', $response->body()), 0, 300);
            // Busy, or a model Google has retired (404): try the next one.
            if (! in_array($response->status(), [...self::BUSY, 404], true)) {
                throw new RuntimeException($lastError);
            }
        }

        throw new RuntimeException($lastError);
    }
}
