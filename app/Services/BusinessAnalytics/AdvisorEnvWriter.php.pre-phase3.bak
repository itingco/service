<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

final class AdvisorEnvWriter
{
    /** @param array<string,string> $settings */
    public function withSettings(string $contents, array $settings): string
    {
        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $lines = preg_split('/\R/', $contents);
        if (!is_array($lines)) $lines = [];

        foreach ($settings as $key => $value) {
            $replacement = $key.'='.$this->formatValue($key, $value);
            $found = false;
            foreach ($lines as $i => $line) {
                if (preg_match('/^'.preg_quote($key, '/').'\s*=/', $line) === 1) {
                    if (!$found) {
                        $lines[$i] = $replacement;
                        $found = true;
                    } else {
                        unset($lines[$i]);
                    }
                }
            }
            if (!$found) $lines[] = $replacement;
        }

        $result = implode($eol, array_values($lines));
        return rtrim($result, "\r\n").$eol;
    }

    private function formatValue(string $key, string $value): string
    {
        if ($key === 'BUSINESS_ADVISOR_API_KEY') {
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
            return '"'.$escaped.'"';
        }
        return $value;
    }
}
