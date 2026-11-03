<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

final class AdvisorHealthProbe
{
    /** @return array{ready:bool,code:string,message:string} */
    public function preflight(string $provider, string $model, string $apiKey): array
    {
        $provider = strtolower(trim($provider));
        if ($provider === '' || $provider === 'disabled') {
            return ['ready'=>false,'code'=>'DISABLED','message'=>'External AI provider masih disabled.'];
        }
        if ($provider !== 'openai') {
            return ['ready'=>false,'code'=>'UNSUPPORTED_PROVIDER','message'=>'Provider tidak didukung: '.$provider];
        }
        if (trim($apiKey) === '') {
            return ['ready'=>false,'code'=>'MISSING_KEY','message'=>'BUSINESS_ADVISOR_API_KEY belum diisi.'];
        }
        if (trim($model) === '') {
            return ['ready'=>false,'code'=>'MISSING_MODEL','message'=>'BUSINESS_ADVISOR_MODEL belum diisi.'];
        }
        return ['ready'=>true,'code'=>'READY','message'=>'Konfigurasi OpenAI siap diuji.'];
    }

    /** @param array<string,mixed> $body @return array{ok:bool,code:string,message:string} */
    public function interpretHttp(int $status, array $body): array
    {
        if ($status >= 200 && $status < 300) {
            return ['ok'=>true,'code'=>'LIVE_OK','message'=>'OpenAI Responses API dapat diakses.'];
        }

        $message = trim((string)($body['error']['message'] ?? 'OpenAI API request gagal.'));
        if ($status === 401 || $status === 403) {
            return ['ok'=>false,'code'=>'AUTH_FAILED','message'=>$message !== '' ? $message : 'API key tidak valid atau tidak memiliki akses.'];
        }
        if ($status === 429) {
            return ['ok'=>false,'code'=>'RATE_LIMITED','message'=>$message !== '' ? $message : 'Rate limit atau quota OpenAI tercapai.'];
        }
        return ['ok'=>false,'code'=>'HTTP_'.$status,'message'=>$message !== '' ? $message : 'OpenAI API HTTP '.$status];
    }
}
