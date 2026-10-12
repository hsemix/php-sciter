<?php

declare(strict_types=1);

namespace Sciter\Tests\Integration;

use Sciter\Window;
use Sciter\WindowOptions;

/**
 * Only covers behaviour that does not need a native window, so the
 * suite stays headless.
 */
final class WindowTest extends IntegrationTestCase
{
    public function testNewWindowIsNotCreatedYet(): void
    {
        $window = self::runtime()->createWindow(new WindowOptions());

        self::assertInstanceOf(Window::class, $window);
        self::assertSame(0, $window->state());
        self::assertFalse($window->isClosed());
        self::assertFalse($window->isClosing());
    }

    public function testOnCloseIsChainable(): void
    {
        $window = self::runtime()->createWindow();

        self::assertSame($window, $window->onClose(static function (): void {}));
    }

    public function testHideBeforeCreateDoesNotCreateTheWindow(): void
    {
        $window = self::runtime()->createWindow();

        $window->hide();

        self::assertSame(0, $window->state());
    }
}
