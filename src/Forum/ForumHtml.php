<?php
declare(strict_types=1);
namespace Dendrila\Cms\Forum;

final class ForumHtml
{
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /** @param list<array<string,mixed>> $topics */
    public static function topics(array $topics): string
    {
        $html = '<section class="dendrila-forum"><h2>Discussions</h2><ul>';
        foreach ($topics as $topic) {
            $html .= '<li><a href="/forum/sujet/' . ForumRules::id((string) $topic['topic_id']) . '">'
                . self::escape((string) $topic['title']) . '</a></li>';
        }
        return $html . '</ul></section>';
    }

    /** @param list<array<string,mixed>> $messages */
    public static function messages(array $messages): string
    {
        $html = '<section class="dendrila-forum-messages" aria-label="Messages du forum">';
        foreach ($messages as $message) {
            $html .= '<article><p>' . nl2br(self::escape((string) $message['body']), false) . '</p>';
            foreach ($message['photos'] ?? [] as $photo) {
                $html .= '<figure><img loading="lazy" decoding="async" src="/forum/photo/'
                    . ForumRules::id((string) $photo['photo_id']) . '" alt="'
                    . self::escape((string) $photo['alt_text']) . '"></figure>';
            }
            $html .= '</article>';
        }
        return $html . '</section>';
    }
}
