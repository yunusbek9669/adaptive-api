<?php

namespace Yunusbek\AdaptiveApi\traits;

use Yunusbek\AdaptiveApi\ASTValidator;
use yii\db\ExpressionInterface;

trait ReferenceTrait
{
    /**
     * @param string $cte_name
     * @param bool $is_root
     * @return void
     */
    public function referenceCteMaker(string $cte_name, bool $is_root = false): void
    {
        if ($is_root) { $this->from = $cte_name; }
        $data = $this->cteList[$cte_name];
        $from = str_contains(strtolower($data['table']), ' as ') ? $data['table'] : "{$data['table']} AS $cte_name";
        $this->partHelper($cte_name, $data);
        $condition = [];
        foreach ($this->params['condition'] as $key => $val) {
            if ($key === 'unique_number') {
                $condition[$data['unique_number']] = $val;
            } else {
                $condition[$key] = $val;
            }
        }
        $this->with[$cte_name] = <<<SQL
        WITH {$cte_name} AS (
                SELECT 
                    {$this->selectSql($data['select'])}
                FROM {$from} {$this->join($data['join'], $cte_name, "\n        ")} {$this->partOfRoot($cte_name, $data, $condition, $is_root)}
            )
        SQL;
        if ($is_root) {
            $this->with[$cte_name] .= $this->remainingCte($cte_name, $data, $condition);
        }
    }

    /** Asosiyga bog‘langan relation (CTE)lar
     * @param string $cte_name
     * @param array $data
     * @return void
     */
    protected function partHelper(string $cte_name, array &$data): void
    {
        /** Relation */
        if (!empty($data['join'])) {
            if (is_array($data['join'][0])) {
                foreach ($data['join'] as $value) {
                    if (ASTValidator::referenceDataTypes($value[1])) {
                        $this->join[] = $this->addRelation($cte_name, $data, $value, $value[1]);
                        $this->referenceCteMaker($value[1]);
                    }
                }
            } elseif (ASTValidator::referenceDataTypes($data['join'][1])) {
                $this->join[] = $this->addRelation($cte_name, $data, $data['join'], $data['join'][1]);
                $this->referenceCteMaker($data['join'][1]);
            }
        }
        $this->cteList[$cte_name] = $data;
    }

    /** SELECT ichiga relation qilingan tablitsadan unique ustun qo‘shish
     * @param string $cte_name
     * @param array $data
     * @param array $join
     * @param string $subAlias
     * @return array
     */
    private function addRelation(string $cte_name, array &$data, array $join, string $subAlias): array
    {
        $snake_case = $this->camelToSnake($subAlias);
        $data['select'] = array_merge($data['select'], [
            "{$snake_case}_unique_number" => "{$subAlias}.unique_number"
        ]);
        $for_root = $join;
        $for_root['on'] = ["unique_number" => "{$cte_name}.{$snake_case}_unique_number"];
        return $for_root;
    }

    /** Asosiy (CTE)ning qismlari
     * @param string $cte_name
     * @param array $data
     * @param array $condition
     * @param bool $is_root
     * @return string|null
     */
    protected function partOfRoot(string $cte_name, array $data, array $condition, bool $is_root): ?string
    {
        if ($is_root) {
            $this->from = $cte_name;
            return <<<SQL
            
                    WHERE (:last_number::integer IS NULL OR {$cte_name}.{$data['unique_number']} > :last_number::integer) {$this->condition(array_merge($condition, $data['where']), $cte_name, ' AND ')}
                    ORDER BY {$cte_name}.{$data['unique_number']} ASC
                    LIMIT :limit
            SQL;
        }
        return null;
    }

    /** Qoldiq hisoblovchi CTE
     * @param string $cte_name
     * @param array $data
     * @param array $condition
     * @return string
     */
    protected function remainingCte(string $cte_name, array $data, array $condition): string
    {
        $filteredJoins = [];
        $remain_join = $data['join'];
        if (!empty($remain_join) && !is_array($remain_join[0])) {
            $remain_join = [$remain_join];
        }
        foreach ($remain_join as $remainData) {
            if (stripos($remainData[1], ' parent') === false) {
                $filteredJoins[] = $remainData;
            }
        }

        $rootJoin = str_replace("$cte_name.", "{$data['table']}.", $this->join($filteredJoins, $cte_name));
        $and_where = str_replace("$cte_name.", "{$data['table']}.", $this->condition(array_merge($condition, $data['where']), null, " AND "));
        return <<<SQL
        ,
            remaining AS MATERIALIZED (
                SELECT COUNT(*) AS remaining_count
                FROM {$data['table']} 
                {$rootJoin}
                WHERE (:last_number::integer IS NULL OR {$data['table']}.{$data['unique_number']} > :last_number::integer) {$and_where}
            )
        SQL;
    }
}