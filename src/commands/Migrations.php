<?php

namespace Yunusbek\AdaptiveApi\commands;

use Yii;
use yii\base\Exception;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\BaseConsole;
use yii\helpers\FileHelper;

class Migrations extends Controller
{
    public $defaultAction = 'generate';

    /**
     * @throws Exception
     */
    public function actionGenerate()
    {
        $migrationClassName = 'm' . gmdate('ymd_His') . '_create_client_api_schema_permissions_table';

        $migrationCode = <<<PHP
<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%client_api_schema_permissions}}`.
 */
class {$migrationClassName} extends Migration
{
    public function safeUp()
    {
        \$this->createTable('{{%client_api_schema_permissions}}', [
            'id' => \$this->primaryKey(),
            'client_domain' => \$this->string(255)->notNull(),
            'client_ip' => \$this->string(45)->null(), // IPv4/IPv6
            'schema' => 'JSONB NOT NULL',
            'filter' => 'JSONB NULL',
            'created_at' => \$this->integer()->notNull(),
            'updated_at' => \$this->integer()->notNull(),
        ]);

        // Domen bo‘yicha tezkor qidiruv uchun unique index
        \$this->createIndex(
            'idx-client_api_schema_permissions-client_domain',
            '{{%client_api_schema_permissions}}',
            'client_domain',
            true
        );
        
        // IP bo‘yicha tezkor qidiruv uchun unique index
        \$this->createIndex(
            'idx-client_api_schema_permissions-client_ip',
            '{{%client_api_schema_permissions}}',
            'client_ip',
            true
        );

        // JSONB ustida GIN index (tezkor qidiruv va filterlash uchun)
        \$this->execute("CREATE INDEX idx_client_api_schema_permissions_schema_gin ON {{%client_api_schema_permissions}} USING GIN (schema);");
        \$this->execute("CREATE INDEX idx_client_api_schema_permissions_filter_gin ON {{%client_api_schema_permissions}} USING GIN (filter);");
    }

    public function safeDown()
    {
        \$this->dropIndex('{{%idx-client_api_schema_permissions-client_domain}}', '{{%client_api_schema_permissions}}');
        \$this->dropIndex('{{%idx-client_api_schema_permissions-client_ip}}', '{{%client_api_schema_permissions}}');
        \$this->dropTable('{{%client_api_schema_permissions}}');
    }
}
PHP;

        $dir = Yii::getAlias('@app/migrations');

        $filePath = $dir . '/' . $migrationClassName . '.php';
        if ($this->confirm("Create new migration '$filePath'?")) {
            if (FileHelper::createDirectory($dir) === false || file_put_contents($filePath, $migrationCode, LOCK_EX) === false) {
                $this->stdout("Failed to create new migration.\n", BaseConsole::FG_RED);
                return ExitCode::IOERR;
            }

            $this->stdout("New migration created successfully.\n", BaseConsole::FG_GREEN);
        }

        return ExitCode::OK;
    }
}