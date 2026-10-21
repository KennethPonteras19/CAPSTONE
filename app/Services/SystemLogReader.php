<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SystemLogReader
{
    public function paginateLogs(Request $request, string $indexRoute): LengthAwarePaginator
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
            'severity' => ['sometimes', Rule::in(['all', 'info', 'warning', 'danger'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $logs = $this->readLogEntries();
        $severity = $validated['severity'] ?? 'all';
        $search = strtolower(trim($validated['search'] ?? ''));

        if ($severity !== '' && $severity !== 'all') {
            $logs = array_values(array_filter($logs, function (array $log) use ($severity) {
                $logSeverity = strtolower((string) ($log['severity'] ?? 'info'));
                $normalized = in_array($logSeverity, ['error', 'critical', 'alert', 'emergency'], true)
                    ? 'danger' : $logSeverity;

                return $normalized === $severity;
            }));
        }

        if ($search !== '') {
            $logs = array_values(array_filter($logs, function (array $log) use ($search) {
                $haystack = strtolower(
                    ($log['timestamp'] ?? '') . ' ' .
                    ($log['event'] ?? '') . ' ' .
                    ($log['severity'] ?? '')
                );

                return str_contains($haystack, $search);
            }));
        }

        $perPage = 25;
        $total = count($logs);
        $page = min((int) ($validated['page'] ?? 1), max(1, (int) ceil($total / $perPage)));

        return new LengthAwarePaginator(array_slice($logs, ($page - 1) * $perPage, $perPage), $total, $perPage, $page, [
            'path' => route($indexRoute),
            'query' => $request->only(['search', 'severity']),
        ]);
    }

    public function logPaths(): array
    {
        $single = config('logging.channels.single.path', storage_path('logs/laravel.log'));
        $daily = config('logging.channels.daily.path', storage_path('logs/laravel.log'));
        $dailyPattern = dirname($daily).'/'.pathinfo($daily, PATHINFO_FILENAME).'-????-??-??.'.pathinfo($daily, PATHINFO_EXTENSION);

        return array_values(array_unique(array_filter(array_merge([$single], File::glob($dailyPattern)), 'is_file')));
    }

    public function readLogEntries(?int $max = null): array
    {
        $entries = [];

        foreach ($this->logPaths() as $logPath) {
            // Iterate lines so exception stack traces do not duplicate the whole file in memory.
            foreach (new \SplFileObject($logPath, 'r') as $line) {
                if (is_string($line) && preg_match('/^\[(.*?)\]\s+\S+\.(\w+):\s+(.*)$/', $line, $matches)) {
                    $entries[] = [
                        'raw_timestamp' => $matches[1],
                        'timestamp' => $this->formatTimestamp($matches[1]),
                        'severity' => strtolower($matches[2]),
                        'event' => $this->simplifyEvent(trim($matches[3])),
                    ];
                }
            }
        }

        $entries = array_reverse($entries);
        // Sort by the original 24-hour timestamp, including entries in rotated daily files.
        usort($entries, fn (array $left, array $right) => strcmp($right['raw_timestamp'], $left['raw_timestamp']));

        return array_map(function (array $entry) {
            unset($entry['raw_timestamp']);

            return $entry;
        }, $max === null ? $entries : array_slice($entries, 0, $max));
    }

    private function formatTimestamp(string $raw): string
    {
        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', $raw, config('app.timezone'))
                ->setTimezone(config('app.timezone'))
                ->format('Y-m-d h:i:s A');
        } catch (\Throwable $e) {
            try {
                return Carbon::parse($raw)
                    ->setTimezone(config('app.timezone'))
                    ->format('Y-m-d h:i:s A');
            } catch (\Throwable $inner) {
                return $raw;
            }
        }
    }

    private function simplifyEvent(string $rawEvent): string
    {
        if (preg_match('/"message":"([^"]+)"/', $rawEvent, $jsonMessage)) {
            return stripslashes($jsonMessage[1]);
        }

        if (Str::startsWith($rawEvent, 'User activity')) {
            return trim(str_replace('User activity', '', $rawEvent));
        }

        return $rawEvent;
    }
}
