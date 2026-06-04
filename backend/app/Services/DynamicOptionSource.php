<?php

namespace App\Services;

use App\Models\WorkflowField;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DynamicOptionSource
{
    public function hasDynamicSource(WorkflowField $field): bool
    {
        return !empty($field->option_source_type) && !empty($field->option_source_config);
    }

    public function resolveOptions(WorkflowField $field, array $context = []): array
    {
        $sourceType = $field->option_source_type;
        $sourceConfig = $field->option_source_config;

        if (empty($sourceType) || empty($sourceConfig)) {
            return $field->resolved_options;
        }

        return match ($sourceType) {
            'database' => $this->resolveFromDatabase($sourceConfig, $context),
            'api' => $this->resolveFromApi($sourceConfig, $context),
            'service' => $this->resolveFromService($sourceConfig, $context),
            default => $field->resolved_options,
        };
    }

    public function resolveFromDatabase(string $config, array $context = []): array
    {
        try {
            $parsed = json_decode($config, true);
            if (!is_array($parsed)) {
                return [];
            }

            $table = $parsed['table'] ?? '';
            $labelColumn = $parsed['label_column'] ?? 'name';
            $valueColumn = $parsed['value_column'] ?? 'id';
            $whereClause = $parsed['where'] ?? [];
            $orderBy = $parsed['order_by'] ?? $labelColumn;

            if (empty($table)) {
                return [];
            }

            $query = DB::table($table)->select($valueColumn, $labelColumn);

            foreach ($whereClause as $column => $value) {
                $query->where($column, $value);
            }

            $query->orderBy($orderBy);
            $results = $query->get();

            $options = [];
            foreach ($results as $row) {
                $options[] = [
                    'label' => (string) ($row->{$labelColumn} ?? ''),
                    'value' => (string) ($row->{$valueColumn} ?? ''),
                ];
            }

            return $options;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function resolveFromApi(string $config, array $context = []): array
    {
        try {
            $parsed = json_decode($config, true);
            if (!is_array($parsed)) {
                return [];
            }

            $url = $parsed['url'] ?? '';
            $method = $parsed['method'] ?? 'GET';
            $headers = $parsed['headers'] ?? [];
            $body = $parsed['body'] ?? [];
            $labelPath = $parsed['label_path'] ?? 'label';
            $valuePath = $parsed['value_path'] ?? 'value';
            $dataPath = $parsed['data_path'] ?? '';

            if (empty($url)) {
                return [];
            }

            $request = Http::withHeaders($headers);

            $response = match (strtoupper($method)) {
                'POST' => $request->post($url, $body),
                'PUT' => $request->put($url, $body),
                default => $request->get($url, $body),
            };

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();

            if (!empty($dataPath)) {
                $data = $this->extractPath($data, $dataPath);
            }

            if (!is_array($data)) {
                return [];
            }

            $options = [];
            foreach ($data as $item) {
                $options[] = [
                    'label' => (string) ($this->extractPath($item, $labelPath) ?? ''),
                    'value' => (string) ($this->extractPath($item, $valuePath) ?? ''),
                ];
            }

            return $options;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function resolveFromService(string $config, array $context = []): array
    {
        try {
            $parsed = json_decode($config, true);
            if (!is_array($parsed)) {
                return [];
            }

            $serviceClass = $parsed['class'] ?? '';
            $method = $parsed['method'] ?? 'getOptions';
            $params = $parsed['params'] ?? [];

            if (empty($serviceClass) || !class_exists($serviceClass)) {
                return [];
            }

            $service = app($serviceClass);
            if (!method_exists($service, $method)) {
                return [];
            }

            $result = $service->$method($params, $context);

            if (!is_array($result)) {
                return [];
            }

            $options = [];
            foreach ($result as $item) {
                if (is_array($item)) {
                    $options[] = [
                        'label' => (string) ($item['label'] ?? $item['name'] ?? ''),
                        'value' => (string) ($item['value'] ?? $item['id'] ?? ''),
                    ];
                } else {
                    $options[] = [
                        'label' => (string) $item,
                        'value' => (string) $item,
                    ];
                }
            }

            return $options;
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function extractPath(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }
}
