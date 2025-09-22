<?php

namespace Faed\LaravelSaasHelper\commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FaedParameterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faed:parameter
                            {name : Table name to generate parameters for}
                            {--connection= : Specify database connection}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成Request参数和swagger注释';

    /**
     * Excluded column names
     *
     * @var array
     */
    protected $excludedColumns = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $tableName = $this->argument('name');
        $specifiedConnection = $this->option('connection');

        // Get field comments from appropriate connection
        $fieldComments = $this->getFieldComments($tableName, $specifiedConnection);

        if (empty($fieldComments)) {
            $this->error("No columns found for table '{$tableName}'");
            return 1;
        }

        // Generate and display Swagger parameters
        $this->generateSwaggerParameters($fieldComments);

        // Generate and display validation rules
        $this->generateValidationRules($fieldComments);

        return 0;
    }

    /**
     * Generate Swagger/OpenAPI parameters
     *
     * @param array $fieldComments
     */
    protected function generateSwaggerParameters(array $fieldComments)
    {
        $this->info("\nSwagger Parameters:");

        foreach ($fieldComments as $comment) {
            if ($this->isExcludedColumn($comment['name'])) {
                continue;
            }

            $required = $comment['is_nullable'] === 'NO' ? 'true' : 'false';
            list($type, $example) = $this->getFieldType($comment['type'], $comment['name'], $comment['comment']);

            $parameter = sprintf(
                '*    @OA\Parameter(name="%s",description="%s",in="query",required=%s,@OA\Schema(type="%s"),example="%s"),',
                $comment['name'],
                $comment['comment'] ?: 'No description',
                $required,
                $type,
                $example ?? ''
            );

            $this->info($parameter);
        }
    }

    /**
     * Generate validation rules
     *
     * @param array $fieldComments
     * @return array
     */
    protected function generateValidationRules(array $fieldComments): array
    {
        $rules = [];

        foreach ($fieldComments as $comment) {
            if ($this->isExcludedColumn($comment['name'])) {
                continue;
            }
            $fieldName = $comment['name'];
            $isNullable = $comment['is_nullable'] === 'NO';
            $baseType = preg_replace('/\(.*\)/', '', $comment['type']);
            $baseType = strtolower($baseType);
            $fieldComment = $comment['comment'] ?? '';

            // Start with required/nullable
            $rule = $isNullable ?  'required' :'nullable';

            // Add type-specific rules
            // Integer types
            if (in_array($baseType, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint'])) {
                // Boolean fields (tinyint(1))
                if ($baseType === 'tinyint' && strpos($comment['type'], '(1)') !== false) {
                    $rule .= '|boolean';
                } else {
                    $rule .= '|integer';
                    // Add size validation for known ID fields
                    if ($fieldName === 'clique_id') {
                        $rule .= '|min:60000000|max:99999999';
                    } elseif ($fieldName === 'comkey') {
                        $rule .= '|min:10000000|max:99999999';
                    }
                }
            }
            // Decimal/Float types
            elseif (in_array($baseType, ['decimal', 'float', 'double'])) {
                $rule .= '|numeric';
            }
            // Boolean (could be enum('Y','N') or other representations)
            elseif ($baseType === 'enum' && in_array(strtoupper($fieldComment), ['Y', 'N'])) {
                $rule .= '|boolean';
            }
            // JSON types
            elseif ($baseType === 'json') {
                $rule .= '|array';
            }
            // Date/Time types
            elseif (in_array($baseType, ['datetime', 'timestamp', 'date', 'time'])) {
                $rule .= '|date';
            }
            // Email fields
            elseif (strpos($fieldName, 'email') !== false || strpos($fieldComment, 'email') !== false) {
                $rule .= '|email';
            }
            // URL fields
            elseif (strpos($fieldName, 'url') !== false || strpos($fieldComment, 'url') !== false) {
                $rule .= '|url';
            }
            // Phone fields (simple validation)
            elseif (strpos($fieldName, 'phone') !== false || strpos($fieldComment, 'phone') !== false) {
                $rule .= '|regex:/^1[3-9]\d{9}$/';
            }
            // String types with length validation
            elseif (in_array($baseType, ['char', 'varchar', 'text'])) {
                // Extract length from type (e.g. varchar(255) -> 255)
                if (preg_match('/\((\d+)\)/', $comment['type'], $matches)) {
                    $maxLength = $matches[1];
                    $rule .= "|string|max:{$maxLength}";
                } else {
                    $rule .= '|string';
                }
            }
            // Default to string
            else {
                $rule .= '|string';
            }

            // Add special rules based on field name or comment
            if (strpos($fieldName, 'password') !== false) {
                $rule .= '|min:6';
            }
            if (strpos($fieldName, '_at') !== false && $baseType === 'datetime') {
                $rule .= '|date_format:Y-m-d H:i:s';
            }

            $rules[$fieldName] = $rule;
        }

        // Output the rules in a more readable format
        $this->info("\nValidation Rules:");
        $this->info('[');
        foreach ($rules as $field => $rule) {
            $result = str_replace('|', '\',\'', $rule);
            $this->info("    '{$field}' => ['{$result}'],");
        }
        $this->info(']');

        return $rules;
    }
    /**
     * Get field comments from database
     *
     * @param string $tableName
     * @param string|null $connection
     * @return array
     */
    protected function getFieldComments(string $tableName, ?string $connection = null): array
    {
        $connections = $this->getMysqlConnections();

        // If specific connection is provided, use only that one
        if ($connection) {
            $connections = array_intersect($connections, [$connection]);
        }

        foreach ($connections as $conn) {
            try {
                $sql = "SELECT
                        COLUMN_NAME,
                        COLUMN_COMMENT,
                        IS_NULLABLE,
                        DATA_TYPE
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?";

                $results = DB::connection($conn)->select($sql, [$tableName]);

                if (!empty($results)) {
                    return array_map(function ($row) {
                        return [
                            'name' => $row->COLUMN_NAME,
                            'comment' => $row->COLUMN_COMMENT ?: $row->COLUMN_NAME,
                            'is_nullable' => $row->IS_NULLABLE,
                            'type' => $row->DATA_TYPE,
                        ];
                    }, $results);
                }
            } catch (\Exception $e) {
                $this->warn("Connection '{$conn}' failed: " . $e->getMessage());
                continue;
            }
        }

        return [];
    }

    /**
     * Get all MySQL connections from config
     *
     * @return array
     */
    protected function getMysqlConnections(): array
    {
        return array_filter(
            array_keys(config('database.connections')),
            function ($db) {
                return strpos($db, 'mysql') !== false;
            }
        );
    }

    /**
     * Determine if column should be excluded
     *
     * @param string $columnName
     * @return bool
     */
    protected function isExcludedColumn(string $columnName): bool
    {
        return in_array($columnName, $this->excludedColumns);
    }

    /**
     * Get field type and example value
     *
     * @param string $type
     * @param string $name
     * @param string $comment
     * @return array
     */
    protected function getFieldType(string $type, string $name, string $comment): array
    {
        // Normalize type by removing extra info (e.g. "varchar(255)" -> "varchar")
        $baseType = preg_replace('/\(.*\)/', '', $type);
        $baseType = strtolower($baseType);

        // Integer types
        if (in_array($baseType, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint'])) {
            // Special handling for boolean fields (typically tinyint(1))
            if ($baseType === 'tinyint' && strpos($type, '(1)') !== false) {
                return ['boolean', true];
            }

            // Special values for known ID fields
            if ($name === 'clique_id') {
                return ['integer', 60000004];
            } elseif ($name === 'comkey') {
                return ['integer', 10000005];
            }

            return ['integer', rand(100000, 999999)];
        }
        // Decimal/Float types
        elseif (in_array($baseType, ['decimal', 'float', 'double'])) {
            return ['number', round(rand(1000, 9999) / 100, 2)];
        }
        // Boolean (could be enum('Y','N') or other representations)
        elseif ($baseType === 'enum' && in_array(strtoupper($comment), ['Y', 'N'])) {
            return ['boolean', true];
        }
        // JSON types
        elseif ($baseType === 'json') {
            return ['object', ['key' => 'value']];
        }
        // Date/Time types
        elseif ($baseType === 'datetime' || $baseType === 'timestamp') {
            return ['string', now()->format('Y-m-d H:i:s')];
        }
        elseif ($baseType === 'date') {
            return ['string', now()->format('Y-m-d')];
        }
        elseif ($baseType === 'time') {
            return ['string', now()->format('H:i:s')];
        }
        // Text/String types
        elseif (in_array($baseType, ['char', 'varchar', 'text', 'longtext', 'mediumtext', 'tinytext'])) {
            // Check if this might be an email field
            if (strpos($name, 'email') !== false || strpos($comment, 'email') !== false) {
                return ['string', 'user@example.com'];
            }
            // Check if this might be a URL field
            elseif (strpos($name, 'url') !== false || strpos($comment, 'url') !== false) {
                return ['string', 'https://example.com'];
            }
            // Check if this might be a phone field
            elseif (strpos($name, 'phone') !== false || strpos($comment, 'phone') !== false) {
                return ['string', '13800138000'];
            }
            // Default string
            return ['string', $comment ?: Str::slug($name, '_')];
        }
        // Binary types
        elseif (in_array($baseType, ['binary', 'varbinary', 'blob', 'longblob'])) {
            return ['string', 'base64:'.base64_encode('binary_data')];
        }
        // Fallback
        return ['string', $comment ?: $name];
    }
}
