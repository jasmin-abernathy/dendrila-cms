<?php
declare(strict_types=1);
namespace Dendrila\Cms\Api;

/** Public MMW discovery that never exposes secrets, server paths or role mappings. */
final class MmwDiscoveryManifest
{
    /** @param callable(string):(?string) $env */
    public static function fromEnvironment(callable $env): array
    {
        $siteId=trim((string)($env('DENDRILA_SITE_ID')??''));
        $name=trim((string)($env('DENDRILA_SITE_NAME')??''));
        $origin=trim((string)($env('APP_BASE_URL')??''));
        $provider=trim((string)($env('DENDRILA_IDENTITY_PROVIDER')??''));
        if($provider!=='native' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/D',$siteId)
            || $name==='' || !preg_match('/^[a-f0-9]{64}$/D',(string)($env('DENDRILA_AUTH_KEY')??''))) {
            throw new \RuntimeException('Native MMW discovery not configured.');
        }
        $parsed=parse_url($origin);
        if(!is_array($parsed)||($parsed['scheme']??null)!=='https'||empty($parsed['host'])
            || isset($parsed['user'])||isset($parsed['pass'])||isset($parsed['query'])||isset($parsed['fragment'])
            || !in_array($parsed['path']??'',['','/'],true) || !filter_var($origin,FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('An HTTPS site origin is required.');
        }
        return [
            'schema_version'=>1,
            'site_id'=>$siteId,
            'display_name'=>$name,
            'api_base_url'=>rtrim($origin,'/'),
            'auth_methods'=>['pairing_code'],
            'branding_preview'=>['primary'=>'#47755d'],
            'protocol_min'=>1,'protocol_max'=>1,
        ];
    }
}
