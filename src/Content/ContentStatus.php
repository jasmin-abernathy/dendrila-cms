<?php
declare(strict_types=1);

namespace Dendrila\Cms\Content;

enum ContentStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';
}
