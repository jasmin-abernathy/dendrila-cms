<?php
declare(strict_types=1);
namespace Dendrila\Cms\Installation;
use PDO;
final readonly class Migrator
{
    public function __construct(private PDO $pdo){}
    public function run():array {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (migration_id VARCHAR(190) NOT NULL PRIMARY KEY,applied_at DATETIME(6) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $applied=$this->pdo->query('SELECT migration_id FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);$done=[];
        foreach(glob(dirname(__DIR__,2).'/database/migrations/*.sql')?:[] as $file){
            $id=basename($file);if(in_array($id,$applied,true))continue;
            $delimiter=';';$buffer='';
            foreach(file($file,FILE_IGNORE_NEW_LINES) as $line){
                if(preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i',$line,$match)){$delimiter=$match[1];continue;}
                $buffer.=$line."\n";
                if(str_ends_with(rtrim($line),$delimiter)){$sql=trim(substr(rtrim($buffer),0,-strlen($delimiter)));$buffer='';if($sql!=='')$this->pdo->exec($sql);}
            }
            if(trim($buffer)!=='')throw new \RuntimeException('Unterminated migration.');
            $q=$this->pdo->prepare('INSERT INTO schema_migrations VALUES (?,UTC_TIMESTAMP(6))');$q->execute([$id]);$done[]=$id;
        }
        return $done;
    }
}
