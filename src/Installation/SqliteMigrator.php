<?php
declare(strict_types=1);
namespace Dendrila\Cms\Installation;
use Dendrila\Cms\Storage\SqliteConnection;
use PDO;
use RuntimeException;

/** Converts versioned CMS schema to SQLite for single-site installations.
 * Identical logical constraints and indexes; no dynamic SQL from visitors.
 */
final readonly class SqliteMigrator
{
    public function __construct(private SqliteConnection $pdo){}
    public function run(): array
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (migration_id TEXT PRIMARY KEY,applied_at TEXT NOT NULL)');
        $applied=$this->pdo->query('SELECT migration_id FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $done=[];
        foreach(glob(dirname(__DIR__,2).'/database/migrations/*.sql')?:[] as $file) {
            $id=basename($file);
            if(in_array($id,$applied,true))continue;
            $content=(string)file_get_contents($file);
            $sql=explode(';',$content);
            $this->pdo->beginTransaction();
            try {
                foreach($sql as $part){
                    $part=trim($part);
                    if($part==='')continue;
                    foreach($this->convert($part) as $command)$this->pdo->exec($command);
                }
                $q=$this->pdo->prepare('INSERT INTO schema_migrations VALUES (?,UTC_TIMESTAMP(6))');
                $q->execute([$id]);
                $this->pdo->commit();
                $done[]=$id;
            } catch(\Throwable $e){
                if($this->pdo->inTransaction())$this->pdo->rollBack();
                throw new RuntimeException('SQLite migration '.$id.' failed: '.$e->getMessage(),0,$e);
            }
        }
        return $done;
    }
    /** @return list<string> */
    private function convert(string $sql):array
    {
        if(preg_match('/^ALTER\s+TABLE\s+(\w+)\s+ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+(\w+)\s+(.+)$/is',$sql,$m)){
            $table=$m[1];$column=$m[2];
            $existing=$this->pdo->query('PRAGMA table_info('.$table.')')->fetchAll();
            foreach($existing as $row)if($row['name']===$column)return [];
            return ['ALTER TABLE '.$table.' ADD COLUMN '.$column.' '.self::types($m[3])];
        }
        if(!preg_match('/^CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?(\w+)\s*\((.*)\)\s*ENGINE\s*=.*$/is',$sql,$m)){
            return [$sql];
        }
        $table=$m[2];$segments=self::split($m[3]);$columns=[];$indexes=[];$auto=false;
        foreach($segments as $segment){
            $segment=trim($segment);
            if($segment==='')continue;
            if(preg_match('/^(UNIQUE\s+)?KEY\s+(\w+)\s*\(([^)]*)\)$/is',$segment,$ix)){
                $indexes[]='CREATE '.($ix[1]!==''?'UNIQUE ':'').'INDEX IF NOT EXISTS '.$ix[2].' ON '.$table.'('.$ix[3].')';
                continue;
            }
            if(preg_match('/^PRIMARY\s+KEY\s*\(\s*event_id\s*\)$/i',$segment) && $auto) continue;
            if(preg_match('/^event_id\s+BIGINT\s+UNSIGNED\s+NOT\s+NULL\s+AUTO_INCREMENT$/i',$segment)){
                $columns[]='event_id INTEGER PRIMARY KEY AUTOINCREMENT';$auto=true;continue;
            }
            $columns[]=self::types($segment);
        }
        $definition='CREATE TABLE '.($m[1]!==''?'IF NOT EXISTS ':'').$table.' (' . implode(",\n",$columns).')';
        array_unshift($indexes,$definition);
        return $indexes;
    }
    private static function types(string $line):string
    {
        $line=preg_replace('/\bENUM\s*\([^)]*\)/i','TEXT',$line);
        $line=preg_replace('/\bBIGINT\s+UNSIGNED\b|\bBIGINT\b|\bSMALLINT\s+UNSIGNED\b|\bINT\s+UNSIGNED\b|\bINT\b|\bTINYINT\s*\(1\)/i','INTEGER',$line);
        $line=preg_replace('/\bVARCHAR\s*\(\d+\)|\bCHAR\s*\(\d+\)|\bDATETIME\s*\(6\)|\bLONGTEXT\b|\bTEXT\b/i','TEXT',$line);
        $line=preg_replace('/\bBOOLEAN\b/i','INTEGER',$line);
        // SQLite accepts TRUE/FALSE defaults with BOOLEAN-affinity INTEGER columns.
        return $line;
    }
    /** Split columns on commas outside nested SQL expressions. */
    private static function split(string $input):array
    {
        $parts=[];$buffer='';$depth=0;$quote=false;$n=strlen($input);
        for($i=0;$i<$n;$i++){
            $c=$input[$i];
            if($c==="'" && ($i===0||$input[$i-1]!=='\\'))$quote=!$quote;
            if(!$quote){
                if($c==='(')$depth++;
                elseif($c===')')$depth--;
                elseif($c===','&&$depth===0){
                    $parts[]=$buffer;$buffer='';continue;
                }
            }
            $buffer.=$c;
        }
        $parts[]=$buffer;
        return $parts;
    }
}
