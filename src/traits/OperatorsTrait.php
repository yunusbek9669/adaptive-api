<?php

namespace Yunusbek\AdaptiveApi\traits;

use Yunusbek\AdaptiveApi\ASTValidator;
use yii\db\Expression;

trait OperatorsTrait
{
    /** CONDITION operatorini sql formatiga moslash
     * @param array $condition
     * @param string|null $alias
     * @param string|null $prefix
     * @return string|null
     */
    private function condition(array $condition, string $alias = null, string $prefix = null): ?string
    {
        if (!empty($condition) && isset($this->from)) {
            $select = $this->cteList[$this->from]['select'];
            $where = [];
            if (isset($condition[0]) && is_array($condition[1])) {
                foreach ($condition[1] as $key => $val) {
                    $val = str_starts_with($val, ':') ? $val : var_export($val, true);
                    $columnDefinition = $this->qualifyColumns($select[$key] ?? "{$key}", $alias ?? $this->from);
                    if (strtolower($condition[0]) === 'not') {
                        $where[] = "$columnDefinition IS NOT " . $val;
                    } elseif (in_array(strtolower($condition[0]), ['!=', '<>'])) {
                        $where[] = "$columnDefinition <> " . $val;
                    } else {
                        $where[] = "$columnDefinition {$condition[0]} " . $val;
                    }
                }
            } else {
                foreach ($condition as $attribute => $value) {
                    $columnDefinition = $this->qualifyColumns($select[$attribute] ?? "{$attribute}", $alias ?? $this->from);
                    if (is_int($attribute)) {
                        $where[] = $value;
                    } elseif (is_null($value)) {
                        $where[] = "$columnDefinition IS NULL";
                    } elseif (is_array($value)) {
                        $value = implode(', ', array_map(fn($v) => str_starts_with($v, ':') ? $v : var_export($v, true), $value));
                        $where[] = "$columnDefinition IN ({$value})";
                    } elseif ($value instanceof Expression) {
                        $where[] = "$columnDefinition = {$value}";
                    } else {
                        $where[] = "$columnDefinition = " . (str_starts_with($value, ':') ? $value : var_export($value, true));
                    }
                }
            }

            return $prefix . implode(" AND ", $where);
        }
        return null;
    }


    /** JOIN operatorini sql formatiga moslash
     * @param array $data
     * @param string|null $rootAlias
     * @param string|null $prefix
     * @return string|null
     */
    private function join(array $data, string $rootAlias = null, string $prefix = null): ?string
    {
        if (!empty($data)) {
            $result = [];
            if (!is_array($data[0])) { $data = [$data]; }
            if (!empty($rootAlias)) { $rootAlias = "$rootAlias."; }
            foreach ($data as $key => $operator) {
                if (preg_match('/^(\w+)\s+(?:AS\s+)?(\w+)$/i', $operator[1], $matches)) {
                    $alias = $matches[2];
                    $table = $matches[1];
                } else {
                    $alias = $operator[1];
                    $table = $operator[1];
                }
                $result[$key] = "{$operator[0]} {$table} AS {$alias}";

                $condition = [];
                if (isset($operator['on']) && is_array($operator['on'])) {
                    foreach ($operator['on'] as $joinColumn => $rootColumn) {
                        if (preg_match('/^[\w]+\.([\w]+)$/', $rootColumn, $m)) {
                            $rootColumn = "{$rootColumn}";
                        } else {
                            $rootColumn = "{$rootAlias}{$rootColumn}";
                        }
                        $condition[] = "{$alias}.{$joinColumn} = {$rootColumn}";
                    }
                    $result[$key] .= " ON ".implode(" AND ", $condition);
                } elseif (isset($operator['on']) && is_string($operator['on'])) {
                    $result[$key] .= " ON ({$operator['on']})";
                }
                if (isset($operator['condition'])) {
                    $result[$key] .= $this->condition($operator['condition'], $alias, str_contains($result[$key], ' ON ') ? " AND " : ' ON ');
                }
            }
            return $prefix.implode("\n        ", $result);
        }
        return null;
    }


    /** SELECT ichiga 'jsonb_build_object' qilib berish
     * @param string|array $data
     * @param string|null $type
     * @param string|null $key
     * @return string
     */
    private function select(string|array $data, string $type = null, string &$key = null): string
    {
        $condition = null;
        if (is_array($data)) {
            ASTValidator::detectIfNull($data, $condition, $type, $key);
            $flat = [];
            foreach ($data as $key => $value) {
                $flat[] = "'".str_replace('{?}', '', $key)."'";
                $flat[] = is_array($value) ? $this->select($value, null, $key) : (str_contains($value, '.*') ? "to_jsonb({$value})" : $value);
            }
            $data = implode(", ", $flat);
            $data = "       jsonb_build_object({$data})";
        }

        if ($condition) { $data = "CASE WHEN COALESCE({$condition}) IS NOT NULL THEN {$data} END"; }

        if (isset($type)) {
            $type = str_replace('{?}', '', $type);
            if (str_ends_with($data, '.*')) {
                $data = str_replace('.*', '', $data);
                $data = "       row_to_json({$data}) AS \"{$type}\"";
            } else {
                $data = "{$data} AS \"{$type}\"";
            }
        }
        return $data;
    }
}