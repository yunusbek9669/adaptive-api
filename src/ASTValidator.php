<?php

namespace Yunusbek\AdaptiveApi;

use Yunusbek\AdaptiveApi\builders\SqlBuilder;
use Yunusbek\AdaptiveApi\CteConstants;
use PHPSQLParser\PHPSQLParser;
use yii\db\Exception;
use Yunusbek\AdaptiveApi\traits\JsonTrait;

class ASTValidator
{
    use JsonTrait;
    private array $errors = [];
    public static array $dangWords = [
        'insert', 'delete', 'drop', 'update', 'alter', 'truncate', 'create', 'exec', '--', ';'
    ];

    private array $dangerousWords;

    public function __construct()
    {
        $this->dangerousWords = self::$dangWords;
    }

    private array $dangerousOperators = [';', '--', '/*', '*/', '#'];


    public static function rootRelationDataTypes(string $type): bool
    {
        return in_array($type, JsonTrait::$json['root_relation']);
//        return in_array($type, CteConstants::employeeDataTypeList());
    }

    public static function referenceDataTypes(string $type): bool
    {
        return in_array($type, JsonTrait::$json['reference']);
//        return in_array($type, CteConstants::referenceDataTypeList());
    }

    public function validate(string $sql): array
    {
        $this->errors = [];

        // Break into fragments if multiple statements exist
        $statements = preg_split('/(;|--|#|\/\*)[\s]*/', $sql);
        if (!$this->getDangerousOperators($statements)) {
            foreach ($statements as $i => $statement) {
                $statement = trim($statement);
                if ($statement === '') continue;

                try {
                    $parser = new PHPSQLParser($statement, true);
                    $parsed = $parser->parsed;
                    $this->traverse($parsed, "[statement_{$i}]");
                } catch (\Throwable $e) {
                    $this->errors[] = [
                        'path' => "[statement_{$i}]",
                        'value' => preg_replace('/SQLSTATE\[\d+\]: /', '', SqlBuilder::modelErrorsToString($e)),
                        'reason' => 'SQL parse failed or invalid syntax',
                    ];
                }
            }
        }

        return $this->errors;
    }

    private function traverse(array $tree, string $path = ''): void
    {
        foreach ($tree as $key => $node) {
            $currentPath = $path . "[$key]";
            if (is_array($node)) {
                if ($this->isDangerous($node, $currentPath)) {
                    // already added in isDangerous
                    continue;
                }
                $this->traverse($node, $currentPath);
            } elseif (is_string($node)) {
                if ($this->containsDangerousString($node)) {
                    $this->errors[] = [
                        'path' => $currentPath,
                        'value' => $node,
                        'reason' => 'String contains dangerous word'
                    ];
                }
            }
        }
    }

    private function isDangerous(array $node, string $path = ''): bool
    {
        $expr = $node['base_expr'] ?? '';

        if (isset($node['expr_type']) && $node['expr_type'] === 'const') {
            if ($this->containsDangerousString($expr)) {
                $this->errors[] = [
                    'path' => $path,
                    'value' => $expr,
                    'reason' => 'Constant contains dangerous word'
                ];
                return true;
            }
        }

        if (isset($node['expr_type']) && $node['expr_type'] === 'operator') {
            if (in_array(trim($expr), $this->dangerousOperators)) {
                $this->errors[] = [
                    'path' => $path,
                    'value' => $expr,
                    'reason' => 'Operator is dangerous'
                ];
                return true;
            }
        }

        if ($this->containsDangerousString($expr)) {
            $this->errors[] = [
                'path' => $path,
                'value' => $expr,
                'reason' => 'Expression contains dangerous word'
            ];
            return true;
        }

        return false;
    }

    private function containsDangerousString(string $expr): bool
    {
        $expr = trim($expr);

        // Ignore safe string literals
        if (preg_match("/^['\"].*['\"]$/", $expr)) {
            return false;
        }
        $expr = strtolower($expr);
        $expr = preg_replace('/\s+/', ' ', $expr); // collapse spaces

        // Tokenize for exact match
        $tokens = preg_split('/[^a-z_]+/', $expr); // only letters and underscore
        $parts = preg_split('/[;#-]{1,2}/', $expr);
        foreach ($parts as $part) {
            $part = trim($part);
            foreach ($this->dangerousWords as $word) {
                // optional: stricter matching with word boundaries
                if (preg_match('/\b' . preg_quote($word, '/') . '\b/', $part) || in_array($word, $tokens, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getDangerousOperators(array $statements): bool
    {
        if (count($statements) > 1) {
            $pos = strrpos($statements[0], "\n");
            if ($pos !== false) {
                $tail = trim(substr($statements[0], $pos + 1));
            } else {
                $tail = trim($statements[0]);
            }
            if (strlen($tail) > 55) {
                $tail = '...'.substr($tail, -50, 50);
            }
            $this->errors[] = [
                'path' => '[raw]',
                'value' => $tail,
                'reason' => 'Contains dangerous SQL delimiters (;, --, /*, #)'
            ];
            return true;
        }
        return false;
    }

    /**
     * @throws \Exception
     */
    public static function errorMessage($value, $reason, $level = 'error') {
        if ($level === 'warning') {
            $error = "⚠️ {$reason}! ";
            $error .= "Found near: {$value}";
        } else {
            $error = "🚨 ‼️Potentially dangerous SQL expression detected‼️ ";
            $error .= "Found near: {$value}  ―――― ";
            $error .= " ⛔️ Reason: {$reason}";
        }
        throw new \Exception($error);
    }

    /**
     * @throws \Exception
     */
    public static function detectDangerousAlias(string $key, array|string $form): bool
    {
        $near = trim(json_encode([$key => $form]), '"');
        $explode = explode(' ', $key);
        $flipped = array_flip($explode); // O(m)
        foreach (ASTValidator::$dangWords as $val) {
            if (isset($flipped[$val])) {
                self::errorMessage("{$near}", 'Alias name contains dangerous word');
            }
        }
        return false;
    }

    /** SELECT ichiga 'jsonb_build_object' qilib berish
     * @param array|string $dataForm
     * @param array $data
     * @param string $dataTypeList
     * @param string $error
     * @param int $iteration
     * @throws \Exception
     */
    public static function makeSelectForm(array|string &$dataForm, array &$data, string $dataTypeList, string &$error, int &$iteration): void
    {
        if (is_array($dataForm)) {
            $newDataForm = [];
            foreach ($dataForm as $associativeKey => &$form) {
                if (!ASTValidator::detectDangerousAlias($associativeKey, $form) && (empty($associativeKey) || strpbrk($associativeKey, " '")))
                {
                    $near = trim(json_encode([$associativeKey => $form]), '"');
                    ASTValidator::errorMessage("{$near}", 'Alias name contains invalid word', 'warning');
                }
                if (is_array($form)) {
                    self::makeSelectForm($form, $data, $dataTypeList, $error, $iteration);
                    foreach ($data as $key => $value) {
                        $data[$key] = $value;
                    }
                } else {
                    self::sanitizeExpression($form, $data, $dataTypeList, $iteration);
                    if ($error) { break; }
                }
                $newDataForm[$associativeKey] = $form;
            }
            $dataForm = $newDataForm;
        } else {
            self::sanitizeExpression($dataForm, $data, $dataTypeList, $iteration);
        }
    }

    /** (SQL Injection)larni tekshirish
     * @param string $form
     * @param array $data
     * @param string $dataTypeList
     * @param int $iteration
     * @return void
     * @throws \Exception
     */
    private static function sanitizeExpression(string &$form, array &$data, string $dataTypeList, int &$iteration): void
    {
        if ($dataTypeList === CteConstants::REFERENCE_DATA_TYPE) {
            if (preg_match('/<\{([a-zA-Z_][a-zA-Z0-9_]*)\}>\.([a-zA-Z0-9_]+|\*)/', $form, $m)) {
                $alias = $m[1];
                if (!self::$dataTypeList($m[1])) {
                    throw new \Exception("⚠️ Unknown alias used: '{$m[1]}'");
                }
                $form = str_replace("<{{$alias}}>", "{{$alias}}", $form);
                $data[$alias][$iteration] = $data[$alias][$iteration] ?? "relation";
            }
        }
        /** Ruxsat etilgan functionlar */
        $allowedSqlFunctions = ['CASE', 'WHEN', 'THEN', 'ELSE', 'END', 'TO_CHAR', 'TO_TIMESTAMP', 'CONCAT', 'COALESCE', 'CAST', 'NULLIF', 'UPPER', 'LOWER', 'LENGTH', 'SUBSTRING', 'TRIM', 'POSITION'];

        /** xavfsiz sql ifodalari */
        $safeSqlPattern = '/^[a-zA-Z0-9_$. ()=><\'",\-+*\/:]+$/u';

        /** 1. Noto‘g‘ri alias va columnni ajratish (aniqlik uchun) */
        if (preg_match_all('/\{([^}]*)\}\.([^\s\(\),+*\/\-]+)/', $form, $preMatches, PREG_SET_ORDER)) {
            foreach ($preMatches as $m) {
                $invalidAlias = $m[1];
                $invalidColumn = $m[2];

                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $invalidAlias)) {
                    throw new \Exception("⛔️ Invalid alias name in '{$m[0]}'");
                }

                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$|^\*$/', $invalidColumn)) {
                    throw new \Exception("⛔️ Invalid column name in '{$m[0]}'");
                }
            }
        }

        /** 2. Ifoda ichidagi barcha {alias}.column larni topish */
        if (preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}\.([^\s\(\),+*\/\-]+)/', $form, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $alias = $match[1];
                $column = $match[2];

                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$|^\*$/', $column)) {
                    throw new \Exception("⛔️ Invalid column name in '{$match[0]}'");
                }

                if (!self::$dataTypeList($alias)) {
                    throw new \Exception("⚠️ Unknown alias used: '{$alias}'");
                }

                $form = str_replace($match[0], "{$alias}.{$column}", $form);
                $data[$alias][$iteration] = $data[$alias][$iteration] ?? "root";
            }
        }
        /** 3. Format xatoliklari */
        elseif (preg_match('/^\{[a-zA-Z_][a-zA-Z0-9_]*\}\.$/', $form)) {
            throw new \Exception("⛔️ Missing column name after alias in '$form'. Expected format: {alias}.column");
        } elseif (preg_match('/^\.[a-zA-Z_][a-zA-Z0-9_]*$/', $form)) {
            throw new \Exception("⛔️ Invalid format: missing alias in '$form'. Expected format: {alias}.column");
        } elseif (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*$/', $form)) {
            throw new \Exception("⛔️ Only `{alias}.column` format is allowed! Error: '$form'");
        } elseif (preg_match('/^\{\}\.[a-zA-Z_][a-zA-Z0-9_]*$/', $form)) {
            throw new \Exception("⛔️ Empty alias is not allowed! Error: '$form'");
        }

        /** 4. Xavfsizlik tekshiruvi (funcs + umumiy belgilar) */
        // {alias}.column formatdagi qismlarini olib tashlash
        $exprWithoutBraced = preg_replace('/\{[a-z_][a-z0-9_]*\}\.[a-z_][a-z0-9_]*|\{[a-z_][a-z0-9_]*\}\.\*/i', '', $form);
        // Oddiy alias.column formatdagi qismlarini olib tashlash (agar qolgan bo‘lsa)
        $exprWithoutAliases = preg_replace('/\b[a-z_][a-z0-9_]*\.[a-z_][a-z0-9_]*|\b[a-z_][a-z0-9_]*\.\*/i', '', $exprWithoutBraced);
        // Literal stringlarni olib tashlash
        $exprCleaned = preg_replace("/'[^']*'/", '', $exprWithoutAliases);

        // SQL funksiyalarini tekshirish
        if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}\.\*$/', $form, $m)) {
            $alias = $m[1];
            if (!self::$dataTypeList($alias)) {
                throw new \Exception("⚠️ Unknown alias used: '{$alias}'");
            }
            $form = "$alias.*";
            $data[$alias][$iteration] = $data[$alias][$iteration] ?? "root";
        } elseif (preg_match_all('/\b([A-Z_]{2,})\b/', strtoupper($exprCleaned), $funcMatches)) {
            foreach ($funcMatches[1] as $func) {
                if (!in_array($func, $allowedSqlFunctions)) {
                    $list = implode('|', $allowedSqlFunctions);
                    throw new \Exception("⚠️ Unauthorized SQL function detected: {$func}; 💡 ruxsat etiladi: ({$list})");
                }
            }
        }
        // Umumiy xavfli belgilarga tekshiruv
        elseif (!preg_match($safeSqlPattern, $form)) {
            throw new \Exception("⚠️ Unsafe or invalid SQL expression: '{$form}'");
        }
        $iteration++;
    }

    /** {?} bilan boshlangan tanalar tutib olish (avlodlari bo‘sh emasligiga tekshirish)
     * @param array $data
     * @param string|null $condition
     * @param string|null $type
     * @param string|null $key
     * @return void
     */
    public static function detectIfNull(array $data, string &$condition = null, string $type = null, string $key = null): void
    {
        if (str_starts_with($type, '{?}') || str_starts_with($key, '{?}')) {
            $qualified = [];
            self::findAliasesRecursive($data, $qualified, true);
            if (!empty($qualified)) {
                $condition = self::buildConditionFromAliases($qualified);
            }
        }
    }

    /** "SELECT" {?} bilan boshlangan tanalar ichida foydalanilgan aliaslarni yig‘ish
     * @param array $data
     * @param array $qualified
     * @param bool $active
     * @return void
     */
    private static function findAliasesRecursive(array $data, array &$qualified = [], bool $active = false): void
    {
        foreach ($data as $key => $value) {
            $isConditional = str_starts_with($key, '{?}');
            $currentActive = $active || $isConditional;

            if (is_array($value)) {
                self::findAliasesRecursive($value, $qualified, $currentActive);
            } else if ($currentActive && preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\.([a-zA-Z_][a-zA-Z0-9_]*|\*)/', $value, $m)) {
                $qualified["$m[1].$m[2]::text"] = true;
            }
        }
    }

    /** "SELECT" {?} bilan boshlangan tanalar uchun shart berish
     * @param array $qualified
     * @return string
     */
    private static function buildConditionFromAliases(array $qualified): string
    {
        $condition = [];
        foreach ($qualified as $key => $bool) {
            $condition[] = $key;
        }
        return implode(', ', $condition);
    }

    /** Birnechta asosiy aliaslar mumkin emas
     * @param array $data_types
     * @return void
     * @throws \Exception
     */
    public static function multipleRoot(array $data_types): void
    {
        $multiple_root = array_keys(array_filter($data_types, fn ($items) => in_array('root', $items, true)));
        if (count($multiple_root) > 1) {
            $data = implode(', ', array_keys($multiple_root));
            throw new \Exception("⛔️ Multiple root aliases detected. A query must have only one main table alias: {$data}.");
        }
    }
}