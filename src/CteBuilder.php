<?php

namespace Yunusbek\AdaptiveApi;

use Yunusbek\AdaptiveApi\builders\SqlBuilder;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use Throwable;
use Yii;

class CteBuilder extends SqlBuilder
{
    private array $root = [];
    public array $withList = [];
    private array $relation = [];
    private array $reference = [];
    private array $queryParams = [];
    private array $tableAttributes = [];
    private array|string $template;
    private array $callbackList = [];
    protected string $data_type;
    public array $result = [];

    public static array $schema = [];

    private function __construct() {}

    public static function root(array $root): self
    {
        $self = new self();
        $self->data_type = CteConstants::ROOT_RELATION_DATA_TYPE;
        $self->cteLimited = CteConstants::CTE_ROOT_LIMITED;
        $self->from = CteConstants::CTE_ROOT;
        $self->root = [$self->from => $root];
        return $self;
    }

    /**
     * @param array $with
     * @return CteBuilder
     * @throws InvalidConfigException
     */
    public function with(array $with): self
    {
        if (!empty($this->withList)) {
            throw new InvalidConfigException('Method with() has already been called. Duplicate calls are not allowed.');
        }
        $this->withList = $with;
        return $this;
    }

    /**
     * @param array $relation
     * @return CteBuilder
     * @throws InvalidConfigException
     */
    public function relation(array $relation): self
    {
        if (!empty($this->relation)) {
            throw new InvalidConfigException('Method relation() has already been called. Duplicate calls are not allowed.');
        }
        $this->relation = $relation;
        return $this;
    }

    /**
     * @param array $reference
     * @return CteBuilder
     */
    public static function reference(array $reference): self
    {
        $self = new self();
        $self->data_type = CteConstants::REFERENCE_DATA_TYPE;
        $self->reference = $reference;
        return $self;
    }

    /**
     * @throws InvalidConfigException
     */
    public function queryParams(array $queryParams): self
    {
        if (!empty($this->queryParams)) {
            throw new InvalidConfigException('Method queryParams() has already been called. Duplicate calls are not allowed.');
        }
        $this->queryParams = $queryParams;
        return $this;
    }

    /**
     * @throws InvalidConfigException
     */
    public function template(array|string $template): self
    {
        if (!empty($this->template)) {
            throw new InvalidConfigException('Method template() has already been called. Duplicate calls are not allowed.');
        }
        $this->template = $template;
        return $this;
    }

    public function setCallback(callable $callback, string $textPattern): self
    {
        $this->callbackList[$textPattern] = $callback;
        return $this;
    }

    /**
     * @throws InvalidConfigException
     * @throws \Exception
     * @throws Throwable
     */
    public function getApi(): array
    {
        /** requests */
        $request = Yii::$app->request;
        if (empty($this->template)) { $this->template = $request->post(); }
        $this->queryParams = array_merge($this->queryParams, $request->get());

        if (
            (!empty($this->reference) && (!empty($this->root) || !empty($this->relation)))
            ||
            (empty($this->reference) && (empty($this->root) || empty($this->relation)))
        ) {
            throw new InvalidConfigException(
                "Invalid configuration: Choose exactly ONE mode — either use reference() OR use root() together with relation()."
            );
        }
        $this->cteList = $this->setCteList($this->root, 'root');
        $this->cteList = array_merge($this->cteList, $this->setCteList($this->withList, 'with'));
        $this->cteList = array_merge($this->cteList, $this->setCteList($this->relation, 'rootRelation'));
        $this->cteList = array_merge($this->cteList, $this->setCteList($this->reference, 'reference'));
        if (empty($this->template)) {
            throw new InvalidConfigException("The 'template' must not be empty.");
        }
        if (empty($this->queryParams)) {
            throw new InvalidConfigException("The 'queryParams' must not be empty.");
        }
        self::$schema = array_keys($this->cteList);

        $this->params = $this->paramsHelper($this->queryParams, ['query_params' => array_diff_key($this->queryParams, array_flip(['count', 'last_number']))], $this->data_type === CteConstants::ROOT_RELATION_DATA_TYPE);
        $this->result = $this->jsonBuilder($this->template, $this->data_type, $this->callbackList);

        if ($this->countable) {
            $last_number = $this->result['last_number'] ?? 0;
            $left = $this->result['left'] ?? 0;
            unset($this->result['last_number'], $this->result['left']);
            return [
                'left' => $left,
                'came_count' => count($this->result),
                'last_number' => $last_number,
                'items' => $this->result
            ];
        } else {
            return $this->result;
        }
    }

    /**
     * @throws Exception|InvalidConfigException
     */
    private function setCteList(array $list, string $type): array
    {
        $schema = Yii::$app->db->schema;
        foreach ($list as $cteKey => &$config)
        {
            if (!isset($config['class']) && empty($config['data']) && !isset($config['table']) && !isset($config['with']) && !isset($config['cte'])) {
                throw new InvalidConfigException("Either the 'class', 'table', 'data', 'with' or 'cte' property must be specified for [$cteKey].");
            } elseif (isset($config['table']) && !is_string($config['table'])) {
                throw new InvalidConfigException("The 'table' property must be a string for [$cteKey].");
            } elseif (isset($config['class']) && !str_contains($config['class'], '\\')) {
                throw new InvalidConfigException("Invalid 'class' property for [$cteKey]: the specified class does not exist or cannot be autoloaded.");
            } elseif (isset($config['class']) && is_string($config['class'])) {
                $config['table'] = $config['class']::tableName();
            } elseif (isset($config['cte']) && is_string($config['cte'])) {
                $config['table'] = $config['cte'];
            } elseif (isset($config['with']) && isset($this->withList[$config['with']])) {
                $config['table'] = $config['with'];
            } elseif (!empty($config['data'])) {
                $config['table'] = $this->convertDataTable($config['data'], $cteKey);
            } elseif (isset($config['with']) && !isset($this->withList[$config['with']])) {
                throw new InvalidConfigException("The value provided through the 'with' property [{$config['with']}] does not match any defined CTE in the query. for [$cteKey].");
            }

            if (isset($config['select']) && !is_array($config['select'])) {
                throw new InvalidConfigException("The 'select' property must be an array for [$cteKey].");
            } elseif (!isset($config['select'])) {
                $config['select'] = array_keys($schema->getTableSchema($config['table'])->columns);
            }
            if ($this->data_type === CteConstants::ROOT_RELATION_DATA_TYPE && $cteKey !== $this->from && (!isset($config['on']) || !is_array($config['on']) || count($config['on']) > 1)) {
                throw new InvalidConfigException("The 'on' property must contain exactly one associative array in the format ['(this)column' => 'root.column'] for relation [$cteKey].");
            }
            if ($this->data_type === CteConstants::ROOT_RELATION_DATA_TYPE && $cteKey !== $this->from && isset($config['recursive']) && (!is_array($config['recursive']) || count($config['recursive']) > 1)) {
                throw new InvalidConfigException("The 'recursive' property must contain exactly one associative array in the format ['(this)column' => 'root.column'] for relation [$cteKey].");
            }
            $config['unique_number'] = $config['unique_number'] ?? 'id';
            $config['where'] = $config['where'] ?? [];
            $config['join'] = $config['join'] ?? [];
            $config['select']  = $this->normalizeSelect($cteKey, array_merge(['unique_number' => $config['unique_number']], $config['select']));
            if (!in_array($type, ['root', 'with'])) {
                $this->tableAttributes[$type][] = $cteKey;
            }
        }
        return $list;
    }
}