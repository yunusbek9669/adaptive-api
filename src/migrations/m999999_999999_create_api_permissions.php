<?php

use yii\db\Migration;

/**
 * Migration: API uchun read-only role va user yaratish
 * Maqsad: API faqat SELECT qila oladi, boshqa hech narsa (INSERT, UPDATE, DELETE, CREATE, DROP)
 * Xavfsizlik: SQL injection himoyasi, idempotent execution
 */
class m999999_999999_create_api_permissions extends Migration
{
    private $dbName;
    private $roleName;
    private $userName;
    private $password;

    /**
     * @throws \Exception
     */
    public function init()
    {
        parent::init();

        // Environment o'zgaruvchilarni olish
        $this->dbName   = getenv('API_DB_NAME');
        $this->roleName = getenv('API_DB_ROLE');
        $this->userName = getenv('API_DB_USER');
        $this->password = getenv('API_DB_PASSWORD');

        // Validatsiya
        if (!$this->roleName || !$this->userName || !$this->password) {
            throw new \Exception("API_DB_ROLE, API_DB_USER, API_DB_PASSWORD environment variables must be set.");
        }

        // DSN dan database nomini olish
        $dsn = \Yii::$app->db->dsn;
        if (!$this->dbName && preg_match('/dbname=([^;]+)/', $dsn, $matches)) {
            $this->dbName = $matches[1];
        } elseif (!$this->dbName) {
            throw new \Exception("DB name is not found in DSN or environment.");
        }

        // Input validatsiya (SQL injection himoyasi)
        if (!$this->isValidIdentifier($this->roleName)) {
            throw new \Exception("Invalid role name format.");
        }
        if (!$this->isValidIdentifier($this->userName)) {
            throw new \Exception("Invalid user name format.");
        }
    }

    public function safeUp()
    {
        $db = \Yii::$app->db;

        echo "Creating read-only API role and user...\n";

        // 1. API Role yaratish (agar mavjud bo'lmasa)
        if (!$this->roleExists($this->roleName)) {
            echo "Creating role: {$this->roleName}\n";
            $this->execute("CREATE ROLE " . $db->quoteColumnName($this->roleName) . " NOINHERIT");
        } else {
            echo "Role {$this->roleName} already exists, skipping...\n";
        }

        // 2. API User yaratish (agar mavjud bo'lmasa)
        if (!$this->roleExists($this->userName)) {
            echo "Creating user: {$this->userName}\n";
            // Password xavfsiz escape qilish
            $escapedPassword = pg_escape_string($db->pdo, $this->password);
            $this->execute("CREATE ROLE " . $db->quoteColumnName($this->userName) .
                " LOGIN PASSWORD '{$escapedPassword}'");
        } else {
            echo "User {$this->userName} already exists, skipping...\n";
        }

        // 3. User ga role berish
        echo "Granting role {$this->roleName} to user {$this->userName}\n";
        $this->execute("GRANT " . $db->quoteColumnName($this->roleName) .
            " TO " . $db->quoteColumnName($this->userName));

        // 4. Database ga CONNECT ruxsati
        echo "Granting CONNECT on database\n";
        $this->execute("GRANT CONNECT ON DATABASE " . $db->quoteColumnName($this->dbName) .
            " TO " . $db->quoteColumnName($this->roleName));

        // 5. Public schema uchun USAGE ruxsati
        echo "Granting USAGE on schema public\n";
        $this->execute("GRANT USAGE ON SCHEMA public TO " . $db->quoteColumnName($this->roleName));

        // 6. Barcha mavjud jadvallar uchun SELECT ruxsati
        echo "Granting SELECT on all existing tables\n";
        $this->execute("GRANT SELECT ON ALL TABLES IN SCHEMA public TO " .
            $db->quoteColumnName($this->roleName));

        // 7. Barcha mavjud sequencelar uchun SELECT ruxsati
        echo "Granting SELECT on all existing sequences\n";
        $this->execute("GRANT SELECT ON ALL SEQUENCES IN SCHEMA public TO " .
            $db->quoteColumnName($this->roleName));

        // 8. Kelajakda yaratilgan jadvallar uchun default SELECT ruxsati
        echo "Setting default privileges for future tables\n";
        $this->execute("
            ALTER DEFAULT PRIVILEGES IN SCHEMA public
            GRANT SELECT ON TABLES TO " . $db->quoteColumnName($this->roleName) . "
        ");

        // 9. Kelajakda yaratilgan sequencelar uchun default SELECT ruxsati
        echo "Setting default privileges for future sequences\n";
        $this->execute("
            ALTER DEFAULT PRIVILEGES IN SCHEMA public
            GRANT SELECT ON SEQUENCES TO " . $db->quoteColumnName($this->roleName) . "
        ");

        // 10. Faqat public schema ko'rinsin (xavfsizlik)
        echo "Restricting search_path to public schema only\n";
        $this->execute("ALTER ROLE " . $db->quoteColumnName($this->userName) .
            " SET search_path = public");

        // 11. Connection limit (ixtiyoriy, lekin tavsiya etiladi)
        echo "Setting connection limit\n";
        $this->execute("ALTER ROLE " . $db->quoteColumnName($this->userName) .
            " CONNECTION LIMIT 100");

        // 12. Statement timeout (ixtiyoriy, lekin tavsiya etiladi - 30 soniya)
        echo "Setting statement timeout\n";
        $this->execute("ALTER ROLE " . $db->quoteColumnName($this->userName) .
            " SET statement_timeout = '30s'");

        // 13. REVOKE barcha xavfli permissionlar (double-check)
        echo "Explicitly revoking dangerous permissions\n";
        $dangerousPrivileges = ['INSERT', 'UPDATE', 'DELETE', 'TRUNCATE', 'REFERENCES', 'TRIGGER'];
        foreach ($dangerousPrivileges as $priv) {
            $this->execute("REVOKE {$priv} ON ALL TABLES IN SCHEMA public FROM " .
                $db->quoteColumnName($this->roleName));
        }

        // 14. REVOKE schema creation va modification
        $this->execute("REVOKE CREATE ON SCHEMA public FROM " .
            $db->quoteColumnName($this->roleName));

        echo "✅ API permissions configured successfully!\n";
        echo "Role: {$this->roleName} (read-only)\n";
        echo "User: {$this->userName}\n";
        echo "Permissions: SELECT only on all tables and sequences\n";
    }

    public function safeDown()
    {
        $db = \Yii::$app->db;

        echo "Removing API role and user...\n";

        // 1. User dan role revoke qilish
        if ($this->roleExists($this->userName)) {
            echo "Revoking role from user\n";
            try {
                $this->execute("REVOKE " . $db->quoteColumnName($this->roleName) .
                    " FROM " . $db->quoteColumnName($this->userName));
            } catch (\Exception $e) {
                echo "Warning: " . $e->getMessage() . "\n";
            }
        }

        // 2. Active connectionlarni terminate qilish (agar mavjud bo'lsa)
        if ($this->roleExists($this->userName)) {
            echo "Terminating active connections for user\n";
            try {
                $this->execute("
                    SELECT pg_terminate_backend(pid)
                    FROM pg_stat_activity
                    WHERE usename = " . $db->quoteValue($this->userName) . "
                ");
            } catch (\Exception $e) {
                echo "Warning: " . $e->getMessage() . "\n";
            }
        }

        // 3. User o'chirish
        if ($this->roleExists($this->userName)) {
            echo "Dropping user: {$this->userName}\n";
            $this->execute("DROP ROLE IF EXISTS " . $db->quoteColumnName($this->userName));
        }

        // 4. Role o'chirish
        if ($this->roleExists($this->roleName)) {
            echo "Dropping role: {$this->roleName}\n";
            $this->execute("DROP ROLE IF EXISTS " . $db->quoteColumnName($this->roleName));
        }

        echo "✅ API role and user removed successfully!\n";
    }

    /**
     * Role yoki user mavjudligini tekshirish
     *
     * @param string $roleName
     * @return bool
     */
    private function roleExists($roleName)
    {
        $db = \Yii::$app->db;
        $exists = $db->createCommand(
            "SELECT 1 FROM pg_roles WHERE rolname = :rolename"
        )
            ->bindValue(':rolename', $roleName)
            ->queryScalar();

        return $exists !== false;
    }

    /**
     * SQL identifier validatsiya (SQL injection himoyasi)
     * Faqat harf, raqam va underscore ruxsat etiladi
     *
     * @param string $identifier
     * @return bool
     */
    private function isValidIdentifier($identifier)
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier) === 1;
    }
}