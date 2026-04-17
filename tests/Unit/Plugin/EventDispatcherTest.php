<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Plugin\Event;
use Ylab\PhpMigrater\Plugin\EventDispatcher;
use Ylab\PhpMigrater\Plugin\EventType;

final class EventDispatcherTest extends TestCase
{
    public function testDispatchCallsListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $called = false;

        $dispatcher->listen(EventType::BeforeAnalyze, function (Event $event) use (&$called) {
            $called = true;
        });

        $dispatcher->dispatch(new Event(EventType::BeforeAnalyze));

        $this->assertTrue($called);
    }

    public function testDispatchPassesPayload(): void
    {
        $dispatcher = new EventDispatcher();
        $received = null;

        $dispatcher->listen(EventType::FileProcessed, function (Event $event) use (&$received) {
            $received = $event->payload;
        });

        $dispatcher->dispatch(new Event(EventType::FileProcessed, ['file' => 'test.php']));

        $this->assertSame(['file' => 'test.php'], $received);
    }

    public function testNonMatchingEventNotCalled(): void
    {
        $dispatcher = new EventDispatcher();
        $called = false;

        $dispatcher->listen(EventType::BeforeAnalyze, function () use (&$called) {
            $called = true;
        });

        $dispatcher->dispatch(new Event(EventType::AfterAnalyze));

        $this->assertFalse($called);
    }
}
