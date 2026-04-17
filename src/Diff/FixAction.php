<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Diff;

enum FixAction
{
    case Apply;
    case Skip;
    case ApplyAll;
    case Quit;
}
