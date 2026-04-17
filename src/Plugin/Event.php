<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Plugin;

enum EventType: string
{
    case BeforeAnalyze = 'before_analyze';
    case AfterAnalyze = 'after_analyze';
    case BeforeFix = 'before_fix';
    case AfterFix = 'after_fix';
    case FileProcessed = 'file_processed';
    case BeforeTestGenerate = 'before_test_generate';
    case AfterTestGenerate = 'after_test_generate';
    case BeforeReport = 'before_report';
    case AfterReport = 'after_report';
    case FixApproved = 'fix_approved';
    case FixRejected = 'fix_rejected';
    case FixRolledBack = 'fix_rolled_back';
    case BeforeFileFix = 'before_file_fix';
    case AfterFileFix = 'after_file_fix';
}

final class Event
{
    public function __construct(
        public readonly EventType $type,
        public readonly array $payload = [],
    ) {}
}

final class EventDispatcher
{
    /** @var array<string, list<callable(Event): void>> */
    private array $listeners = [];

    public function listen(EventType $type, callable $listener): void
    {
        $this->listeners[$type->value][] = $listener;
    }

    public function dispatch(Event $event): void
    {
        $listeners = $this->listeners[$event->type->value] ?? [];
        foreach ($listeners as $listener) {
            $listener($event);
        }
    }
}
