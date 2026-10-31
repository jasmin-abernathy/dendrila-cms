<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Dendrila\Cms\Forum\ForumHtml;
use Dendrila\Cms\Forum\ForumRules;

function forumAssert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}
forumAssert(ForumRules::title(' Album photo ') === 'Album photo', 'Title trim.');
forumAssert(ForumRules::message("Bonjour\nPhotographie") === "Bonjour\nPhotographie", 'Multiline body.');
forumAssert(ForumRules::alt('Pont de Metz') === 'Pont de Metz', 'Accessible alt.');
foreach ([static fn () => ForumRules::title('X'), static fn () => ForumRules::message(''),
    static fn () => ForumRules::alt(' '), static fn () => ForumRules::id('../secret'),
    static fn () => ForumRules::site('..')] as $invalid) {
    try { $invalid(); throw new RuntimeException('Invalid input accepted.'); }
    catch (InvalidArgumentException) { /* validation passed */ }
}
$topics = ForumHtml::topics([['topic_id' => str_repeat('a', 32),
    'title' => '<script>alert(1)</script>']]);
forumAssert(!str_contains($topics, '<script>') && str_contains($topics, '&lt;script&gt;'),
    'Escapes topic labels.');
$messages = ForumHtml::messages([['body' => '<img src=x onerror=alert(1)>',
    'photos' => [['photo_id' => str_repeat('b', 32), 'alt_text' => '" onload="alert(1)']]]]);
forumAssert(!str_contains($messages, '<img src=x') &&
    str_contains($messages, 'alt="&quot; onload=&quot;alert(1)"'),
    'Escapes body and image alt attribute.');
echo "Forum validation and HTML safety tests passed.\n";
