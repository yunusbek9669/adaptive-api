<?php

namespace Yunusbek\AdaptiveApi\builders;

use Yunusbek\AdaptiveApi\ASTValidator;
use Yunusbek\AdaptiveApi\CteConstants;
use Yunusbek\AdaptiveApi\traits\CteToolsTrait;
use Throwable;
use yii\base\Model;
use yii\db\Exception;
use yii\db\ExpressionInterface;

class SqlBuilder
{
    use CteToolsTrait;

    public array $params;
    public array|string $select;
    public array|string $join;
    public array $with = [];
    public string $from;
    public string $cteLimited;
    public array $result;

    public array $cteList = [];

    /**
     * @throws \Exception
     * @throws Throwable
     */
    protected function finalBuilder(array $params, string $dataTypeList, array $callbackList): array
    {
        if (!empty($this->select))
        {
            try {
                $resultData = $this->queryBuilder($this->sqlParting(), $params, $dataTypeList);
            } catch (Exception $e) {
                throw new \Exception('⛔️ '.preg_replace('/SQLSTATE\[\d+\]: /', '', self::modelErrorsToString($e)));
            }

            return $this->jsonToArray($this->countable ? $resultData : $resultData[0], $params, $callbackList);
        }
        return [];
    }

    /** Querydan qaytgan JSON natijani ARRAYga o‘girish va qayta saralash
     * @param array $data
     * @param array $params
     * @param array $callbackList
     * @return array
     * @throws Throwable
     */
    private function jsonToArray(array $data, array $params, array $callbackList): array
    {
        if (!empty($data)) {
            $lastId = $data[0]['last_number'] ?? null;
            $remainingCount = $data[0]['remaining_count'] ?? null;
            foreach ($data as &$value)
            {
                unset($data['root_number'], $data['last_number'], $data['remaining_count']);

                if (is_array($value)) {
                    $value = $this->jsonToArray($value, $params, $callbackList);
                } elseif (is_string($value))
                {
                    //json to array - json qismi aniqlansa arrayga o‘girish
                    if ((str_starts_with($value, '[{') || str_starts_with($value, '{'))) {
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $value = $this->jsonToArray($decoded, $params, $callbackList);
                            continue;
                        }
                    }

                    //files - fayl manzillari orqali asl fayllarni topib base64 formatga o‘girish
                    if (str_starts_with($value, getenv('UPLOAD_FOLDER_PATH')) || str_starts_with($value, '/'.getenv('UPLOAD_FOLDER_PATH')) || str_starts_with($value, substr(getenv('UPLOAD_FOLDER_PATH'), 1))) {
                        $filePath = \Yii::getAlias('@webroot') . '/' . ltrim($value, '/');
                        if (file_exists($filePath)) {
                            $value = base64_encode(file_get_contents($filePath));
                        } else {
                            $value = null;
                        }
                    }
                    //callback - tashqaridan yuborilgan callback functionlarni ishlatish
                    elseif (!empty($callbackList)) {
                        foreach ($callbackList as $textPattern => $callback) {
                            if (is_callable($callback) && preg_match($textPattern, $value, $matches)) {
                                $value = call_user_func($callback, $matches[2], $matches[1]);
                            }
                        }
                    }
                }
            }

            if ($lastId !== null) {
                $data['last_number'] = $lastId;
            }
            if ($remainingCount !== null) {
                $data['left'] = $remainingCount;
            }
        }

        return $data;
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    private function queryBuilder(array $sqlPart, array $params, string $dataTypeList): array
    {
        $sql = $this->sqlCollector($sqlPart, $params, $dataTypeList);
        $validator = new ASTValidator();
        $issues = $validator->validate($sql);
        if (!empty($issues)) {
            $errors = '';
            foreach ($issues as $issue) {
                ASTValidator::errorMessage($issue['value'], $issue['reason']);
            }
            throw new \Exception($errors);
        }
        if (getenv('API_DB_HOST') && getenv('API_DB_PORT') && getenv('API_DB_NAME')) {
            $dsn = 'pgsql:host='.getenv('API_DB_HOST').';port='.getenv('API_DB_PORT').';dbname='.getenv('API_DB_NAME');
        } else {
            $dsn = \Yii::$app->db->dsn;
        }
        $db = new \yii\db\Connection([
            'dsn' => $dsn,
            'username' => getenv('API_DB_USER'),
            'password' => getenv('API_DB_PASSWORD'),
        ]);
        $param_list = [
            ':last_number' => (int)($params['last_number'] ?? null),
            ':limit' => (int)($params['count'] ?? 1)
        ];
        foreach ($params['query_params'] ?? [] as $key => $param) {
            $param_list[$key] = $param;
        }
        return $db->createCommand($sql)->bindValues($param_list)->queryAll();
    }

    private function sqlCollector(array $sqlPart, array $params, string $dataTypeList): string
    {
        $result = null;
        if ($dataTypeList === CteConstants::ROOT_RELATION_DATA_TYPE) {
            $result = <<<SQL
                {$sqlPart['with_cte']}
                SELECT DISTINCT ON (limitedRoot.unique_number) 
             {$sqlPart['select']},
                    MAX(limitedRoot.unique_number) OVER () AS last_number,
                    GREATEST(counts.total_count - counts.limited_count, 0) AS remaining_count
                FROM limitedRoot limitedRoot
                {$sqlPart['join']}
                CROSS JOIN cte_counts counts
            SQL;
        } elseif ($dataTypeList === CteConstants::REFERENCE_DATA_TYPE) {
            $result = <<<SQL
                {$sqlPart['with_cte']}
                SELECT
                    {$sqlPart['select']},
                    (SELECT MAX(unique_number) FROM {$sqlPart['from']}) AS last_number,
                    remaining.remaining_count - COUNT(*) OVER () AS remaining_count
                FROM {$sqlPart['from']} AS {$sqlPart['from']}
                {$sqlPart['join']} 
                CROSS JOIN remaining
            SQL;

        }
        return $result;
    }


    /** (SQL)ning qismlarini tayyorlash (CTE, SELECT, JOIN)
     * @return array
     */
    private function sqlParting(): array
    {
        $result = [
            'with_cte' => '',
            'select' => '',
            'from' => '',
            'join' => ''
        ];

        // with qismini yig‘ib olish
        if (!empty($this->with)) {
            $array = array_unique(array_values($this->with));
            $result['with_cte'] = 'WITH ';
            foreach ($array as $cte) {
                if (stripos($cte, ' recursive ') !== false) {
                    $result['with_cte'] .= 'RECURSIVE ';
                    break;
                }
            }
            $result['with_cte'] .= preg_replace('/WITH|RECURSIVE\s+/i', '', implode(",\n   ", $array));
        }

        // select qismini yig‘ib olish
        if (!empty($this->select)) {
            if (is_array($this->select)) {
                $result['select'] = implode(",\n ", $this->select);
            } else {
                $result['select'] = "\n ".$this->select;
            }
        }

        // join qismini yig‘ib olish
        if (!empty($this->join)) {
            $join_ready = [];
            if (is_array($this->join)) {
                foreach ($this->join as $join) {
                    if (gettype($join) === 'array') {
                        $join_ready[] = $this->join([$join], $this->from);
                    } else {
                        $join_ready[] = $join;
                    }
                }
                $result['join'] = implode("\n    ", array_unique(array_values($join_ready)));
            } else {
                $result['join'] = $this->join;
            }
        }

        // join qismini yig‘ib olish
        if (!empty($this->from)) {
            $result['from'] = $this->from;
        }
        return $result;
    }


    /**
     * @throws \Exception
     * @throws Throwable
     */
    protected function jsonBuilder(array|string &$dataForm, string $dataTypeList, array $callbackList): array
    {
        $error = '';
        $iteration = 0;
        $data_types = [];
        ASTValidator::makeSelectForm($dataForm, $data_types, $dataTypeList, $error, $iteration);
        if (!empty($error)) {
            return [$error];
        }
        if (is_array($dataForm)) {
            foreach ($dataForm as $key => $form) {
                $this->select[] = $this->select($form, $key);
            }
        } else {
            $this->select[] = $this->select($dataForm);
        }
        if ($dataTypeList === CteConstants::ROOT_RELATION_DATA_TYPE) {
            $this->makingRootRelationData($data_types);
        } elseif ($dataTypeList === CteConstants::REFERENCE_DATA_TYPE) {
            $this->makingReferenceData($data_types);
        }
        return $this->finalBuilder($this->params, $dataTypeList, $callbackList);
    }

    /**
     * @throws \Exception
     */
    private function makingRootRelationData(array $data_types): void
    {
        $this->rootCteMaker();
        foreach ($data_types as $key => $data_type) {
            if (ASTValidator::rootRelationDataTypes($key)) {
                $this->relationCteMaker($key);
            } else {
                throw new \Exception("⚠️ Unknown alias used: '$key'");
            }
        }
    }

    /**
     * @throws \Exception
     */
    protected function makingReferenceData(array $data_types): void
    {
        ASTValidator::multipleRoot($data_types);
        foreach ($data_types as $key => $data_type) {
            if (ASTValidator::referenceDataTypes($key)) {
                if (empty($this->join) || in_array($key, array_column($this->join, 1))) {
                    $this->referenceCteMaker($key, in_array('root', $data_type));
                } else {
                    throw new \Exception("⚠️ The alias '$key' is not among the related tables defined in the query.");
                }
            } else {
                throw new \Exception("⚠️ Unknown alias used: '$key'");
            }
        }
    }

    public static function modelErrorsToString($model): string
    {
        if (!$model instanceof Model)
        {
            $error_reason = '';
            if ($model instanceof \Throwable || $model instanceof \Exception) {
                if (preg_match('/LINE \d+:\s*(.*)/', $model->getMessage(), $match)) {
                    $error_reason = ' ―――― ' . trim($match[1]);
                }
                $explode = explode("\n", trim($model->getMessage()));
                return ($explode[0] ?? get_class($model)) . $error_reason;
            }
            return is_string($model) ? $model : json_encode($model);
        }
        $errors = $model->getErrors();
        $string = "";
        foreach ($errors as $error)
        {
            $string = $error[0] . " " . PHP_EOL . $string;
        }

        return $string;
    }
}