<?php
declare(strict_types=1);
namespace Dendrila\Cms\Auth;

/** RFC 6238, HMAC-SHA1, 30-second step, six digits. */
final class Totp
{
    public static function secret(): string
    {
        $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $result='';
        foreach(str_split(random_bytes(32)) as $byte) $result.=$alphabet[ord($byte)&31];
        return $result;
    }
    public static function code(string $secret, int $step, int $digits=6): string
    {
        if(!preg_match('/^[A-Z2-7]{16,128}$/D',$secret) || $step<0 || !in_array($digits,[6,8],true)) throw new \InvalidArgumentException('Invalid TOTP input.');
        $bits=''; foreach(str_split($secret) as $c) $bits.=str_pad(decbin(strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567',$c)),5,'0',STR_PAD_LEFT);
        $key=''; for($i=0;$i+8<=strlen($bits);$i+=8) $key.=chr(bindec(substr($bits,$i,8)));
        $hash=hash_hmac('sha1',pack('N2',intdiv($step,4294967296),$step%4294967296),$key,true);
        $offset=ord($hash[19])&15;
        $number=unpack('N',substr($hash,$offset,4))[1]&0x7fffffff;
        return str_pad((string)($number%(10**$digits)),$digits,'0',STR_PAD_LEFT);
    }
    public static function verify(string $secret,string $code,int $lastStep=-1,?int $time=null): ?int
    {
        if(!preg_match('/^\d{6}$/D',$code)) return null;
        $step=intdiv($time??time(),30);
        foreach([$step,$step-1,$step+1] as $candidate) {
            if($candidate>$lastStep && hash_equals(self::code($secret,$candidate),$code)) return $candidate;
        }
        return null;
    }
}
