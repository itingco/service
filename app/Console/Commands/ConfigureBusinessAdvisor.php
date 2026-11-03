<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BusinessAnalytics\AdvisorEnvWriter;
use Illuminate\Console\Command;

final class ConfigureBusinessAdvisor extends Command
{
    protected $signature = 'business-advisor:configure {--model=gpt-5.6-terra : OpenAI model ID} {--no-web : Disable web research}';
    protected $description = 'Securely configure the Product Strategy Advisor OpenAI provider in .env';

    public function handle(AdvisorEnvWriter $writer): int
    {
        $envPath = base_path('.env');
        if (!is_file($envPath)) {
            $this->error('.env tidak ditemukan: '.$envPath);
            return self::FAILURE;
        }

        $model = trim((string) $this->option('model'));
        if ($model === '') {
            $this->error('--model tidak boleh kosong.');
            return self::FAILURE;
        }

        $apiKey = (string) $this->secret('Paste OpenAI API key (input disembunyikan)');
        if (trim($apiKey) === '') {
            $this->error('API key kosong. Konfigurasi dibatalkan.');
            return self::FAILURE;
        }

        $contents = file_get_contents($envPath);
        if ($contents === false) {
            $this->error('Gagal membaca .env.');
            return self::FAILURE;
        }

        $backup = $envPath.'.before-business-advisor-ai.bak';
        if (!is_file($backup) && !copy($envPath, $backup)) {
            $this->error('Gagal membuat backup .env.');
            return self::FAILURE;
        }

        $updated = $writer->withSettings($contents, [
            'BUSINESS_ADVISOR_PROVIDER' => 'openai',
            'BUSINESS_ADVISOR_MODEL' => $model,
            'BUSINESS_ADVISOR_API_KEY' => $apiKey,
            'BUSINESS_ADVISOR_WEB_RESEARCH' => $this->option('no-web') ? 'false' : 'true',
            'BUSINESS_ADVISOR_TIMEOUT_SECONDS' => '45',
        ]);

        if (file_put_contents($envPath, $updated) === false) {
            $this->error('Gagal menulis .env.');
            return self::FAILURE;
        }

        $this->info('Konfigurasi Hybrid AI tersimpan. API key tidak ditampilkan.');
        $this->line('Model       : '.$model);
        $this->line('Web research: '.($this->option('no-web') ? 'OFF' : 'ON'));
        $this->line('Backup      : '.$backup);
        $this->comment('Jalankan php artisan optimize:clear lalu business-advisor:check --live --web.');
        return self::SUCCESS;
    }
}
