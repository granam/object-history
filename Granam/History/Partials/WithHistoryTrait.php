<?php
declare(strict_types=1);

namespace Granam\History\Partials;

use Granam\Tools\ValueDescriber;

trait WithHistoryTrait
{
    private $history = [];

    /**
     * Gives array of modifications and result values, from very first to current value.
     * Order of historical values is from oldest as first to newest as last.
     * Warning: history is NOT persisted.
     *
     * @return array
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    /**
     * @param mixed $result
     */
    protected function noticeHistoryChangeFromOutside($result): void
    {
        $changingCall = $this->findChangingCall(false /* only methods from outside */); // find a last call outside of this class (that causing current change)
        $this->history[] = [
            'changedBy' => [
                'name' => $this->formatToSentence($changingCall['function']),
                'with' => $this->extractArgumentsDescription($changingCall['args']),
            ],
            'result' => $result,
        ];
    }

    /**
     * @param bool $fromInside
     * @return array
     */
    private function findChangingCall(bool $fromInside): array
    {
        /** @var array $call */
        foreach (\debug_backtrace() as $call) {
            if (($fromInside
                    && (!\array_key_exists('function', $call)
                        || !\in_array($call['function'], [__FUNCTION__, 'noticeHistoryChangeFromInside'], true)
                    )
                )
                || ((!\array_key_exists('object', $call) || $call['object'] !== $this)
                    && (!\array_key_exists('class', $call) || !\is_a($this, $call['class']))
                )
            ) {
                return $call;
            }
        }

        // @codeCoverageIgnoreStart
        return ['function' => '', 'args' => []];
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param string $string
     * @return string
     */
    private function formatToSentence(string $string): string
    {
        \preg_match_all('~[[:upper:]]?[[:lower:]]*~', $string, $matches);
        $captures = \array_filter($matches[0], function ($capture) {
            return $capture !== '';
        });

        return \implode(' ', \array_map('lcfirst', $captures));
    }

    /**
     * @param array $arguments
     * @return string
     */
    private function extractArgumentsDescription(array $arguments): string
    {
        $descriptions = [];
        foreach ($arguments as $argument) {
            $descriptions[] = ValueDescriber::describe($argument);
        }

        return \implode(',', $descriptions);
    }

    /**
     * @param mixed $result
     */
    protected function noticeHistoryChangeFromInside($result): void
    {
        $changingCall = $this->findChangingCall(true /* from inside / by this class */);
        $this->history[] = [
            'changedBy' => [
                'name' => $this->formatToSentence($changingCall['function']),
                'with' => $this->extractArgumentsDescription($changingCall['args']),
            ],
            'result' => $result,
        ];
    }

    /**
     * @param WithHistory $somethingWithHistory
     */
    protected function adoptHistory(WithHistory $somethingWithHistory): void
    {
        /** @var WithHistoryTrait $somethingWithHistory */
        // previous history FIRST, current after
        $this->history = \array_merge($somethingWithHistory->getHistory(), $this->getHistory());
    }
}