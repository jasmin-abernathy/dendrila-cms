<?php
declare(strict_types=1);

namespace Dendrila\Cms\BackOffice;

/** Les différences entre clients sont de la configuration, pas des forks de l'interface. */
final readonly class Config
{
    public function __construct(
        public string $siteId,
        public string $siteName,
        public string $basePath = '/admin/',
        public string $assetsPath = '/backoffice-assets',
        public string $primaryColor = '#47755d',
        public string $iconUrl = '/favicon.ico',
        public ?string $androidDownloadUrl = null,
        public string $loginUrl = '/admin/login.php',
    ) {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/D',$siteId)
            || trim($siteName)==='' || mb_strlen($siteName)>190
            || preg_match('~^/[a-zA-Z0-9_/-]*/$~D', $basePath)!==1
            || $basePath==='//' || preg_match('~^/[a-zA-Z0-9_/-]+$~D',$assetsPath)!==1
            || preg_match('/^#[0-9A-Fa-f]{6}$/D',$primaryColor)!==1
            || !self::safeUrl($iconUrl) || !self::safeUrl($loginUrl)
            || ($androidDownloadUrl!==null && !self::safeUrl($androidDownloadUrl))) {
            throw new \InvalidArgumentException('Configuration du back-office invalide.');
        }
    }

    public function url(string $section = 'textes', array $params = []): string
    {
        return $this->basePath.'?'.http_build_query(['section'=>$section]+$params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function safeUrl(string $value): bool
    {
        if ($value==='' || str_contains($value,"\0") || str_starts_with($value,'//')) return false;
        if (str_starts_with($value,'/')) {
            return preg_match('~^/(?!/)[a-zA-Z0-9._/\~%-]+$~D', $value)===1
                && !str_contains($value,'..');
        }
        return filter_var($value,FILTER_VALIDATE_URL)!==false
            && parse_url($value,PHP_URL_SCHEME)==='https';
    }
}
