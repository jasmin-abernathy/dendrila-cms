<?php
declare(strict_types=1);
namespace Dendrila\Cms\Storage;

use PDO;
use PDOStatement;

/** SQLite dialect adapter for the existing CMS queries.
 * MySQL remains supported by the ordinary PDO connection.
 * SQL translation is limited to the known, tested CMS statements.
 */
final class SqliteConnection extends PDO
{
    public function __construct(string $dsn)
    {
        if (!str_starts_with($dsn, 'sqlite:')) throw new \InvalidArgumentException('Not a SQLite DSN.');
        parent::__construct($dsn, null, null, [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
            PDO::ATTR_TIMEOUT=>5,
        ]);
        parent::exec('PRAGMA foreign_keys=ON');
        parent::exec('PRAGMA busy_timeout=5000');
        $this->sqliteCreateFunction('UTC_TIMESTAMP', static fn(?int $precision=0):string =>
            gmdate('Y-m-d H:i:s').($precision>0?substr(sprintf('%.6f', microtime(true)), -7):''),
            -1);
        $this->sqliteCreateFunction('JSON_UNQUOTE', static function(?string $value):?string {
            if ($value===null)return null;
            $decoded=json_decode($value,true);
            return is_string($decoded)?$decoded:$value;
        },1);
    }

    public static function translate(string $sql): string
    {
        $sql=preg_replace('/\s+FOR\s+UPDATE\b/i','',$sql);
        $sql=preg_replace('/\bINSERT\s+IGNORE\s+INTO\b/i','INSERT OR IGNORE INTO',$sql);
        $sql=preg_replace('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\b/i','ON CONFLICT DO UPDATE SET',$sql);
        $sql=preg_replace('/\bVALUES\(([A-Za-z][A-Za-z0-9_]*)\)/i','excluded.$1',$sql);
        $sql=preg_replace('/DATE_SUB\(UTC_TIMESTAMP\(6\),\s*INTERVAL\s+(\d+)\s+MINUTE\)/i',"datetime('now','-$1 minutes')",$sql);
        $sql=preg_replace('/DATE_ADD\(UTC_TIMESTAMP\(6\),\s*INTERVAL\s+(\d+)\s+MINUTE\)/i',"datetime('now','+$1 minutes')",$sql);
        // SQLite does not support MySQL's interval expression.
        $sql=preg_replace('/UTC_TIMESTAMP\(6\)\s*-\s*INTERVAL\s+(\d+)\s+MINUTE/i',"datetime('now','-$1 minutes')",$sql);
        return $sql;
    }

    public function prepare(string $query, array $options=[]): PDOStatement|false
    {
        return parent::prepare(self::translate($query),$options);
    }
    public function exec(string $statement): int|false
    {
        return parent::exec(self::translate($statement));
    }
    public function query(string $query, ?int $fetchMode=null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $sql=self::translate($query);
        return $fetchMode===null?parent::query($sql):parent::query($sql,$fetchMode,...$fetchModeArgs);
    }
}
