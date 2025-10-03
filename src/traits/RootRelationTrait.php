<?php

namespace Yunusbek\AdaptiveApi\traits;

use yii\base\InvalidConfigException;
use yii\db\Exception;

trait RootRelationTrait
{
    /**
     * @return void
     */
    public function rootCteMaker(): void
    {
        $data = $this->cteList[$this->from];
//        $this->partHelper($this->from, $data);
        $condition = [];
        foreach ($this->params['condition'] as $key => $val) {
            if ($key === 'unique_number') {
                $condition[$data['unique_number']] = $val;
            } else {
                $condition[$data['filter'][$key] ?? $key] = $val;
            }
        }

        $this->with[$this->from] = <<<SQL
        WITH {$this->from} AS (
                SELECT DISTINCT ON ({$this->from}.{$data['unique_number']}) 
                    {$this->selectSql($data['select'])},
                    COUNT(*) OVER() AS total_count
                FROM {$data['table']} AS {$this->from} {$this->join($data['join'], $this->from, "\n        ")} 
                WHERE (:last_number::integer IS NULL OR {$this->from}.{$data['unique_number']} > :last_number::integer) {$this->condition(array_merge($condition, $data['where']), $this->from, ' AND ')}
            ),
            {$this->cteLimited} AS MATERIALIZED (
                SELECT *
                FROM {$this->from} 
                ORDER BY unique_number ASC
                LIMIT :limit
            ),
            cte_counts AS (
                SELECT
                    (SELECT COUNT(*) FROM {$this->from}) AS total_count,
                    (SELECT COUNT(*) FROM {$this->cteLimited}) AS limited_count
            )
        SQL;
    }

    /**
     * @param string $cte_name
     * @return void
     * @throws InvalidConfigException
     */
    public function relationCteMaker(string $cte_name): void
    {
        $this->cteList[$cte_name]['select'] = array_merge(['root_number' => "{$this->cteLimited}.unique_number"], $this->cteList[$cte_name]['select']);
        $data = $this->cteList[$cte_name];

        /** (with) ishlatilgan bo'lsa uni sqlga chaqirib qo‘yish */
        if (isset($data['with'])) {
            $this->relationCteMaker($data['with']);
        }

        /** (cte) ishlatilgan bo'lsa uni sqlga chaqirib qo‘yish */
        if (isset($data['cte'])) {
            $this->relationCteMaker($data['cte']);
        }

        if (!in_array($cte_name, array_keys($this->withList ?? []))) {
            $this->join[] = ['LEFT JOIN', $cte_name, 'on' => ["root_number" => $data['select']['root_number']]];
        }

        $this->partHelper($cte_name, $data);
        if (!empty($data['recursive'])) {
            $this->with[$cte_name] = $this->recursive($data, $cte_name);
        } else {
            $this->with[$cte_name] = <<<SQL
            WITH {$cte_name} AS (
                    SELECT DISTINCT ON ({$this->cteLimited}.unique_number) 
                        {$this->selectSql($data['select'])}
                    FROM {$this->cteLimited} AS {$this->cteLimited} {$this->join($this->joinSelf($data, $cte_name), $cte_name, "\n        ")} {$this->condition($data['where'], $cte_name, "\n        WHERE ")}
                )
            SQL;
        }
    }

    /**
     * @param array $data
     * @param string $cte_name
     * @return array
     * @throws InvalidConfigException
     */
    private function joinSelf(array &$data, string $cte_name): array
    {
        if (!isset($data['on']) || !is_array($data['on']) || count($data['on']) > 1) {
            throw new InvalidConfigException("The 'on' property must contain exactly one associative array in the format ['(this)column' => 'root.column'] for relation [$cte_name].");
        }
        $condition = [];
        if (isset($data['where'])) {
            foreach ($data['where'] as $key => $value) {
                if (!str_contains($key, '.')) {
                    $condition[$key] = $value;
                    unset($data['where'][$key]);
                }
            }
        }
        if (isset($data['join']['on'])) {
            $data['join'] = [$data['join']];
        }
        $data['join'] = array_merge([['JOIN', "{$data['table']} AS {$cte_name}", 'on' => array_map(fn($v) => "$this->cteLimited." . $v, $data['on']), 'condition' => $condition]], $data['join']);
        return $data['join'];
    }

    /**
     * @param array $data
     * @param string $cte_name
     * @return string
     * @throws InvalidConfigException
     */
    private function recursive(array $data, string $cte_name): string
    {
        $parent_number = reset($data['recursive']);
        $child = ['select' => []];
        $parent = ['select' => []];
        if (!empty($data['select'])) {
            foreach ($data['select'] as $key => &$value) {
                if ($key === 'unique_number') continue;
                if (preg_match_all('/RECURSIVE\s*\(\s*([^)]+?)\s*\)/i', $value, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $m) {
                        $value = str_replace($m[0], "STRING_AGG({$m[1]}, ' ' ORDER BY level DESC)", $value);
                    }
                } elseif (preg_match('/\b([a-zA-Z_][a-zA-Z0-9_]*)\.([a-zA-Z_][a-zA-Z0-9_]*)\b/', $value, $m)) {
                    $column = $m[2];
                    $child_value = $value;
                    $value = str_replace("{$this->cteLimited}.unique_number", "{$cte_name}.unique_number", $value);
                    $parent_value = str_replace("{$cte_name}.unique_number", "recursive_{$cte_name}.unique_number", $value);
                    if ($key !== 'root_number') {
                        $value = "MAX(CASE WHEN {$cte_name}.level = 1 THEN {$value} END)";
                        $child_value = "child.$column";
                        $parent_value = "parent.$column";
                    }
                    $child['select'][$column] = $child_value;
                    $parent['select'][$column] = $parent_value;
                }
            }
        }
        unset($data['select']['unique_number']);
        $child['join'] = $this->join($this->joinSelf($data, 'child'), $cte_name, "\n        ");
        $parent['join'] = $this->join($this->joinToParent($data, "recursive_$cte_name"), 'parent', "\n        ");
        return <<<SQL
            WITH RECURSIVE recursive_{$cte_name} AS (
                    SELECT
                        {$this->selectSql($child['select'])},
                        child.{$parent_number} AS parent_number,
                        1 AS level
                    FROM {$this->cteLimited} AS {$this->cteLimited} {$child['join']} {$this->condition($data['where'], $cte_name, "\n        WHERE ")}
                
                    UNION ALL
                
                    SELECT
                        {$this->selectSql($parent['select'])},
                        parent.{$parent_number} AS parent_number,
                        recursive_{$cte_name}.level + 1
                    FROM {$data['table']} parent {$parent['join']} {$this->condition($data['where'], "recursive_{$cte_name}", "\n        WHERE ")}
                ),
                {$cte_name} AS (
                    SELECT
                        {$this->selectSql($data['select'])}
                    FROM recursive_{$cte_name} {$cte_name}
                    GROUP BY {$cte_name}.unique_number
                )
            SQL;
    }

    /**
     * @param array $data
     * @param string $cte_name
     * @return array
     */
    private function joinToParent(array $data, string $cte_name): array
    {
        $parent_join = [];
        foreach ($data['join'] as $key => $value) {
            if (str_contains($value[1], 'child')) {
                $value['on'] = ['parent_number' => "parent.".key($data['recursive'])];
            }
            $parent_join[] = [$value[0], $cte_name, 'on' => $value['on']];
        }
        return $parent_join;
    }
}