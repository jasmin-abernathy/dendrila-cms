<?php
declare(strict_types=1);

namespace Dendrila\Cms\Seo;

use Dendrila\Cms\Content\VersionConflict;
use PDO;

final readonly class PdoSeoRepository implements SeoRepository
{
    public function __construct(private PDO $pdo)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    public function find(string $siteId, string $contentId): ?array
    {
        return $this->read(
            'SELECT site_id, content_id, title, description, canonical_url, social_title, social_description,
                    social_image_url, indexable, version, created_at, updated_at
             FROM seo_metadata WHERE site_id = :site_id AND content_id = :content_id LIMIT 1',
            ['site_id' => $siteId, 'content_id' => $contentId],
        );
    }

    public function findForUpdate(string $siteId, string $contentId): ?array
    {
        return $this->read(
            'SELECT site_id, content_id, title, description, canonical_url, social_title, social_description,
                    social_image_url, indexable, version, created_at, updated_at
             FROM seo_metadata WHERE site_id = :site_id AND content_id = :content_id LIMIT 1 FOR UPDATE',
            ['site_id' => $siteId, 'content_id' => $contentId],
        );
    }

    public function insert(array $record): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO seo_metadata
             (site_id, content_id, title, description, canonical_url, social_title, social_description,
              social_image_url, indexable, version, created_at, updated_at)
             VALUES
             (:site_id, :content_id, :title, :description, :canonical_url, :social_title, :social_description,
              :social_image_url, :indexable, :version, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))'
        );
        $statement->execute($this->parameters($record));
    }

    public function save(array $record, int $expectedVersion): void
    {
        $parameters = $this->parameters($record);
        $parameters['expected_version'] = $expectedVersion;
        $statement = $this->pdo->prepare(
            'UPDATE seo_metadata SET
                title = :title, description = :description, canonical_url = :canonical_url,
                social_title = :social_title, social_description = :social_description,
                social_image_url = :social_image_url, indexable = :indexable, version = :version,
                updated_at = UTC_TIMESTAMP(6)
             WHERE site_id = :site_id AND content_id = :content_id AND version = :expected_version'
        );
        $statement->execute($parameters);
        if ($statement->rowCount() !== 1) {
            throw new VersionConflict('SEO metadata version is stale.');
        }
    }

    private function read(string $sql, array $parameters): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch();
        if ($row === false) return null;
        $row['version'] = (int) $row['version'];
        $row['indexable'] = (bool) $row['indexable'];
        return $row;
    }

    /** @param array<string,mixed> $record @return array<string,mixed> */
    private function parameters(array $record): array
    {
        return [
            'site_id' => $record['site_id'],
            'content_id' => $record['content_id'],
            'title' => $record['title'],
            'description' => $record['description'],
            'canonical_url' => $record['canonical_url'],
            'social_title' => $record['social_title'],
            'social_description' => $record['social_description'],
            'social_image_url' => $record['social_image_url'],
            'indexable' => $record['indexable'] ? 1 : 0,
            'version' => $record['version'],
        ];
    }
}
