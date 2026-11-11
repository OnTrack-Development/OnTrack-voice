<?php
declare(strict_types=1);

namespace App\Models {
    final class VoiceDncEntry {
        public static array $blocked=[];
        public int $tenant_id;
        public ?int $account_id=null;
        public string $phone_normalized;
        public string $source='contact_optout';
        public static function query(): DncQuery {return new DncQuery();}
    }
    final class DncQuery {
        private int $tenant=0;
        private string $phone='';
        public function forTenant(int $id):self {$this->tenant=$id;return $this;}
        public function where(string $name,string $value):self {
            if($name!=='phone_normalized')throw new \RuntimeException('unsafe_lookup');
            $this->phone=$value;return $this;
        }
        public function exists():bool {
            return in_array($this->phone,VoiceDncEntry::$blocked[$this->tenant]??[],true);
        }
    }
}
namespace {
    require_once __DIR__.'/../saas/app/Support/VoiceDncBridge.php';
    use App\Models\VoiceDncEntry;
    use App\Support\VoiceDncBridge;
    function check_dnc(bool $ok,string $label):void {
        if(!$ok)throw new \RuntimeException('LARAVEL_DNC_BRIDGE_FAIL '.$label);
    }
    $dir=sys_get_temp_dir().'/ontrack-laravel-dnc-'.bin2hex(random_bytes(6));
    mkdir($dir,0700);
    $oldMap=getenv('ONTRACK_TENANT_ACCOUNT_BRIDGE_FILE');
    $oldDsn=getenv('ONTRACK_CONSOLE_DNC_DSN');
    try {
        check_dnc(VoiceDncBridge::normalize('+201001234567')==='201001234567','normalization');
        VoiceDncEntry::$blocked=[91=>['201001234567']];
        check_dnc(VoiceDncBridge::isSuppressed(91,'01001234567'),'local mirror');
        check_dnc(!VoiceDncBridge::isSuppressed(92,'01001234567'),'tenant isolation');
        $map=$dir.'/map.json';
        file_put_contents($map,json_encode(['account_to_tenant'=>['7'=>91,'8'=>92,'9'=>93]],JSON_THROW_ON_ERROR));
        chmod($map,0600);
        putenv('ONTRACK_TENANT_ACCOUNT_BRIDGE_FILE='.$map);
        $dbFile=$dir.'/console.sqlite';
        $db=new \PDO('sqlite:'.$dbFile,null,null,[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
        $db->exec('CREATE TABLE dnc_suppressions(account_id INTEGER,phone_key TEXT,source TEXT,created_at TEXT,PRIMARY KEY(account_id,phone_key))');
        $db->exec('CREATE TABLE contacts(account_id INTEGER,phone TEXT,dnc INTEGER)');
        $db->exec("INSERT INTO dnc_suppressions VALUES (8,'201001234567','contact_optout','now')");
        putenv('ONTRACK_CONSOLE_DNC_DSN=sqlite:'.$dbFile);
        check_dnc(VoiceDncBridge::isSuppressed(92,'+201001234567'),'Console primary dual read');
        check_dnc(!VoiceDncBridge::isSuppressed(93,'+201001234567'),'different tenant cannot read Console entry');
        $entry=new VoiceDncEntry();
        $entry->tenant_id=93;$entry->account_id=9;
        $entry->phone_normalized='201009876543';
        VoiceDncBridge::mirrorToConsole($entry);
        check_dnc((int)$db->query("SELECT COUNT(*) FROM dnc_suppressions WHERE account_id=9 AND phone_key='201009876543'")->fetchColumn()===1,'Laravel opt-out mirrors to Console');
        putenv('ONTRACK_CONSOLE_DNC_DSN=sqlite:'.$dir.'/missing.sqlite');
        check_dnc(!VoiceDncBridge::isSuppressed(93,'+201000000001'),'unavailable Console fallback');
        unset($db);
    } finally {
        if($oldMap===false)putenv('ONTRACK_TENANT_ACCOUNT_BRIDGE_FILE');
        else putenv('ONTRACK_TENANT_ACCOUNT_BRIDGE_FILE='.$oldMap);
        if($oldDsn===false)putenv('ONTRACK_CONSOLE_DNC_DSN');
        else putenv('ONTRACK_CONSOLE_DNC_DSN='.$oldDsn);
        foreach(glob($dir.'/*')?:[] as $file)@unlink($file);
        @rmdir($dir);
    }
    echo "LARAVEL_DNC_DUAL_READ_MIRROR_FALLBACK_PASS\n";
}
