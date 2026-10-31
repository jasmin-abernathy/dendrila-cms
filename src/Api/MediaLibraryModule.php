<?php
declare(strict_types=1);
namespace Dendrila\Cms\Api;
final class MediaLibraryModule
{
    public static function definition(): array
    {
        $fields = [
            ['id' => 'title', 'type' => 'text', 'label' => 'Titre', 'max_length' => 255],
            ['id' => 'alt', 'type' => 'multiline', 'label' => 'Description alternative proposée', 'max_length' => 2000,
                'hint' => 'Chaque utilisation peut préciser sa propre description.'],
            ['id' => 'caption', 'type' => 'multiline', 'label' => 'Légende', 'max_length' => 2000],
            ['id' => 'credit', 'type' => 'text', 'label' => 'Crédit ou source', 'max_length' => 2000],
        ];
        return ['id' => 'media_library', 'kind' => 'media_library', 'title' => 'Médiathèque',
            'subtitle' => 'Images du site. Les aperçus peuvent être partagés par leur lien.',
            'writable' => true, 'fields' => $fields,
            'options' => ['allow_rotate' => true, 'allow_delete' => true],
            'media' => ['upload_enabled' => true, 'max_bytes' => 10000000,
                'accepted_mime_types' => \Dendrila\Cms\Media\ImageStorage::MIMES, 'fields' => $fields]];
    }
}
