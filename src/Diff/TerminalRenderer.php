<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Diff;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Renders colored unified diffs in the terminal.
 * In interactive mode, prompts the user to apply/skip each change.
 */
final class TerminalRenderer
{
    public function render(DiffResult $diff, OutputInterface $output): void
    {
        if (!$diff->hasChanges) {
            $output->writeln("<info>No changes for {$diff->fileName}</info>");
            return;
        }

        $output->writeln('');
        $output->writeln("<comment>--- {$diff->fileName}</comment>");
        $output->writeln("<comment>+++ {$diff->fileName} (modified)</comment>");
        $output->writeln('');

        foreach (explode("\n", $diff->unifiedDiff) as $line) {
            if (str_starts_with($line, '+') && !str_starts_with($line, '+++')) {
                $output->writeln("<fg=green>{$line}</>");
            } elseif (str_starts_with($line, '-') && !str_starts_with($line, '---')) {
                $output->writeln("<fg=red>{$line}</>");
            } elseif (str_starts_with($line, '@@')) {
                $output->writeln("<fg=cyan>{$line}</>");
            } else {
                $output->writeln($line);
            }
        }

        $output->writeln('');
    }

    /**
     * Render diff and prompt user for action.
     *
     * @return FixAction The user's chosen action
     */
    public function renderInteractive(
        DiffResult $diff,
        InputInterface $input,
        OutputInterface $output,
        QuestionHelper $questionHelper,
    ): FixAction {
        $this->render($diff, $output);

        $output->writeln(sprintf(
            '<info>%d line(s) added, %d line(s) removed</info>',
            $diff->getAddedLineCount(),
            $diff->getRemovedLineCount(),
        ));

        $question = new ChoiceQuestion(
            'What would you like to do?',
            [
                'a' => 'Apply this fix',
                's' => 'Skip this fix',
                'A' => 'Apply all remaining fixes',
                'q' => 'Quit (skip all remaining)',
            ],
            'a',
        );

        $answer = $questionHelper->ask($input, $output, $question);

        return match ($answer) {
            'a', 'Apply this fix' => FixAction::Apply,
            's', 'Skip this fix' => FixAction::Skip,
            'A', 'Apply all remaining fixes' => FixAction::ApplyAll,
            'q', 'Quit (skip all remaining)' => FixAction::Quit,
            default => FixAction::Skip,
        };
    }
}
