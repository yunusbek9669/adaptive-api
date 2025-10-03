<?php

namespace Yunusbek\AdaptiveApi\traits;

use Yii;
use yii\base\InvalidConfigException;
use Yunusbek\AdaptiveApi\CteConstants;

trait JsonTrait
{
    private static array $json = [];

    /**
     * @throws InvalidConfigException
     */
    public static function getJson()
    {
        $path = getenv('API_SCHEMA_FILE_PATH') ?? CteConstants::CTE_ROOT_SCHEMA_PATH;
        $jsonFile = str_replace('//', DIRECTORY_SEPARATOR, Yii::getAlias('@webroot') .'/'. $path.'/api-schema.json');
        if (file_exists($jsonFile)) {
            $jsonContent = file_get_contents($jsonFile);
            $decoded = json_decode($jsonContent, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                self::$json = $decoded ?? [
                    'root_relation' => [],
                    'reference' => [],
                ];
            } else {
                throw new InvalidConfigException("Invalid JSON structure detected in {$jsonFile}.");
            }
        }
        return self::$json;
    }
}