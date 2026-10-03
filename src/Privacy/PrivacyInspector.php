<?php
declare(strict_types=1);

namespace Dendrila\Cms\Privacy;

/**
 * CMS-native, source-agnostic detector derived from Dendrila Privacy's "observe first" principle.
 * Front adapters feed rendered HTML/configuration snapshots; this class never fetches remote URLs itself.
 */
final class PrivacyInspector
{
    /** @var array<string, array{label:string,kind:string,category:string,optional:bool,status:string,signatures:list<string>}> */
    private const SERVICES = [
        'google_analytics' => [
            'label' => 'Google Analytics', 'kind' => 'tracker', 'category' => 'statistics',
            'optional' => true, 'status' => 'detected',
            'signatures' => ['google-analytics.com', 'googletagmanager.com/gtag/js', 'gtag('],
        ],
        'google_tag_manager' => [
            'label' => 'Google Tag Manager', 'kind' => 'third_party_service', 'category' => 'unknown',
            'optional' => false, 'status' => 'needs_review',
            'signatures' => ['googletagmanager.com/gtm.js', 'gtm.start'],
        ],
        'meta_pixel' => [
            'label' => 'Meta Pixel', 'kind' => 'tracker', 'category' => 'marketing',
            'optional' => true, 'status' => 'detected',
            'signatures' => ['connect.facebook.net', 'fbq('],
        ],
        'microsoft_clarity' => [
            'label' => 'Microsoft Clarity', 'kind' => 'tracker', 'category' => 'statistics',
            'optional' => true, 'status' => 'detected',
            'signatures' => ['clarity.ms/tag/', 'clarity('],
        ],
        'hotjar' => [
            'label' => 'Hotjar', 'kind' => 'tracker', 'category' => 'statistics',
            'optional' => true, 'status' => 'detected',
            'signatures' => ['static.hotjar.com', 'hotjar.com/c/hotjar-', 'hj('],
        ],
        'matomo' => [
            'label' => 'Matomo', 'kind' => 'tracker', 'category' => 'statistics',
            'optional' => false, 'status' => 'needs_review',
            'signatures' => ['matomo.js', 'piwik.js', '_paq.push'],
        ],
        'youtube' => [
            'label' => 'YouTube', 'kind' => 'third_party_service', 'category' => 'external_media',
            'optional' => true, 'status' => 'detected',
            'signatures' => ['youtube.com/embed/', 'youtube-nocookie.com/embed/'],
        ],
        'vimeo' => [
            'label' => 'Vimeo', 'kind' => 'third_party_service', 'category' => 'external_media',
            'optional' => true, 'status' => 'detected',
            'signatures' => ['player.vimeo.com/video/'],
        ],
        'google_maps' => [
            'label' => 'Google Maps', 'kind' => 'third_party_service', 'category' => 'external_media',
            'optional' => true, 'status' => 'detected',
            'signatures' => ['google.com/maps/embed', 'maps.googleapis.com'],
        ],
        'recaptcha' => [
            'label' => 'Google reCAPTCHA', 'kind' => 'third_party_service', 'category' => 'functional',
            'optional' => false, 'status' => 'needs_review',
            'signatures' => ['google.com/recaptcha/', 'gstatic.com/recaptcha/'],
        ],
    ];

    /**
     * @param list<array{id:string,url?:string,content:string}> $documents
     * @return list<array<string, mixed>>
     */
    public function inspect(array $documents): array
    {
        if (count($documents) > 1000) {
            throw new \InvalidArgumentException('A privacy inspection is limited to 1000 documents.');
        }

        $evidence = [];
        foreach ($documents as $document) {
            if (!is_array($document) || !isset($document['id'], $document['content'])
                || !is_string($document['id']) || !is_string($document['content'])
                || trim($document['id']) === '' || strlen($document['id']) > 190
                || strlen($document['content']) > 2_000_000) {
                throw new \InvalidArgumentException('Invalid privacy inspection document.');
            }
            $haystack = strtolower($document['content']);
            foreach (self::SERVICES as $serviceId => $service) {
                $matches = [];
                foreach ($service['signatures'] as $signature) {
                    if (str_contains($haystack, strtolower($signature))) {
                        $matches[] = $signature;
                    }
                }
                if ($matches !== []) {
                    $evidence[$serviceId][] = [
                        'document_id' => $document['id'],
                        'url' => isset($document['url']) && is_string($document['url']) ? $document['url'] : null,
                        'signatures' => array_values(array_unique($matches)),
                    ];
                }
            }
        }

        $findings = [];
        foreach ($evidence as $serviceId => $matches) {
            $service = self::SERVICES[$serviceId];
            $findings[] = [
                'kind' => $service['kind'],
                'service_id' => $serviceId,
                'label' => $service['label'],
                'category' => $service['category'],
                'status' => $service['status'],
                'optional_tracking' => $service['optional'],
                'evidence' => $matches,
            ];
        }
        return $findings;
    }
}
