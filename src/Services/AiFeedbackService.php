<?php
declare(strict_types=1);

namespace App\Services;

use App\Utils\Env;

final class AiFeedbackService
{
    public function __construct() {}

    /**
     * Genereerib AI tagasiside sissekande põhjal.
     * Kui API ebaõnnestub, tagastab rule-based fallback.
     */
    public function generate(string $summary, int $pushupsToday, ?float $weightToday, int $deltaPushups, int $consistency14d): string
    {
        $apiKey = Env::get('AI_API_KEY');
        $model = Env::get('AI_MODEL', 'gpt-4o-mini');

        if ($apiKey === '') {
            return $this->getFallback($pushupsToday, $deltaPushups, $consistency14d);
        }

        $systemPrompt = 'Sa oled noortepärase, turvalise tooniga treeningu-coach. Ära maini kaalu numbrina. Ära anna meditsiinilisi nõuandeid. Kirjuta eesti keeles. 2–4 lauset.';
        $weightPart = $weightToday !== null ? 'kaal sisestatud' : 'puudub';
        $userPrompt = "Viimased mõõtmised: {$summary}. Täna: pushups={$pushupsToday}, kaal={$weightPart}. Muutus pushups: {$deltaPushups}. Järjepidevus 14p: {$consistency14d} sissekannet. Kirjuta tagasiside ja 1 konkreetne järgmine samm.";

        try {
            $text = $this->callOpenAI($apiKey, $model, $systemPrompt, $userPrompt);
            return $text ?: $this->getFallback($pushupsToday, $deltaPushups, $consistency14d);
        } catch (\Throwable $e) {
            return $this->getFallback($pushupsToday, $deltaPushups, $consistency14d);
        }
    }

    private function callOpenAI(string $apiKey, string $model, string $system, string $user): string
    {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'max_tokens' => 200,
                'temperature' => 0.7,
            ]),
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $response === false) {
            throw new \RuntimeException('AI API request failed');
        }

        $data = json_decode($response, true);
        $text = $data['choices'][0]['message']['content'] ?? null;
        return is_string($text) ? trim($text) : '';
    }

    private function getFallback(int $pushupsToday, int $delta, int $consistency14d): string
    {
        if ($consistency14d < 2) {
            return 'Hea, et logid! Järjepidevus on võti – proovi järgmiseks nädalaks vähemalt 2–3 korda sissekannet teha.';
        }
        if ($delta >= 2) {
            return 'Suurepärane areng! Jätka samas vaimus ja ära unusta puhkust.';
        }
        if ($delta <= -2) {
            return 'Vorm võib kõikuda – see on normaalne. Oluline on järjepidevus. Jätka logimist.';
        }
        return 'Stabiilne tulemus. Võid järgmisel korral proovida väikese tõusuga, kui tunnet on hea.';
    }
}
