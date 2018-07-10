<?php
declare(strict_types=1);

namespace Granam\Tests\Properties\Partials;

use Granam\History\Partials\WithHistory;
use Granam\History\Partials\WithHistoryTrait;
use Granam\Tools\ValueDescriber;
use PHPUnit\Framework\TestCase;

class WithHistoryTraitTest extends TestCase
{
    /**
     * @test
     * @dataProvider provideSomeRandomData
     * @param $randomData
     */
    public function I_can_get_history_of_an_object($randomData): void
    {
        $somethingHistorical = new SomethingHistorical('foo', 123, 456.789);
        $changes = [];
        $changes[] = [
            'changedBy' => [
                'name' => 'i can get history of an object',
                'with' => ValueDescriber::describe($randomData),
            ],
            'result' => $somethingHistorical,
        ];
        self::assertEquals($changes, $somethingHistorical->getHistory());

        $somethingHistorical->setBar(999);
        $changes[] = [
            'changedBy' => [
                'name' => 'i can get history of an object', // external method expected
                'with' => ValueDescriber::describe($randomData),
            ],
            'result' => 999,
        ];
        self::assertEquals($changes, $somethingHistorical->getHistory());

        $somethingHistorical->setBaz(159);
        $changes[] = [
            'changedBy' => [
                'name' => 'set baz', // lastly called internal / changed object method expected
                'with' => ValueDescriber::describe(159.0),
            ],
            'result' => 159.0,
        ];
        self::assertEquals($changes, $somethingHistorical->getHistory());

        $somethingHistorical->merge($somethingHistorical);
        $adopted = array_merge($changes, $changes);
        $adopted[] = [
            'changedBy' => [
                'name' => 'merge', // lastly called internal / changed object method expected
                'with' => ValueDescriber::describe($somethingHistorical),
            ],
            'result' => 'merged',
        ];
        self::assertEquals($adopted, $somethingHistorical->getHistory());

        $somethingLessHistorical = new SomethingLessHistorical([$minorChange = ['medium' => 'vinyl']]);
        $somethingHistorical->merge($somethingLessHistorical);
        array_unshift($adopted, $minorChange); // adopted history goes first
        $adopted[] = [
            'changedBy' => [
                'name' => 'merge', // lastly called internal / changed object method expected
                'with' => ValueDescriber::describe($somethingLessHistorical),
            ],
            'result' => 'merged',
        ];
        self::assertEquals($adopted, $somethingHistorical->getHistory());
    }

    public function provideSomeRandomData(): array
    {
        return [
            [$this->getRandomData(random_int(0, 4))],
            [$this->getRandomData(random_int(0, 4))],
            [$this->getRandomData(random_int(0, 4))],
            [$this->getRandomData(random_int(0, 4))],
        ];
    }

    private function getRandomData(int $index)
    {
        switch ($index) {
            case 0:
                return [random_int(1, 10), random_bytes(50)];
            case 1:
                return random_int(1, 10);
            case 2:
                return new \stdClass();
            case 3:
                return new \DateTime();
            case 4:
                return random_bytes(20);
            default:
                throw new \LogicException();
        }
    }

    /**
     * @test
     */
    public function History_made_by_same_class_is_ignored_for_outside_changes(): void
    {
        $somethingWithHistoricalRoots = new SomethingWithHistoricalRoots('ink', 234, 456.567);
        $replaced = $somethingWithHistoricalRoots->replaceFoo('paper');
        self::assertEquals(
            [
                [
                    'changedBy' => [
                        'name' => 'history made by same class is ignored for outside changes',
                        'with' => '',
                    ],
                    'result' => $somethingWithHistoricalRoots,
                ],
                [
                    'changedBy' => [
                        'name' => 'history made by same class is ignored for outside changes',
                        'with' => '',
                    ],
                    'result' => $replaced,
                ],
            ],
            $replaced->getHistory()
        );
    }
}

/** inner */
class SomethingHistorical implements WithHistory
{
    use WithHistoryTrait;

    /**
     * @var string
     */
    private $foo;
    /**
     * @var int
     */
    private $bar;
    /**
     * @var float
     */
    private $baz;

    public function __construct(string $foo, int $bar, float $baz)
    {
        $this->foo = $foo;
        $this->bar = $bar;
        $this->baz = $baz;
        $this->noticeHistoryChangeFromOutside($this);
    }

    /**
     * @return string
     */
    public function getFoo(): string
    {
        return $this->foo;
    }

    /**
     * @return int
     */
    public function getBar(): int
    {
        return $this->bar;
    }

    /**
     * @return float
     */
    public function getBaz(): float
    {
        return $this->baz;
    }

    /**
     * @param string $foo
     */
    public function setFoo(string $foo): void
    {
        $this->foo = $foo;
        $this->noticeHistoryChangeFromOutside($foo);
    }

    /**
     * @param int $bar
     */
    public function setBar(int $bar): void
    {
        $this->bar = $bar;
        $this->noticeHistoryChangeFromOutside($bar);
    }

    /**
     * @param float $baz
     */
    public function setBaz(float $baz): void
    {
        $this->baz = $baz;
        $this->noticeHistoryChangeFromInside($baz);
    }

    /**
     * @param WithHistory $somethingHistorical
     */
    public function merge(WithHistory $somethingHistorical): void
    {
        $this->adoptHistory($somethingHistorical);
        $this->noticeHistoryChangeFromInside('merged');
    }

    public function replaceFoo(string $foo)
    {
        return new static($foo, $this->getBar(), $this->getBaz());
    }

}

/** inner */
class SomethingLessHistorical implements WithHistory
{
    /**
     * @var array
     */
    private $someHistory;

    public function __construct(array $someHistory)
    {
        $this->someHistory = $someHistory;
    }

    public function getHistory(): array
    {
        return $this->someHistory;
    }

}

class SomethingWithHistoricalRoots extends SomethingHistorical
{

    public function replaceFoo(string $foo): SomethingHistorical
    {
        $replaced = parent::replaceFoo($foo);
        $replaced->adoptHistory($this);

        return $replaced;
    }
}