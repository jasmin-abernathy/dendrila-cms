<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use LePotager\Cms\Api\PdoSiteReadModel;
use LePotager\Cms\Content\ContentTypeDefinition;

function cabinetKitCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$kit = json_decode(file_get_contents($root . '/kits/cabinet-medical/kit.json'), true, 512, JSON_THROW_ON_ERROR);

foreach (['consultation_service', 'practitioner_qualification'] as $typeId) {
    cabinetKitCheck(in_array($typeId, $kit['content_types'] ?? [], true), "The kit does not declare {$typeId}.");
    $definition = json_decode(file_get_contents(
        $root . "/kits/cabinet-medical/content-types/{$typeId}.json"
    ), true, 512, JSON_THROW_ON_ERROR);
    cabinetKitCheck((new ContentTypeDefinition($definition))->id() === $typeId,
        "The {$typeId} content type is invalid.");
}

$modules = $kit['api_modules'] ?? null;
cabinetKitCheck(is_array($modules) && array_is_list($modules), 'The kit API modules must be a list.');
$safe = PdoSiteReadModel::safeModules($modules);
cabinetKitCheck(array_column($safe, 'id') === ['services', 'qualifications'],
    'The practitioner app requires services and qualifications modules in this order.');
foreach ($safe as $module) {
    cabinetKitCheck($module['kind'] === 'gallery' && ($module['writable'] ?? false) === true,
        "Module {$module['id']} must be a writable gallery.");
    cabinetKitCheck(($module['options']['allow_create'] ?? false) === true,
        "Module {$module['id']} must allow item creation.");
}

echo "Cabinet medical kit contract tests passed.\n";
