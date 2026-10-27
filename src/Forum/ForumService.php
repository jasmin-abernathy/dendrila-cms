<?php
declare(strict_types=1);

namespace Dendrila\Cms\Forum;

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use PDO;

/**
 * Forum multi-sites. Les comptes publics sont fournis par l'adaptateur d'identité
 * du site ; ce service ne fait jamais confiance à un acteur fourni par le navigateur.
 */
final readonly class ForumService
{
    public function __construct(
        private PDO $pdo,
        private AuthorizationService $authorization,
        private ForumPhotoStorage $photos,
    ) {
    }

    /** @return array{topic_id:string,message_id:string,status:string} */
    public function openTopic(ActorContext $actor, string $title, string $body): array
    {
        $this->authorization->require($actor, 'forum.post');
        $title = ForumRules::title($title);
        $body = ForumRules::message($body);
        $this->rateLimit($actor, 'forum_topics', 3, 15);
        $topicId = bin2hex(random_bytes(16));
        $messageId = bin2hex(random_bytes(16));
        $this->pdo->beginTransaction();
        try {
            $q = $this->pdo->prepare("INSERT INTO forum_topics
                (site_id,topic_id,author_id,title,status,locked,created_at,updated_at)
                VALUES (:site,:id,:author,:title,'pending',0,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
            $q->execute(['site' => $actor->siteId, 'id' => $topicId,
                'author' => $actor->actorId, 'title' => $title]);
            $this->insertMessage($actor, $messageId, $topicId, $body, true);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
        return ['topic_id' => $topicId, 'message_id' => $messageId, 'status' => 'pending'];
    }

    /** @return array{message_id:string,status:string} */
    public function reply(ActorContext $actor, string $topicId, string $body): array
    {
        $this->authorization->require($actor, 'forum.post');
        $topicId = ForumRules::id($topicId);
        $body = ForumRules::message($body);
        $this->rateLimit($actor, 'forum_messages', 8, 5);
        $id = bin2hex(random_bytes(16));
        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare(
                'SELECT status,locked FROM forum_topics WHERE site_id=:site AND topic_id=:topic FOR UPDATE');
            $query->execute(['site' => $actor->siteId, 'topic' => $topicId]);
            $topic = $query->fetch(PDO::FETCH_ASSOC);
            if (!is_array($topic) || $topic['status'] !== 'published' || (int) $topic['locked'] === 1) {
                throw new \DomainException('Ce sujet n’accepte pas de réponses.');
            }
            $this->insertMessage($actor, $id, $topicId, $body, false);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
        return ['message_id' => $id, 'status' => 'pending'];
    }

    /** @return array{photo_id:string,width:int,height:int,byte_size:int} */
    public function attachPhoto(ActorContext $actor, string $messageId, string $bytes, string $alt): array
    {
        $this->authorization->require($actor, 'forum.post');
        $messageId = ForumRules::id($messageId);
        $alt = ForumRules::alt($alt);
        $id = bin2hex(random_bytes(16));
        $stored = false;
        $this->pdo->beginTransaction();
        try {
            $q = $this->pdo->prepare(
                'SELECT author_id,status FROM forum_messages
                 WHERE site_id=:site AND message_id=:id FOR UPDATE');
            $q->execute(['site' => $actor->siteId, 'id' => $messageId]);
            $message = $q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($message) || $message['author_id'] !== $actor->actorId
                || $message['status'] !== 'pending') {
                throw new \DomainException('Photos autorisées uniquement sur vos messages en attente.');
            }
            $q = $this->pdo->prepare(
                'SELECT COUNT(*) FROM forum_photos WHERE site_id=:site AND message_id=:id');
            $q->execute(['site' => $actor->siteId, 'id' => $messageId]);
            if ((int) $q->fetchColumn() >= ForumRules::MAX_PHOTOS_PER_MESSAGE) {
                throw new \DomainException('Quatre photos maximum par message.');
            }
            $meta = $this->photos->save($id, $bytes);
            $stored = true;
            $q = $this->pdo->prepare('INSERT INTO forum_photos
                (site_id,photo_id,message_id,alt_text,width,height,byte_size,created_at)
                VALUES (:site,:photo,:message,:alt,:width,:height,:size,UTC_TIMESTAMP(6))');
            $q->execute(['site' => $actor->siteId, 'photo' => $id, 'message' => $messageId,
                'alt' => $alt, 'width' => $meta['width'], 'height' => $meta['height'],
                'size' => $meta['byte_size']]);
            $this->pdo->commit();
            return ['photo_id' => $id, ...$meta];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            if ($stored) { $this->photos->delete($id); }
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    public function visibleTopics(string $siteId, int $limit = 40): array
    {
        $siteId = ForumRules::site($siteId);
        $q = $this->pdo->prepare(
            "SELECT topic_id,title,author_id,locked,created_at,updated_at
             FROM forum_topics WHERE site_id=:site AND status='published'
             ORDER BY updated_at DESC LIMIT :limit");
        $q->bindValue(':site', $siteId);
        $q->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $q->execute();
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function visibleTopic(string $siteId, string $topicId): ?array
    {
        $q = $this->pdo->prepare(
            "SELECT topic_id,title,author_id,locked,created_at FROM forum_topics
             WHERE site_id=:site AND topic_id=:topic AND status='published'");
        $q->execute(['site' => ForumRules::site($siteId), 'topic' => ForumRules::id($topicId)]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function visibleMessages(string $siteId, string $topicId): array
    {
        $siteId = ForumRules::site($siteId);
        $topicId = ForumRules::id($topicId);
        if ($this->visibleTopic($siteId, $topicId) === null) {
            return []; // Ne révèle ni discussion modérée ni contenu privé.
        }
        $q = $this->pdo->prepare(
            "SELECT message_id,author_id,body,created_at,is_opener
             FROM forum_messages WHERE site_id=:site AND topic_id=:topic AND status='published'
             ORDER BY created_at ASC LIMIT 200");
        $q->execute(['site' => $siteId, 'topic' => $topicId]);
        $messages = $q->fetchAll(PDO::FETCH_ASSOC);
        $photos = $this->pdo->prepare(
            'SELECT photo_id,alt_text,width,height FROM forum_photos
             WHERE site_id=:site AND message_id=:message ORDER BY created_at,photo_id');
        foreach ($messages as &$message) {
            $photos->execute(['site' => $siteId, 'message' => $message['message_id']]);
            $message['photos'] = $photos->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($message);
        return $messages;
    }

    /** Return image bytes only for a published message within a published topic. */
    public function publicPhoto(string $siteId, string $photoId): ?string
    {
        $q = $this->pdo->prepare(
            "SELECT p.photo_id FROM forum_photos p
             INNER JOIN forum_messages m ON m.site_id=p.site_id AND m.message_id=p.message_id
             INNER JOIN forum_topics t ON t.site_id=m.site_id AND t.topic_id=m.topic_id
             WHERE p.site_id=:site AND p.photo_id=:photo
                AND m.status='published' AND t.status='published' LIMIT 1");
        $q->execute(['site' => ForumRules::site($siteId), 'photo' => ForumRules::id($photoId)]);
        if (!$q->fetchColumn()) { return null; }
        return $this->photos->read($photoId);
    }

    /** @return array{topics:list<array<string,mixed>>,messages:list<array<string,mixed>>} */
    public function moderationQueue(ActorContext $moderator): array
    {
        $this->authorization->require($moderator, 'forum.moderate');
        $topics = $this->pdo->prepare(
            "SELECT topic_id,title,author_id,created_at FROM forum_topics
             WHERE site_id=:site AND status='pending' ORDER BY created_at LIMIT 100");
        $topics->execute(['site' => $moderator->siteId]);
        $messages = $this->pdo->prepare(
            "SELECT m.message_id,m.topic_id,m.author_id,m.body,m.created_at
             FROM forum_messages m INNER JOIN forum_topics t
               ON t.site_id=m.site_id AND t.topic_id=m.topic_id
             WHERE m.site_id=:site AND m.status='pending' AND m.is_opener=0
               AND t.status='published' ORDER BY m.created_at LIMIT 100");
        $messages->execute(['site' => $moderator->siteId]);
        return ['topics' => $topics->fetchAll(PDO::FETCH_ASSOC),
            'messages' => $messages->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function moderateTopic(ActorContext $moderator, string $topicId, bool $publish): void
    {
        $this->authorization->require($moderator, 'forum.moderate');
        $topicId = ForumRules::id($topicId);
        $status = $publish ? 'published' : 'hidden';
        $this->pdo->beginTransaction();
        try {
            $q = $this->pdo->prepare(
                'UPDATE forum_topics SET status=:status,updated_at=UTC_TIMESTAMP(6)
                 WHERE site_id=:site AND topic_id=:topic');
            $q->execute(['status' => $status, 'site' => $moderator->siteId, 'topic' => $topicId]);
            if ($q->rowCount() === 0) {
                throw new \DomainException('Sujet introuvable.');
            }
            $q = $this->pdo->prepare(
                'UPDATE forum_messages SET status=:status,updated_at=UTC_TIMESTAMP(6)
                 WHERE site_id=:site AND topic_id=:topic AND is_opener=1');
            $q->execute(['status' => $status, 'site' => $moderator->siteId, 'topic' => $topicId]);
            $this->audit($moderator, 'forum.moderate_topic', $topicId, ['status' => $status]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
    }

    public function moderateMessage(ActorContext $moderator, string $messageId, bool $publish): void
    {
        $this->authorization->require($moderator, 'forum.moderate');
        $status = $publish ? 'published' : 'hidden';
        $q = $this->pdo->prepare(
            "UPDATE forum_messages m INNER JOIN forum_topics t
                ON t.site_id=m.site_id AND t.topic_id=m.topic_id
             SET m.status=:status,m.updated_at=UTC_TIMESTAMP(6)
             WHERE m.site_id=:site AND m.message_id=:id
                AND m.is_opener=0 AND t.status='published'");
        $q->execute(['status' => $status, 'site' => $moderator->siteId,
            'id' => ForumRules::id($messageId)]);
        if ($q->rowCount() === 0) { throw new \DomainException('Réponse non modifiable.'); }
        $this->audit($moderator, 'forum.moderate_message', $messageId, ['status' => $status]);
    }

    public function lockTopic(ActorContext $moderator, string $topicId, bool $locked): void
    {
        $this->authorization->require($moderator, 'forum.moderate');
        $q = $this->pdo->prepare(
            'UPDATE forum_topics SET locked=:locked,updated_at=UTC_TIMESTAMP(6)
             WHERE site_id=:site AND topic_id=:id');
        $q->execute(['locked' => (int) $locked, 'site' => $moderator->siteId,
            'id' => ForumRules::id($topicId)]);
        if ($q->rowCount() === 0) { throw new \DomainException('Sujet introuvable ou déjà dans cet état.'); }
        $this->audit($moderator, 'forum.lock_topic', $topicId, ['locked' => $locked]);
    }

    private function insertMessage(
        ActorContext $actor, string $id, string $topicId, string $body, bool $opener,
    ): void {
        $q = $this->pdo->prepare(
            "INSERT INTO forum_messages
             (site_id,message_id,topic_id,author_id,body,is_opener,status,created_at,updated_at)
             VALUES (:site,:id,:topic,:author,:body,:opener,'pending',UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $q->execute(['site' => $actor->siteId, 'id' => $id, 'topic' => $topicId,
            'author' => $actor->actorId, 'body' => $body, 'opener' => (int) $opener]);
    }

    private function rateLimit(ActorContext $actor, string $table, int $max, int $minutes): void
    {
        // Table et durée proviennent exclusivement du code, jamais d'un paramètre HTTP.
        $q = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE site_id=:site
             AND author_id=:author AND created_at > UTC_TIMESTAMP(6) - INTERVAL {$minutes} MINUTE");
        $q->execute(['site' => $actor->siteId, 'author' => $actor->actorId]);
        if ((int) $q->fetchColumn() >= $max) {
            throw new \DomainException('Trop de publications récentes ; réessayez plus tard.');
        }
    }

    /** @param array<string,mixed> $details */
    private function audit(ActorContext $actor, string $action, string $id, array $details): void
    {
        $q = $this->pdo->prepare(
            "INSERT INTO audit_events
             (site_id,actor_id,device_id,action,entity_type,entity_id,outcome,details_json,created_at)
             VALUES (:site,:actor,:device,:action,'forum',:id,'allowed',:details,UTC_TIMESTAMP(6))");
        $q->execute(['site' => $actor->siteId, 'actor' => $actor->actorId,
            'device' => $actor->deviceId, 'action' => $action, 'id' => $id,
            'details' => json_encode($details, JSON_THROW_ON_ERROR)]);
    }
}
