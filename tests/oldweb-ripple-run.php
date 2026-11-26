<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Front/OldwebRippleBackdrop.php';

use Dendrila\Cms\Front\OldwebRippleBackdrop;

function rippleCheck(bool $condition, string $description): void
{
    if (!$condition) {
        throw new RuntimeException($description);
    }
}

function rippleReject(array $settings, array $mediaUrls): void
{
    try {
        (new OldwebRippleBackdrop())->render($settings, $mediaUrls, 'fr');
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Unsafe Ripple configuration was accepted.');
}

$renderer = new OldwebRippleBackdrop();
$images = ['demo' => '/media/ripple-retro.svg'];
$result = $renderer->render(
    ['type' => 'ripple', 'media_id' => 'demo', 'speed' => 0.85, 'intensity' => 0.75],
    $images,
    'fr',
);
rippleCheck(str_contains($result['markup'], 'data-dendrila-ripple'), 'Missing Ripple mount element.');
rippleCheck(str_contains($result['markup'], 'src="/media/ripple-retro.svg"'), 'Poster does not use selected local media.');
rippleCheck(str_contains($result['markup'], 'data-speed="0.85"'), 'Bounded speed is not serialized.');
rippleCheck(str_contains($result['markup'], 'data-intensity="0.75"'), 'Bounded intensity is not serialized.');
rippleCheck(str_contains($result['tail'], 'Mettre le fond en pause'), 'Missing accessible pause control.');
rippleCheck(str_contains($result['tail'], '/assets/oldweb-ripple.js'), 'Trusted local script not referenced.');
rippleCheck(!str_contains($result['tail'], 'https://'), 'Third-party script unexpectedly injected.');
$english = $renderer->render(['type' => 'ripple', 'media_id' => 'demo'], $images, 'en');
rippleCheck(str_contains($english['tail'], 'Pause animated background'), 'English control missing.');

rippleReject(['type' => 'ripple', 'media_id' => 'missing'], $images);
rippleReject(['type' => 'video', 'media_id' => 'demo'], $images);
rippleReject(['type' => 'ripple', 'media_id' => 'demo', 'speed' => 99], $images);
rippleReject(['type' => 'ripple', 'media_id' => 'demo', 'intensity' => -1], $images);
rippleReject(['type' => 'ripple', 'media_id' => 'demo', 'intensity' => '0.5'], $images);
rippleReject(['type' => 'ripple', 'media_id' => 'demo', 'javascript' => 'alert(1)'], $images);
rippleReject(['type' => 'ripple', 'media_id' => 'demo'], ['demo' => 'https://external.example/demo.png']);
rippleReject(['type' => 'ripple', 'media_id' => 'demo'], ['demo' => '//external.example/demo.png']);
rippleReject(['type' => 'ripple', 'media_id' => 'demo'], ['demo' => '/media/../unsafe.png']);
rippleReject(['type' => 'ripple', 'media_id' => 'demo'], ['demo' => '/media/unsafe.png?x=1']);
rippleReject(['type' => 'ripple', 'media_id' => 'demo'], ['demo' => '/media/unsafe.png" onerror="alert(1)']);

$root = dirname(__DIR__);
$js = file_get_contents($root . '/public/assets/oldweb-ripple.js');
$css = file_get_contents($root . '/public/assets/oldweb-ripple.css');
rippleCheck(is_string($js) && str_contains($js, 'prefers-reduced-motion: reduce'), 'Reduced motion not handled by JS.');
rippleCheck(str_contains($js, 'webglcontextlost'), 'No context-loss fallback.');
rippleCheck(str_contains($js, 'visibilitychange'), 'Inactive tab is not handled.');
rippleCheck(str_contains($js, 'cancelAnimationFrame'), 'Render loop cannot be stopped.');
rippleCheck(is_string($css) && str_contains($css, ':focus-visible'), 'Pause control focus treatment missing.');
rippleCheck(str_contains($css, '@media (prefers-reduced-motion: reduce)'), 'Static CSS fallback missing.');
echo "Oldweb Ripple contract tests passed.\n";
