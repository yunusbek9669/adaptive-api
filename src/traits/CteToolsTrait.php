<?php

namespace Yunusbek\AdaptiveApi\traits;

use Yunusbek\AdaptiveApi\CteConstants;
use Exception;
use yii\base\InvalidConfigException;
use yii\db\ExpressionInterface;

trait CteToolsTrait
{
    use OperatorsTrait;
    use RootRelationTrait;
    use ReferenceTrait;

    public $countable = false;

    /** API so‘rovdan keladigan parametrlarni qayta ishlash
     * @param array $params
     * @param array $addition
     * @return array
     * @throws Exception
     */
    protected function paramsHelper(array $params, array $addition): array
    {
        $this->countable = isset($params['limit']) && isset($params['limit']);
        $data = [];
        $data['condition'] = [];
        $data['limit'] = (int)($params['limit'] ?? 1);
        $data['last_number'] = (int)($params['last_number'] ?? 0);
        if (!empty($addition['query_params'])) {
            $data['query_params'] = [];
            $iteration = 0;
            foreach ($addition['query_params'] as $key => $param) {
                if (preg_match("/(;|--|#|\/\*)[\s]*/", $key)) {
                    throw new Exception("⚠️ Invalid parameter name used: '{$key}'");
                } elseif (preg_match("/(;|--|#|\/\*)[\s]*/", $param)) {
                    throw new Exception("⚠️ Invalid parameter value used: '{$param}'");
                }
                if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\.([a-zA-Z_][a-zA-Z0-9_]*)$/', $key, $matches) && preg_match('/^<([a-zA-Z_][a-zA-Z0-9_]*)>([a-zA-Z_][a-zA-Z0-9_]*)$/', $key, $matches)) {
                    $data['condition']["{$matches[1]}.{$matches[2]}"] = ":query_param_{$iteration}";
                } elseif (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                    $data['condition'][$key] = ":query_param_{$iteration}";
                }
                $data['query_params'][":query_param_{$iteration}"] = $param;
                $iteration++;
            }
        }
        return $data;
    }

    /** Selectni normallashtirish
     * @param string $alias
     * @param $columns
     * @return array
     */
    protected function normalizeSelect(string $alias, $columns): array
    {
        if ($columns instanceof ExpressionInterface) {
            $columns = [$columns];
        } elseif (!is_array($columns)) {
            $columns = preg_split('/\s*,\s*/', trim((string)$columns), -1, PREG_SPLIT_NO_EMPTY);
        }
        $select = [];
        foreach ($columns as $columnAlias => $columnDefinition) {
            if ($columnDefinition instanceof ExpressionInterface) {
                $columnDefinition = $this->expressionLogic($columnDefinition);
            }
            if (is_string($columnAlias)) {
                // Already in the normalized format, good for them
                $select[strtolower($columnAlias)] = $this->qualifyColumns($columnDefinition, $alias);
                continue;
            }
            if (is_string($columnDefinition)) {
                if (
                    preg_match('/^(.*?)(?:\s+AS\s+|\s+)([\w\-_\.]+)$/is', $columnDefinition, $matches) &&
                    !preg_match('/^\d+$/', $matches[2]) &&
                    strpos($matches[2], '.') === false
                ) {
                    // Using "columnName as alias" or "columnName alias" syntax
                    if (preg_match('/^[\w]+\.([\w]+)$/', $matches[2], $m)) {
                        $matches[2] = $m[1];
                    }
                    $select[strtolower($matches[2])] = $this->qualifyColumns($matches[1], $alias);
                    continue;
                }
                if (strpos($columnDefinition, '(') === false) {
                    // Normal column name, just alias it to itself to ensure it's not selected twice
                    $column = $columnDefinition;
                    if (preg_match('/^[\w]+\.([\w]+)$/', $columnDefinition, $m)) {
                        $column = $m[1];
                    }
                    $select[strtolower($column)] = $this->qualifyColumns($columnDefinition, $alias);
                    continue;
                }
            }
            // Either a string calling a function, DB expression, or sub-query
            $select[] = $columnDefinition;
        }
        return $select;
    }

    private function expressionLogic($value): string
    {
        foreach ($value->params as $key => $param) {
            if (str_contains($value, "$key::text")) {
                $value = str_replace("$key::text", "'$param'", $value);
            } elseif (preg_match("/\Q{$key}\E::int(eger)?/", $value)) {
                $value = str_replace($key, var_export((int)$param, true), $value);
            } elseif (preg_match("/\Q{$key}\E::bool(ean)?/", $value)) {
                $value = str_replace($key, var_export((bool)$param, true), $value);
            } else {
                $value = str_replace($key, var_export($param, true), $value);
            }
        }
        return $value;
    }

    /** Asosiy tablitsaning bo‘sh ustunlariga alis berish
     * @param string $sqlExpression
     * @param string $alias
     * @return string
     */
    private function qualifyColumns(string $sqlExpression, string $alias): string
    {
        // Agar ifoda '(' bilan boshlansa — bu subquery yoki funksiya, tegmaymiz
        $trimmed = ltrim($sqlExpression);
        if (isset($trimmed[0]) && $trimmed[0] === '(') {
            return $sqlExpression;
        }

        $functions = [
            'ABS','ACOS','ASIN','ATAN','AVG','CAST','CEIL','CEILING','COALESCE',
            'CONCAT','CONVERT','COUNT','CURRENT_DATE','CURRENT_TIME','CURRENT_TIMESTAMP',
            'DATE','DAY','EXTRACT','FLOOR','GREATEST','IFNULL','INITCAP','LEAST','LENGTH',
            'LOCALTIME','LOCALTIMESTAMP','TO_TIMESTAMP','TO_CHAR','LOWER','LPAD','LTRIM','MAX','MIN','MOD','FILTER',
            'MONTH','NULLIF','POSITION','POWER','RADIANS','RAND','ROUND','RTRIM',
            'SESSION_USER','SIGN','SIN','SQRT','STDDEV','SUBSTRING','SUM','SYSTEM_USER','STRING_AGG',
            'TAN','TRIM','UPPER','USER','VARIANCE','YEAR',
            'CASE','WHEN','THEN','ELSE','END','IS','NOT','NULL','TRUE','FALSE','AND','OR','IN','ON','AS','SELECT','FROM','WHERE','ORDER','BY'
        ];

        // 1. String literal'larni olib tashlash
        $stringLiterals = [];
        $sqlExpression = preg_replace_callback("/'(?:''|[^'])*'/", function ($m) use (&$stringLiterals) {
            $key = "__STR" . count($stringLiterals) . "__";
            $stringLiterals[$key] = $m[0];
            return $key;
        }, $sqlExpression);

        // 2. Alias qo‘shish faqat normal ustun nomlariga
        $pattern = '/(?:\:[a-zA-Z_][a-zA-Z0-9_]*|__STR\d+__|[a-zA-Z][a-zA-Z0-9_]*(?:\.[a-zA-Z][a-zA-Z0-9_]*)?)/';
        $prev = null;
        $sqlExpression = preg_replace_callback($pattern, function ($matches) use ($alias, $functions, &$prev, $sqlExpression) {
            $word = $matches[0];
            $upperWord = strtoupper($word);

            // Parametr nomlarini tekshirish
            if ($word[0] === ':') {
                $prev = $word;
                return $word;
            }

            // Literal marker bo‘lsa
            if (strpos($word, '__STR') === 0) {
                $prev = $word;
                return $word;
            }

            // CASE, WHEN, THEN, ELSE, END faqat katta harfda operator sifatida tanilsin
            if (in_array($upperWord, $functions)) {
                $prev = $word;
                return $word;
            }

            // Funksiya bo‘lsa — faqat keyin darhol "(" kelganda funksiya deb qaraladi
            $pos = strpos($sqlExpression, $word, ($prev !== null ? strpos($sqlExpression, $prev) + strlen($prev) : 0));
            if ($pos !== false) {
                $nextCharPos = $pos + strlen($word);
                if (isset($sqlExpression[$nextCharPos]) && $sqlExpression[$nextCharPos] === '(') {
                    $prev = $word;
                    return $word;
                }
            }

            // '.' bo‘lsa yoki oldingi so‘z AS bo‘lsa
            if (strpos($word, '.') !== false || strtoupper($prev) === 'AS' || is_numeric($word)) {
                $prev = $word;
                return $word;
            }

            // Oddiy ustun
            $prev = $word;
            return "{$alias}.{$word}";
        }, $sqlExpression);

        // 3. String literal'larni qayta qo‘yish
        foreach ($stringLiterals as $key => $value) {
            $sqlExpression = str_replace($key, $value, $sqlExpression);
        }

        return $sqlExpression;
    }

    /**
     * @throws InvalidConfigException
     */
    protected function convertDataTable(array $data, string $cteKey): string
    {
        $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $select = [];
        foreach ($data as $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    if (is_int($subValue)) {
                        $subValue = "INTEGER";
                    } elseif (is_string($subValue)) {
                        $subValue = "TEXT";
                    }
                    $select[$subKey] = $subValue;
                }
                break;
            } else {
                throw new InvalidConfigException(sprintf("Invalid 'data' format for [%s]: expected a non-empty array of associative arrays.", $cteKey));
            }
        }
        $declare = implode(", ", array_map(fn($col, $type) => "$col $type", array_keys($select), $select));
        $this->with[$cteKey.'Data'] = <<<SQL
        WITH {$cteKey}Data AS (
                SELECT *
                FROM json_to_recordset('{$json_data}') AS {$cteKey}({$declare})
            )
        SQL;
        return "{$cteKey}Data";
    }

    /** Array formatidagi selectni string holatga o‘girish
     * @param array $select
     * @return string
     */
    protected function selectSql(array $select): string
    {
        foreach ($select as $key => &$value) {
            if (is_string($key)) {
                $value = "$value AS $key";
            }
        }
        return implode(",\n            ", $select);
    }

    /** (CameCase)ni (snake_case)ga o‘girish
     * @param string $camelCase
     * @return string
     */
    protected function camelToSnake(string $camelCase): string
    {
        $result = preg_replace_callback(
            '/([A-Z])/',
            function ($matches) {
                return '_' . strtolower($matches[1]);
            },
            $camelCase
        );
        return ltrim($result, '_');
    }
}