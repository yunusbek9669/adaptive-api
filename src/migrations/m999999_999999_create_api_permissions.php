<?php

use yii\db\Migration;

class m999999_999999_create_api_permissions extends Migration
{
    private $dbName;
    private $roleName;
    private $userName;
    private $password;

    /**
     * @throws Exception
     */
    public function init()
    {
        parent::init();
        $this->dbName   = getenv('API_DB_NAME');
        $this->roleName = getenv('API_DB_ROLE');
        $this->userName = getenv('API_DB_USER');
        $this->password = getenv('API_DB_PASSWORD');

        $dsn = \Yii::$app->db->dsn;
        if (!$this->dbName && preg_match('/dbname=([^;]+)/', $dsn, $matches)) {
            $this->dbName = $matches[1];
        } elseif (!$this->dbName) {
            throw new \Exception("DB name is not found.");
        }
    }

    public function safeUp()
    {
        // API role va user
        $this->execute("CREATE ROLE {$this->roleName} NOINHERIT;");
        $this->execute("CREATE ROLE {$this->userName} LOGIN PASSWORD '{$this->password}';");
        $this->execute("GRANT {$this->roleName} TO {$this->userName};");

        // Databasega ulanish ruxsati
        $this->execute("GRANT CONNECT ON DATABASE \"{$this->dbName}\" TO {$this->roleName};");

        // public schema uchun ruxsat
        $this->execute("GRANT USAGE ON SCHEMA public TO {$this->roleName};");

        // SELECT ruxsatlari
        $this->execute("GRANT SELECT ON ALL TABLES IN SCHEMA public TO {$this->roleName};");
        $this->execute("GRANT SELECT ON ALL SEQUENCES IN SCHEMA public TO {$this->roleName};");

        // Kelajakdagi obyektlarga default ruxsat
        $this->execute("
            ALTER DEFAULT PRIVILEGES IN SCHEMA public
            GRANT SELECT ON TABLES TO {$this->roleName};
        ");
        $this->execute("
            ALTER DEFAULT PRIVILEGES IN SCHEMA public
            GRANT SELECT ON SEQUENCES TO {$this->roleName};
        ");

        // Faqat public schema ishlatsin
        $this->execute("ALTER ROLE {$this->userName} SET search_path = public;");
    }

    public function safeDown()
    {
        $this->execute("REVOKE {$this->roleName} FROM {$this->userName};");
        $this->execute("DROP ROLE IF EXISTS {$this->userName};");
        $this->execute("DROP ROLE IF EXISTS {$this->roleName};");
    }
}
